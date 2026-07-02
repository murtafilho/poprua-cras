# Especificação de Tecnologia e Infraestrutura de Colocation — SIZEM BH

**Sistema:** SIZEM BH — Sistema Integrado de Zeladoria Municipal  
**Repositório:** `poprua-cras` (fork do POPRUA Geo, focado em Zeladoria Urbana / integração CRAS)  
**Versão do documento:** 1.3 — julho/2026  
**Ambiente de referência:** `vlcp-sufis01` (SUFIS/PBH)

---

## 1. Visão geral

O SIZEM BH é uma aplicação web para registro e gestão de **zeladorias urbanas** em campo: mapeamento georreferenciado de pontos, vistorias com fotos (incluindo modo offline), cadastro de moradores e relatórios gerenciais. A arquitetura segue o padrão **monólito Laravel** com camada de serviços, banco relacional geoespacial e cache/filas em Redis.

**URL de produção:** `https://sufis.pbh.gov.br/ginfi/poprua-cras/public`

---

## 2. Stack tecnológica adotada

### 2.1 Backend

| Tecnologia | Versão | Função no sistema |
|------------|--------|-------------------|
| **PHP** | 8.4 | Runtime da aplicação |
| **Laravel** | 12.x | Framework web (MVC, rotas, ORM, filas, autenticação) |
| **PostgreSQL** | 17 | Banco de dados relacional |
| **PostGIS** | 3.5 | Extensão espacial (geometrias, coordenadas, consultas GIS) |
| **Redis** | 7 | Sessões, cache e filas de processamento assíncrono |
| **Laravel Breeze** | 2.x | Autenticação web (login, registro, recuperação de senha) |
| **Laravel Sanctum** | 4.x | Autenticação por token para API |
| **Spatie Permission** | 6.x | Controle de acesso baseado em papéis (RBAC) |
| **Spatie MediaLibrary** | 11.x | Upload, armazenamento e conversão de fotos de vistorias |
| **Spatie Activity Log** | 4.x | Auditoria de alterações em registros |
| **Spatie Laravel Backup** | 9.x | Rotinas de backup da aplicação e banco |
| **DomPDF (barryvdh)** | 3.x | Geração de relatórios em PDF |
| **proj4php** | 2.x | Conversão de projeções cartográficas |

### 2.2 Frontend

| Tecnologia | Versão | Função no sistema |
|------------|--------|-------------------|
| **Blade** | (Laravel) | Templates server-side |
| **Alpine.js** | 3.x | Interatividade leve no HTML (sem SPA completa) |
| **Leaflet** | 1.9 | Mapas interativos com marcadores georreferenciados |
| **Leaflet MarkerCluster** | 1.5 | Agrupamento de marcadores em zoom distante |
| **Chart.js** | 4.x | Gráficos no dashboard de gestão |
| **Flatpickr** | 4.x | Seletor de datas |
| **Axios** | 1.x | Requisições HTTP assíncronas |
| **Vite** | 7.x | Bundler e build de assets frontend |
| **Service Worker (PWA)** | — | Cache offline e upload de fotos em modo desconectado |

### 2.3 Padrões arquiteturais

- **Controllers finos** — lógica de negócio concentrada em `app/Services/`
- **Form Requests** — validação de entrada centralizada
- **Policies** — autorização por recurso (ex.: ciclo de vida da vistoria: aberto → finalizado → cancelado)
- **Filas Redis** — conversão de imagens (`media-conversions`) e tarefas assíncronas
- **Fotografias offline-first** — compactação WebP no cliente, fila IndexedDB e conversões WebP assíncronas no servidor (detalhado na seção 2.4)
- **SRID 4326** — padrão de coordenadas geográficas (WGS 84)
- **ETL** — migração de dados do sistema legado POPRUA Geo via `postgres_fdw` (`etl/migrate.sql`)

### 2.4 Arquitetura de fotografias e compactação WebP

O SIZEM BH adota uma arquitetura **offline-first** para fotografias de zeladorias e moradores: a imagem é comprimida no dispositivo do profissional de campo (preferencialmente em **WebP**), persistida localmente quando não há rede e enviada ao servidor de forma assíncrona. No backend, o **Spatie MediaLibrary** armazena o original e gera derivações WebP em fila.

#### 2.4.1 Visão geral do fluxo

```
┌──────────────────────┐     ┌─────────────────────┐     ┌────────────────────────┐
│ Captura (câmera /    │     │ Compactação cliente │     │ Fila local IndexedDB   │
│ galeria mobile)      │────▶│ Canvas → WebP/JPEG  │────▶│ DB: poprua_fotos       │
│ vistoria-form.js     │     │ max 1920px, q 0.7–0.8│     │ store: pendentes       │
└──────────────────────┘     └─────────────────────┘     └───────────┬────────────┘
                                                                      │
                    ┌─────────────────────────────────────────────────┘
                    ▼
         ┌──────────────────────┐     ┌─────────────────────────────┐
         │ Service Worker (PWA)   │────▶│ API REST                    │
         │ Background Sync / poll │     │ POST /api/vistorias/fotos   │
         │ public/sw.js           │     │ POST /api/moradores/fotos   │
         └──────────────────────┘     └──────────────┬──────────────┘
                                                     ▼
                              ┌──────────────────────────────────────────┐
                              │ FotoService → Spatie MediaLibrary        │
                              │ collection `fotos` em storage/app/       │
                              └──────────────────┬───────────────────────┘
                                                 ▼
                              ┌──────────────────────────────────────────┐
                              │ Fila Redis: media-conversions            │
                              │ conversões thumb + preview em WebP       │
                              │ (queue-poprua-cras)                      │
                              └──────────────────────────────────────────┘
```

**Domínios cobertos:** zeladorias (`Vistoria`) e moradores (`Morador`), ambos com trait `HasMedia` e coleção `fotos`.

#### 2.4.2 Compactação no cliente (navegador)

A compactação ocorre **antes** do envio ao servidor, reduzindo consumo de dados móveis e tempo de upload em campo.

| Etapa | Implementação | Parâmetros |
|-------|---------------|------------|
| Detecção de formato | `resources/js/img-format.js` | Testa suporte a `canvas.toDataURL('image/webp')`; usa **WebP** quando disponível, senão **JPEG** (fallback para iOS &lt; 14) |
| Redimensionamento | `vistoria-form.js`, `vistoria-edit.js`, `offline-upload.js` | Largura/altura máx. **1920 px**, mantendo proporção |
| Qualidade | `processPhotoFile()` / `_compressImage()` | **0,8** no formulário; **0,7** na fila offline |
| Limite de arquivo | `offline-upload.js` | **30 MB** no cliente (pré-compactação) |
| Nome do arquivo | `imgName()` | Extensão `.webp` ou `.jpg` coerente com o MIME gerado |

**Resultado típico:** foto de câmera (~5 MB JPEG) reduzida para **~200–400 KB** em WebP antes do upload (conforme UC-007).

#### 2.4.3 Persistência offline (IndexedDB + Service Worker)

| Componente | Função |
|------------|--------|
| `offline-upload.js` | Camada canônica da fila: grava, lista e sincroniza fotos pendentes |
| IndexedDB `poprua_fotos` | Store `pendentes` com `vistoria_id` (ou `temp_*` antes do submit do formulário) |
| `public/sw.js` | Background Sync (`upload-fotos`) em Chromium; reenvio com cookie `XSRF-TOKEN` |
| `app.js` | Badge de sincronização (`#sync-badge`) e polling em navegadores sem Background Sync |

O formulário de criação **não envia blobs** no `POST /vistorias`; as fotos são reconciliadas após persistência da zeladoria (`vincularTempId` em `vistoria-show.js`).

#### 2.4.4 Armazenamento e conversões no servidor

**Spatie MediaLibrary 11.x** gerencia coleções, disco e conversões assíncronas.

| Item | Especificação |
|------|---------------|
| Coleção | `fotos` (modelos `Vistoria` e `Morador`) |
| MIME aceitos | `image/jpeg`, `image/png`, `image/webp` |
| Disco | `local` → `storage/app/public/` (symlink `public/storage`) |
| Validação API | até **10 MB** por imagem (`jpeg`, `jpg`, `png`, `webp`) |
| Serviço | `FotoService` — upload, listagem, legenda e flag `publica` para relatórios |

**Conversões geradas em fila (formato WebP):**

| Conversão | Dimensões | Qualidade | Uso |
|-----------|-----------|-----------|-----|
| `thumb` | 300×300 px (crop) + sharpen | 80% | Grade de miniaturas, listagens |
| `preview` | 800×600 px | 85% | Visualização ampliada |

As conversões são enfileiradas na fila Redis **`media-conversions`** (`->queued()` em `registerMediaConversions`), processadas pelo container **`queue-poprua-cras`**.

#### 2.4.5 Otimização adicional no servidor (binários WebP)

O container PHP inclui ferramentas de otimização de imagem (`docker/Dockerfile`: `jpegoptim`, `optipng`, `pngquant`, `gifsicle`, `webp`). O Spatie Image Optimizer aplica **`cwebp`** com parâmetros configurados em `config/media-library.php`:

- Método de compressão lento (`-m 6`) para melhor taxa
- 10 passes de análise (`-pass 10`)
- Multithreading (`-mt`)
- Fator de qualidade **80** (`-q 80`)

#### 2.4.6 Endpoints de API (fotos)

| Método | Rota | Descrição |
|--------|------|-----------|
| `POST` | `/api/vistorias/fotos` | Upload de foto de zeladoria (`vistoria_id`, `foto`, `legenda`) |
| `GET` | `/api/vistorias/{id}/fotos/status` | Lista fotos com URLs (`url`, `thumb`, `preview`) |
| `PATCH` | `/api/vistorias/{id}/fotos/{mediaId}/legenda` | Atualiza legenda |
| `POST` | `/api/vistorias/{id}/fotos/{mediaId}/toggle-publica` | Inclusão no relatório impresso |
| `POST` | `/api/moradores/fotos` | Upload de foto de morador (análogo) |

Upload tradicional via formulário web (`multipart` em `VistoriaController`) permanece disponível para edição desktop.

#### 2.4.7 Requisitos de infraestrutura para fotografias

| Requisito | Motivo |
|-----------|--------|
| Container **`queue-poprua-cras`** em execução | Processar conversões WebP (`thumb`, `preview`) |
| Redis com fila **`media-conversions`** | Desacoplar processamento de imagem da requisição HTTP |
| Disco em `storage/app/` | Crescimento dominante no horizonte de 5 anos — ver seção 3.5.1 |
| `PHP_UPLOAD_MAX_FILE_SIZE=64M` | Margem para uploads diretos via formulário |
| Ferramentas `cwebp` / GD no container | Geração e otimização de derivações WebP |

### 2.5 Ferramentas de desenvolvimento e qualidade

| Ferramenta | Uso |
|------------|-----|
| PHPUnit 11 | Testes automatizados (feature + unit) |
| PHPStan / Larastan | Análise estática de tipos |
| Laravel Pint | Formatação de código PHP |
| Playwright | Testes end-to-end (opcional) |
| GitHub Actions | CI (testes) e CD (deploy em produção) |

---

## 3. Infraestrutura de colocation necessária

O sistema foi projetado para operar em **servidor dedicado ou colocation** na rede institucional (RMI), com containers Docker orquestrados por `docker-compose` e proxy reverso Apache no host.

### 3.1 Requisitos de hardware (produção)

Dimensionamento em **dois patamares**: implantação inicial e **horizonte de 5 anos**, considerando crescimento acentuado do volume de fotografias (seção 3.5.1).

#### 3.1.1 Implantação inicial (ano 1)

| Recurso | Mínimo | Recomendado | Observação |
|---------|--------|-------------|------------|
| **CPU** | 4 vCPUs | 8 vCPUs | App (1,0) + queue (0,5) + picos de conversão WebP |
| **RAM** | 4 GB | 8 GB | Stack Docker ~1,7 GB + SO + Apache + margem para filas |
| **Disco** | 200 GB SSD | 500 GB SSD | Código + banco (~2 GB) + fotos ano 1 + pool de backup local |
| **Rede** | 100 Mbps | 1 Gbps | Upload em lote após jornada de campo; backup off-site |

#### 3.1.2 Horizonte de 5 anos (projeção de fotos)

| Cenário operacional | Disco fotos + derivações (5 anos) | Disco total provisionado* | CPU / RAM |
|---------------------|-----------------------------------|---------------------------|-----------|
| **Conservador** | ~70 GB | **300 GB SSD** | 4 vCPU / 8 GB RAM |
| **Moderado** (referência) | ~140 GB | **500 GB – 1 TB SSD** | 8 vCPU / 8–16 GB RAM |
| **Intensivo** | ~400 GB | **1,5 – 2 TB SSD ou SAN/NFS** | 8+ vCPU / 16 GB RAM |

\* Inclui banco PostgreSQL (~5–20 GB), backups locais (retenção 30 dias), logs, código e **margem de 30%** para crescimento acima da projeção.

**Recomendação para colocation PBH/SUFIS:** provisionar **volume dedicado** (não compartilhar partição raiz do SO) com **500 GB SSD** na implantação e caminho de expansão para **1 TB** sem migração de host (LVM, SAN ou troca de volume).

**Ambiente de referência atual (`vlcp-sufis01`):** 8 vCPUs, 8 GB RAM — stack **monolítica** (app + banco + fotos no mesmo host). Adequada aos primeiros 1–2 anos; para horizonte de 5 anos com crescimento de fotos, recomenda-se a **arquitetura em três servidores** (seção 3.11).

#### 3.1.3 Comparativo: monolito vs. servidores separados

| Aspecto | Monolito (atual) | **Recomendado (5 anos)** |
|---------|------------------|--------------------------|
| Servidores | 1 host com Docker Compose | 3 hosts especializados |
| Banco | Container `pg17-poprua-cras` local | PostgreSQL dedicado (VM ou bare metal) |
| Fotos | `storage/app/public/` no host da app | Servidor de arquivos ou object storage |
| Escalabilidade | Disco e CPU competem entre si | Escala independente por camada |
| Backup | Banco e fotos no mesmo host | Políticas e destinos distintos por tier |
| Risco | Falha de disco afeta app + dados + mídia | Blast radius reduzido |

### 3.2 Sistema operacional do host

| Item | Especificação |
|------|---------------|
| SO | Linux (Debian 9+ ou equivalente estável) |
| Docker Engine | 20.10+ com Docker Compose v2 |
| Servidor web | Apache 2.4 (proxy FastCGI para o container PHP) |
| TLS/HTTPS | Certificado institucional (terminação no Apache ou balanceador upstream) |
| Firewall | Portas de aplicação expostas apenas em `127.0.0.1` (loopback) |

### 3.3 Topologia de containers Docker

A stack de produção é definida em `docker-compose.yml` (fonte canônica — ADR-006):

```
                    Internet / rede PBH
                              │
                              ▼
              ┌───────────────────────────────┐
              │  Apache 2.4 (host)            │
              │  sufis.pbh.gov.br             │
              │  /ginfi/poprua-cras/public    │
              │       │ proxy:fcgi            │
              │       ▼ 127.0.0.1:9086      │
              └───────────────────────────────┘
                              │
    ┌─────────────────────────┴─────────────────────────┐
    │           Rede Docker: poprua-cras                │
    │                                                   │
    │  ┌─────────────┐  ┌──────────────┐  ┌────────┐ │
    │  │ app         │  │ queue        │  │ ssh    │ │
    │  │ PHP 8.4     │  │ worker       │  │ sidecar│ │
    │  │ FPM+Nginx   │  │ (filas)      │  │ :2226  │ │
    │  │ 512 MB      │  │ 256 MB       │  │ 256 MB │ │
    │  └──────┬──────┘  └──────┬───────┘  └────────┘ │
    │         │                │                      │
    │         ▼                ▼                      │
    │  ┌─────────────┐  ┌──────────────┐             │
    │  │ db          │  │ redis        │             │
    │  │ PG17+PostGIS│  │ 7-alpine     │             │
    │  │ 512 MB      │  │ 128 MB       │             │
    │  └─────────────┘  └──────────────┘             │
    │         │                                       │
    │    volume pgdata (persistente)                  │
    └─────────────────────────────────────────────────┘
```

**Serviços e limites de recursos:**

| Container | Imagem / base | RAM (limite) | CPU (limite) | Função |
|-----------|---------------|--------------|--------------|--------|
| `init-perms-poprua-cras` | alpine:3.20 | efêmero | — | Normalização de permissões no start |
| `php84-poprua-cras` | serversideup/php:8.4-fpm-nginx | 512 MB | 1,0 | Aplicação PHP-FPM |
| `queue-poprua-cras` | mesma imagem do app | 256 MB → **512 MB**† | 0,5 → **1,0**† | Worker de filas (`default`, `media-conversions`) |
| `pg17-poprua-cras` | postgis/postgis:17-3.5 | 512 MB | — | Banco PostgreSQL + PostGIS |
| `redis-poprua-cras` | redis:7-alpine | 128 MB | — | Cache, sessão e filas |
| `ssh-poprua-cras` | Dockerfile.ssh | 256 MB | 0,5 | Acesso administrativo (porta 2226, loopback) |

† A partir do **ano 2** ou quando a fila `media-conversions` acumular > 500 jobs: elevar limite de RAM/CPU do worker ou adicionar segunda réplica (`docker compose scale queue=2`).

### 3.4 Portas e conectividade

Todas as portas abaixo ficam vinculadas a **127.0.0.1** (não expostas diretamente à internet):

| Porta (host) | Destino | Protocolo | Uso |
|--------------|---------|-----------|-----|
| 9086 | PHP-FPM (container app) | FastCGI | Proxy Apache → aplicação |
| 5434 | PostgreSQL (container db) | TCP | Acesso administrativo local / ferramentas |
| 6380 | Redis (container redis) | TCP | Acesso administrativo local |
| 2226 | SSH sidecar | TCP | Manutenção remota via túnel SSH |

**Acesso externo dos usuários:** exclusivamente via HTTPS (443) no Apache, path `/ginfi/poprua-cras/public`.

### 3.5 Armazenamento e persistência

| Dado | Localização | Persistência |
|------|-------------|--------------|
| Código-fonte | `/var/www/html/joomla_sufis/ginfi/poprua-cras/` | Bind mount (Git) |
| Banco PostgreSQL | Volume Docker `pgdata` | Persistente entre rebuilds |
| Fotos e mídia (originais + WebP) | `storage/app/public/` via Spatie MediaLibrary | **Volume dedicado** no host; conversões `thumb` e `preview` em `conversions/` |
| Backups | `/var/backups/poprua-cras/` | Bind mount em partição separada ou volume distinto |
| Logs Laravel | `storage/logs/` | Rotacionados no host |
| Chaves SSH / config admin | `/opt/docker/poprua-cras/ssh-data`, `claude-data` | Persistente no host |

**Estimativa imediata:** banco ~1,5 GB após ETL inicial; fotos ainda incipientes em relação à projeção de 5 anos.

#### 3.5.1 Projeção de armazenamento de fotos — horizonte 5 anos

O volume de fotos é o **principal driver de crescimento** da infraestrutura. Cada foto gera **três arquivos** no servidor: original (WebP/JPEG compactado no cliente) + conversões `thumb` e `preview` (WebP em fila).

**Premissas de cálculo** (estimativas para planejamento; recalibrar com dados operacionais reais após 6–12 meses):

| Premissa | Valor conservador | Valor moderado | Valor intensivo |
|----------|-------------------|----------------|-----------------|
| Pontos ativos mapeados | ~3.000 | ~3.000 | ~3.500 |
| Zeladorias por ponto/ano | 2 | 4 | 6 |
| Fotos por zeladoria | 4 | 5 | 8 |
| Fotos de moradores/ano | +15% sobre zeladorias | +20% | +25% |
| Tamanho médio por foto no servidor* | 0,45 MB | 0,45 MB | 0,50 MB |
| Curva de adoção (anos 1–5) | 50% → 100% do ritmo | 50% → 100% | 60% → 120% |

\* Original ~300 KB + `thumb` ~30 KB + `preview` ~100 KB (média pós-pipeline WebP descrito na seção 2.4).

**Fotos acumuladas em 5 anos (estimativa):**

| Cenário | Fotos/ano (estado pleno) | Total ~5 anos | Armazenamento de mídia |
|---------|--------------------------|---------------|------------------------|
| Conservador | ~28.000 | ~120.000 | **~55 GB** |
| Moderado | ~72.000 | ~310.000 | **~140 GB** |
| Intensivo | ~180.000 | ~850.000 | **~400 GB** |

**Demais componentes de disco (acumulado 5 anos):**

| Componente | Conservador | Moderado | Intensivo |
|------------|-------------|----------|-----------|
| PostgreSQL (`pgdata`) | 5 GB | 15 GB | 25 GB |
| Backups locais (30 dias, comprimidos) | 40 GB | 100 GB | 250 GB |
| Logs, código, SO | 15 GB | 20 GB | 30 GB |
| **Total provisionado recomendado** | **300 GB** | **500 GB – 1 TB** | **1,5 – 2 TB** |

#### 3.5.2 Medidas de infraestrutura para suportar o crescimento

| Medida | Quando adotar | Objetivo |
|--------|---------------|----------|
| Volume SSD **dedicado** para `storage/` | Implantação | Evitar saturação da partição raiz |
| **Backup off-site** (S3/SFTP via Spatie Backup) | Ano 1 (obrigatório ano 2+) | Proteger acervo fotográfico; liberar espaço local |
| Alerta de disco em **70%** e **85%** | Implantação | Antecipar expansão antes de impacto operacional |
| Escalar worker `queue` (CPU/RAM ou réplicas) | Fila `media-conversions` > 500 jobs ou latência > 30 min | Manter conversões WebP em dia após picos de campo |
| Redis **256 MB** | Fila persistente ou picos diários > 2.000 uploads | Evitar eviction de jobs |
| Armazenamento **objeto** (S3/MinIO) para mídia | > 300 GB de fotos ou política institucional | Desacoplar blobs do servidor de aplicação |
| Política de **retenção/arquivamento** | Ano 3+ (definir com área de negócio) | Zeladorias finalizadas > N anos → tier frio |
| Rede **1 Gbps** no link de backup | A partir de 200 GB de mídia | Janela de backup noturno viável |

#### 3.5.3 Picos operacionais (dimensionamento de fila e rede)

Após jornada de campo, equipes podem sincronizar dezenas de fotos em curto intervalo (upload offline). Exemplo de pico **moderado**:

- 20 profissionais × 15 fotos/dia = **300 uploads/dia**
- Cada upload gera 2 jobs de conversão → **600 jobs** na fila `media-conversions`
- Com 1 worker (0,5 vCPU): ~5–10 s/conversão → fila esvazia em **1–2 h** (aceitável)
- Cenário intensivo (100+ uploads simultâneos): considerar **2 workers** ou CPU 1,0 dedicada à fila

Upload de campo: 300 fotos × 300 KB ≈ **90 MB/dia** de entrada (moderado); picos mensais podem concentrar **2–5 GB** em dias de blitz de zeladoria.

### 3.6 Requisitos de rede institucional

| Requisito | Descrição |
|-----------|-----------|
| **DNS** | Resolução de `sufis.pbh.gov.br` (ou hostname definido) |
| **HTTPS** | Certificado válido; header `X-Forwarded-Proto: https` configurado no Apache |
| **Saída HTTPS** | Para GitHub (deploy automático), eventualmente backup off-site (S3/SFTP) |
| **Acesso interno** | SSH administrativo ao host (`vlcp-sufis01` ou equivalente) |
| **ETL (opcional)** | Conexão read-only ao banco legado POPRUA Geo via `host.docker.internal:5433` durante migração |

### 3.7 Segurança (hardening aplicado — ADR-008)

- Imagens Docker com **digest pinning** (versões imutáveis)
- `security_opt: no-new-privileges:true` em todos os serviços
- `cap_drop: [ALL]` com `cap_add` mínimo por serviço
- Redis com **senha obrigatória** (`requirepass`)
- Sessões criptografadas (`SESSION_ENCRYPT=true`)
- Container PHP executa como usuário **www-data** (não-root)
- Banco e Redis **não acessíveis** pela internet — apenas rede Docker interna e loopback

### 3.8 Pipeline de deploy

```
Desenvolvedor  →  git push (main)  →  GitHub  →  GitHub Actions (runner self-hosted)
                                                      │
                                                      ▼
                                              docker/deploy.sh
                                              (git pull + migrate + cache)
```

**Alternativa:** cron de polling a cada 3 minutos (`docker/install-auto-deploy-cron.sh`).

**Validação pós-deploy:** `docker/smoke-test.sh` (4 verificações: FastCGI, URL pública 200+CSRF, dados no banco, ausência de erros no log).

### 3.9 Backup e continuidade

| Item | Especificação |
|------|---------------|
| Backup do banco | `pg_dump` diário via Spatie Backup ou cron |
| Backup de mídia | Incluir `storage/app/public/` no pacote Spatie Backup (cresce com fotos — seção 3.5.1) |
| Destino local | `/var/backups/poprua-cras/` — retenção **30 dias**; partição ou volume separado |
| Backup off-site | **Obrigatório a partir do ano 2** ou ao atingir 100 GB de mídia; S3/SFTP (`BACKUP_DISK`) |
| RPO sugerido | 24 h (banco + mídia); fotos do dia corrente toleram RPO até sincronização offline |
| RTO sugerido | < 4 h (cenário moderado); < 8 h se restauração incluir > 200 GB de mídia |
| Teste de restore | Semestral — validar `pg_restore` + integridade de amostra de fotos |

**Estimativa de backup off-site (5 anos, cenário moderado):** ~140 GB de mídia + incrementais → planejar **300–500 GB** de capacidade remota com lifecycle (tier frio após 12 meses).

### 3.10 Monitoramento recomendado

- Disponibilidade HTTP da URL pública (smoke-test ou monitor externo)
- **Uso de disco** em `storage/app/public/` e volume `pgdata` — alertas em **70%** e **85%**
- Tamanho da fila Redis `media-conversions` (`LLEN` ou Horizon, se adotado)
- Contagem de mídia: `SELECT COUNT(*) FROM media` — baseline mensal para validar projeção
- Logs em `storage/logs/laravel.log` (alertas para `ERROR`/`CRITICAL`)
- Healthcheck dos containers (`docker ps`, `pg_isready`, `redis-cli ping`, `pgrep queue:work`)
- Uso de memória e CPU por container (`docker stats`), especialmente `queue-poprua-cras`
- Taxa de uploads/dia via access log ou métrica de API `/api/vistorias/fotos`

### 3.11 Arquitetura recomendada — servidor de banco e servidor de arquivos separados

Para o horizonte de **5 anos** e crescimento acentuado de fotografias (seção 3.5.1), recomenda-se **desacoplar** a infraestrutura em três camadas na rede institucional (RMI/VLAN dedicada). O estado atual (tudo em `vlcp-sufis01`) permanece válido como **fase 1**; a separação deve ser planejada entre o **ano 1 e o ano 2** de operação plena, ou ao atingir **40% de uso de disco** no host monolítico.

#### 3.11.1 Topologia alvo (três servidores)

```
                         Internet / VPN PBH
                                  │
                                  ▼
              ┌───────────────────────────────────────┐
              │  SERVIDOR 1 — Aplicação (SIZEM-APP)   │
              │  Apache + Docker: app, queue, redis   │
              │  Sem PostgreSQL; sem armazenamento    │
              │  de fotos em disco local de produção  │
              └───────────────┬───────────┬───────────┘
                              │           │
              rede privada    │           │  rede privada
              (VLAN)          │           │  (VLAN)
                              ▼           ▼
        ┌─────────────────────────┐   ┌─────────────────────────────┐
        │ SERVIDOR 2 — Banco      │   │ SERVIDOR 3 — Arquivos       │
        │ (SIZEM-DB)              │   │ (SIZEM-STORAGE)             │
        │ PostgreSQL 17 + PostGIS │   │ MinIO (S3) ou NFS dedicado  │
        │ Apenas porta 5432/tcp   │   │ API S3 :9000 ou NFS :2049   │
        │ para IPs do SIZEM-APP   │   │ para IPs do SIZEM-APP       │
        └─────────────────────────┘   └─────────────────────────────┘
```

#### 3.11.2 Servidor 2 — Banco de dados (SIZEM-DB)

**Função:** exclusivamente dados relacionais e geoespaciais (metadados de fotos na tabela `media`; blobs ficam no servidor 3).

| Recurso | Ano 1–2 | Horizonte 5 anos (moderado) | Horizonte 5 anos (intensivo) |
|---------|---------|----------------------------|------------------------------|
| **CPU** | 4 vCPUs | 8 vCPUs | 8–16 vCPUs |
| **RAM** | 16 GB | 32 GB | 64 GB |
| **Disco** | 100 GB SSD | 250 GB SSD (NVMe) | 500 GB SSD (NVMe) |
| **SO** | Debian 12+ ou RHEL compatível | Idem | Idem |
| **Software** | PostgreSQL **17** + extensão **PostGIS 3.5** | Idem | Idem + réplica read-only (opcional) |

**Configuração recomendada:**

- Instalação **nativa** do PostgreSQL no host (não container em produção de longo prazo) — facilita backup com `pg_basebackup`, tuning de `shared_buffers` e patches de SO.
- `max_connections` ≥ 100; `shared_buffers` ≈ 25% da RAM; `work_mem` ajustado para queries PostGIS.
- Acesso **somente** a partir dos IPs do SIZEM-APP (firewall `iptables`/`nftables` ou security group da rede PBH).
- TLS opcional na conexão (`sslmode=require`) se política institucional exigir.
- Backup dedicado: `pg_dump` diário + `pg_basebackup` semanal; destino em storage separado ou servidor de arquivos (bucket `sizem-db-backups`).
- **Sem exposição** à internet; administração via jump host SSH.

**Integração com a aplicação:** `.env` do SIZEM-APP aponta para host remoto:

```env
DB_HOST=sizem-db.rmi.pbh.gov.br
DB_PORT=5432
DB_DATABASE=poprua_cras
DB_USERNAME=poprua_cras
DB_PASSWORD=<segredo>
```

No `docker-compose.yml`, o serviço `db` é **removido** da stack de produção; container `app` e `queue` conectam ao host externo via rede VLAN.

#### 3.11.3 Servidor 3 — Fotos e arquivos (SIZEM-STORAGE)

**Função:** armazenar originais e conversões WebP (`thumb`, `preview`) geradas pelo Spatie MediaLibrary; servir leitura para a aplicação.

Duas opções equivalentes para colocation institucional — **preferir Opção A** para escala e backup nativo:

| Critério | **Opção A — Object storage (recomendada)** | Opção B — NFS dedicado |
|----------|---------------------------------------------|-------------------------|
| Software | **MinIO** (S3-compatible) ou appliance S3 da infra PBH | NFSv4 em servidor Linux dedicado |
| Integração Laravel | `MEDIA_DISK=s3` + disco em `config/filesystems.php` | Mount NFS em `storage/app/public/`; `MEDIA_DISK=public` |
| Escalabilidade | Buckets, lifecycle, replicação entre sites | Escala vertical (disco) + snapshots SAN |
| Backup | Versionamento de bucket + replicação off-site | Snapshots do volume + `rsync`/backup no servidor NFS |
| Mudança de código | Configuração `.env` (suporte S3 já previsto) | Mínima (caminho local via mount) |
| Servir URLs | Via Laravel (`/storage`) ou CDN interna | Via Apache/app (`public/storage`) |

**Dimensionamento SIZEM-STORAGE (5 anos):**

| Recurso | Conservador | Moderado | Intensivo |
|---------|-------------|----------|-----------|
| **CPU** | 4 vCPUs | 4–8 vCPUs | 8 vCPUs |
| **RAM** | 8 GB | 16 GB | 32 GB |
| **Disco** | 300 GB SSD | **1 TB SSD** | **2 TB SSD** (ou HDD tier frio + SSD cache) |
| **Rede** | 1 Gbps | 1 Gbps | 10 Gbps (se replicação local) |

**Configuração recomendada (Opção A — MinIO):**

- Bucket `sizem-media-prod` (fotos de produção); bucket `sizem-backups` (dumps DB + cópias de segurança).
- Acesso restrito por chave (`AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY`) apenas do SIZEM-APP.
- Versionamento habilitado no bucket de mídia (proteção contra exclusão acidental).
- Lifecycle: objetos > 24 meses podem migrar para tier frio (política a definir com negócio).

```env
FILESYSTEM_DISK=s3
MEDIA_DISK=s3
AWS_ACCESS_KEY_ID=<chave-restrita-app>
AWS_SECRET_ACCESS_KEY=<segredo>
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=sizem-media-prod
AWS_ENDPOINT=https://sizem-storage.rmi.pbh.gov.br
AWS_USE_PATH_STYLE_ENDPOINT=true
```

> **Nota:** conversões WebP (`queue-poprua-cras`) leem o original e gravam derivações no mesmo disco/bucket configurado em `MEDIA_DISK`. O worker precisa de **latência baixa** (< 5 ms) até o SIZEM-STORAGE — preferir mesma VLAN, não WAN.

#### 3.11.4 Servidor 1 — Aplicação (SIZEM-APP) na arquitetura separada

Com banco e arquivos externos, o host de aplicação fica **leve em disco**:

| Recurso | Especificação |
|---------|---------------|
| **CPU** | 8 vCPUs |
| **RAM** | 8–16 GB |
| **Disco** | 100 GB SSD (código, logs, cache temporário de conversão) |
| **Containers** | `app`, `queue`, `redis`, `init-perms`, `ssh` — **sem** `db` |
| **Apache** | Proxy FastCGI → `127.0.0.1:9086` (inalterado) |

Redis permanece no SIZEM-APP (sessão, cache e filas são voláteis e de baixo volume). Não é necessário servidor Redis dedicado no cenário moderado.

#### 3.11.5 Rede e segurança entre servidores

| Origem | Destino | Porta | Protocolo |
|--------|---------|-------|-----------|
| SIZEM-APP | SIZEM-DB | 5432 | PostgreSQL |
| SIZEM-APP | SIZEM-STORAGE | 9000 (MinIO) ou 2049 (NFS) | S3 API / NFS |
| Admin (jump host) | Os três servidores | 22 | SSH |
| Internet | SIZEM-APP | 443 | HTTPS (somente camada web) |
| Internet | SIZEM-DB, SIZEM-STORAGE | — | **Bloqueado** |

Latência entre APP e DB: **< 2 ms** (mesmo datacenter). Entre APP e STORAGE: **< 5 ms** para não degradar fila `media-conversions`.

#### 3.11.6 Backup na arquitetura separada

| Camada | Mecanismo | Destino |
|--------|-----------|---------|
| **Banco** | `pg_dump` diário + `pg_basebackup` semanal no SIZEM-DB | Bucket `sizem-backups` no SIZEM-STORAGE ou fita/off-site institucional |
| **Fotos** | Versionamento nativo (MinIO) ou snapshots NFS | Réplica para segundo site ou fita |
| **Aplicação** | Spatie Backup (código, sem `storage/`) | Off-site; RPO 24 h |
| **Redis** | Não persistir backup (recriável) | — |

Isso corrige a lacuna atual em que o Spatie Backup **não inclui** `storage/` — com `MEDIA_DISK=s3`, o backup de fotos passa a ser responsabilidade do **SIZEM-STORAGE** (versionamento/replicação), não do servidor de aplicação.

#### 3.11.7 Plano de migração (monolito → três servidores)

| Fase | Ação | Risco | Janela |
|------|------|-------|--------|
| **1** | Provisionar SIZEM-DB; `pg_dump` + restore; apontar `.env` `DB_HOST` | Médio | Manutenção ~30 min |
| **2** | Provisionar SIZEM-STORAGE (MinIO); testar upload em homologação | Baixo | — |
| **3** | `php artisan media:export` ou script `aws s3 sync` de `storage/app/public/` → bucket | Médio | Fora do pico; horas conforme volume |
| **4** | Alterar `MEDIA_DISK=s3`; remover serviço `db` do compose; smoke-test 4/4 | Alto | Manutenção ~1 h |
| **5** | Descomissionar volume `pgdata` e disco de fotos no host monolítico após 30 dias de operação estável | Baixo | — |

Rollback de cada fase: reverter `.env` e reativar serviço `db` local no compose (manter snapshot até validação).

#### 3.11.8 Quando adotar

| Gatilho | Ação |
|---------|------|
| Implantação greenfield em colocation | Já provisionar **três servidores** desde o início |
| Monolito com disco > **40%** ou projeção moderada no ano 2 | Iniciar fase 1 (separar banco) |
| Mídia > **100 GB** ou backup de fotos inadequado | Priorizar fase 2–3 (SIZEM-STORAGE) |
| Queries PostGIS lentas competindo com I/O de fotos | Separar banco (fase 1) independentemente do disco |

---

## 4. Checklist de provisionamento (colocation)

### 4.1 Arquitetura monolítica (fase inicial — compatível com `vlcp-sufis01`)

Para implantar o SIZEM BH em **um único servidor**:

- [ ] Servidor Linux com Docker e Docker Compose instalados
- [ ] **Disco:** mínimo **200 GB SSD** (implantação); **500 GB – 1 TB** para horizonte 5 anos (cenário moderado)
- [ ] Volume **dedicado** para `storage/` e `/var/backups/` (não usar apenas partição raiz)
- [ ] 8 vCPU / 8 GB RAM (recomendado para cenário moderado)
- [ ] Apache 2.4 configurado com vhost FastCGI (`docker/apache-vhost.conf`)
- [ ] Certificado TLS no Apache ou balanceador upstream
- [ ] Repositório clonado em `/var/www/html/joomla_sufis/ginfi/poprua-cras/`
- [ ] Arquivo `.env` de produção (`APP_ENV=production`, `APP_DEBUG=false`, credenciais DB/Redis)
- [ ] `docker compose up -d --build` (inclui **app** + **queue** para conversões WebP)
- [ ] `composer install`, `npm run build`, `php artisan migrate --force`, `php artisan storage:link`
- [ ] Deploy key ou runner GitHub para CI/CD
- [ ] Diretório `/var/backups/poprua-cras/` em volume separado com retenção definida
- [ ] **Backup off-site** configurado (`BACKUP_DISK=s3` ou `sftp`) antes do 2º ano de operação
- [ ] Alertas de disco (70%/85%) e monitoramento da fila `media-conversions`
- [ ] Smoke-test verde (`bash docker/smoke-test.sh`)
- [ ] Revisão semestral da projeção da seção 3.5.1 com dados reais de `media` e uso de disco

### 4.2 Arquitetura recomendada — três servidores (horizonte 5 anos)

Para novo provisionamento em colocation com **banco e arquivos separados** (seção 3.11):

**SIZEM-APP (aplicação)**

- [ ] 8 vCPU / 8–16 GB RAM / 100 GB SSD
- [ ] Docker: `app`, `queue`, `redis` (sem container `db`)
- [ ] Apache + vhost FastCGI; HTTPS na borda
- [ ] `.env`: `DB_HOST` → SIZEM-DB; `MEDIA_DISK=s3` → SIZEM-STORAGE
- [ ] Firewall: saída apenas para SIZEM-DB:5432 e SIZEM-STORAGE:9000

**SIZEM-DB (banco)**

- [ ] 8 vCPU / 32 GB RAM / 250 GB SSD NVMe
- [ ] PostgreSQL 17 + PostGIS 3.5 (instalação nativa)
- [ ] Firewall: aceitar 5432 **somente** dos IPs do SIZEM-APP
- [ ] `pg_dump` diário + `pg_basebackup` semanal para bucket de backup
- [ ] Usuário `poprua_cras` com permissões mínimas (sem `SUPERUSER`)

**SIZEM-STORAGE (fotos/arquivos)**

- [ ] 4–8 vCPU / 16 GB RAM / **1 TB SSD** (cenário moderado 5 anos)
- [ ] MinIO ou storage S3 institucional; buckets `sizem-media-prod` e `sizem-backups`
- [ ] Versionamento habilitado; chaves de acesso restritas ao SIZEM-APP
- [ ] Firewall: API S3 **somente** dos IPs do SIZEM-APP
- [ ] Réplica ou backup off-site do bucket de mídia

**Validação cruzada**

- [ ] Latência APP → DB < 2 ms; APP → STORAGE < 5 ms
- [ ] Upload de foto + conversões WebP na fila `media-conversions`
- [ ] Restore testado: banco (`pg_restore`) + objeto de mídia do bucket
- [ ] Smoke-test verde no SIZEM-APP

---

## 5. Referências internas

| Documento | Conteúdo |
|-----------|----------|
| [CLAUDE.md](../CLAUDE.md) | Visão geral do repositório e ambientes |
| [ARQUITETURA_DOCKER.md](ARQUITETURA_DOCKER.md) | Detalhamento da arquitetura Docker (base POPRUA Geo) |
| [DEPLOY_AUTOMATICO.md](DEPLOY_AUTOMATICO.md) | Opções de deploy automático (cron vs runner) |
| [ADR-006](adr/ADR-006-infraestrutura-docker-canonical.md) | Compose canônico e init-perms |
| [ADR-008](adr/ADR-008-hardening-cras-paridade-geo.md) | Hardening de segurança dos containers |
| [UC-007](casos-de-uso/UC-007-upload-offline-fotos.md) | Caso de uso: upload offline de fotos |
| [ADR-009](adr/ADR-009-eliminar-google-drive-fotos-locais.md) | Armazenamento local de fotos (sem Google Drive) |
| `docker-compose.yml` | Definição completa da stack de produção |

---

*Documento elaborado com base no estado do repositório `poprua-cras` em julho/2026.*
