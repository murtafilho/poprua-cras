# TERMO DE REFERÊNCIA — Hospedagem e Infraestrutura de Colocation

## Sistema Integrado de Zeladoria Municipal (SIZEM BH)

| Campo | Valor |
|-------|-------|
| **Sistema** | SIZEM BH — Zeladoria Urbana / integração CRAS |
| **Repositório** | `poprua-cras` |
| **URL de produção** | `https://sufis.pbh.gov.br/ginfi/poprua-cras/public` |
| **Ambiente de referência** | `vlcp-sufis01` (SUFIS/PBH) |
| **Versão do documento** | 1.1 — julho/2026 |
| **Horizonte de projeção** | 3 anos |

---

## 1. OBJETO

Contratação ou provisionamento de **infraestrutura de colocation** (servidor dedicado ou espaço em datacenter institucional) para hospedar, operar e manter em produção contínua a aplicação web **SIZEM BH**, incluindo:

- Servidor de aplicação (web + filas de processamento);
- Banco de dados relacional geoespacial;
- Armazenamento de fotografias de zeladorias e moradores;
- Cache e sessões;
- Rotinas de backup, monitoramento e continuidade operacional.

---

## 2. JUSTIFICATIVA

O SIZEM BH é o sistema oficial de registro e gestão de **zeladorias urbanas** em campo na rede municipal: mapeamento georreferenciado de pontos, vistorias com fotografias (incluindo modo offline), cadastro de moradores e relatórios gerenciais.

A partir da operação plena, cada zeladoria (vistoria) registrará **até 10 fotografias**, com sincronização offline em lote ao final da jornada de campo. O sistema deve suportar **até 100 usuários simultâneos** (profissionais de campo, gestores e administradores). A infraestrutura atual em colocation (`vlcp-sufis01`) necessita de **dimensionamento formal** para garantir disponibilidade, performance e capacidade de armazenamento no horizonte de **3 anos**, sem interrupção dos serviços públicos prestados.

---

## 3. DESCRIÇÃO DA SOLUÇÃO

### 3.1 Finalidade

Aplicação web monolítica para registro de zeladorias urbanas com:

- Mapa interativo (Leaflet + PostGIS);
- Formulários de vistoria em campo (PWA);
- Upload de fotografias **offline-first** (IndexedDB + Service Worker);
- Gestão de moradores, pontos e relatórios PDF;
- Controle de acesso por papéis (RBAC).

### 3.2 Stack tecnológica (requisito de compatibilidade)

A infraestrutura contratada **deve suportar nativamente** a stack abaixo, conforme implementação no repositório:

| Camada | Tecnologia | Versão mínima |
|--------|------------|---------------|
| Runtime | PHP-FPM | 8.4 |
| Framework | Laravel | 12.x |
| Banco de dados | PostgreSQL + PostGIS | 17 / 3.5 |
| Cache, sessão e filas | Redis | 7.x |
| Build frontend | Node.js | 22.x |
| Bundler | Vite | 7.x |
| Containerização | Docker Engine + Compose v2 | 20.10+ / v2 |
| Servidor web (host) | Apache | 2.4 |
| Bibliotecas PHP | GD, exif, pcov, pgsql | — |
| Otimização de imagem | jpegoptim, optipng, cwebp, webp | — |

**Bibliotecas de aplicação relevantes para dimensionamento:**

| Pacote | Função |
|--------|--------|
| Spatie MediaLibrary 11.x | Armazenamento e conversão de fotos |
| Spatie Laravel Backup 9.x | Backup automatizado |
| Laravel Sanctum 4.x | Autenticação API (upload de fotos) |
| Laravel Breeze 2.x | Autenticação web |

### 3.3 Arquitetura de fotografias (driver de armazenamento)

Cada fotografia enviada gera **três arquivos** no servidor:

| Arquivo | Dimensões / formato | Tamanho médio |
|---------|---------------------|---------------|
| Original | WebP/JPEG compactado no cliente (máx. 1920 px) | ~300 KB |
| Miniatura (`thumb`) | 300×300 px WebP (fila assíncrona) | ~30 KB |
| Preview (`preview`) | 800×600 px WebP (fila assíncrona) | ~100 KB |
| **Total por foto** | — | **~430 KB** |

**Pipeline:**

1. Cliente comprime imagem (WebP, qualidade 0,7–0,8) antes do envio;
2. API recebe via `POST /api/vistorias/fotos` (até 10 MB por arquivo);
3. Spatie MediaLibrary persiste em `storage/app/public/`;
4. Worker de fila Redis (`media-conversions`) gera derivações WebP.

---

## 4. PREMISSAS DE DIMENSIONAMENTO

### 4.1 Premissas fixas (informadas pela área de negócio)

| Premissa | Valor |
|----------|-------|
| **Fotografias por vistoria** | **10 fotos** (máximo operacional por zeladoria) |
| Horizonte de projeção | **3 anos** |
| Usuários simultâneos (pico) | **100 usuários** |
| Tamanho médio por foto no servidor (3 arquivos) | **430 KB** (original + `thumb` + `preview`) |
| **Armazenamento por vistoria** | 10 × 430 KB ≈ **4,3 MB** |
| Retenção de backup local | **30 dias** |
| Sessão de usuário | Redis, TTL 120 min, criptografada |

### 4.2 Cenários de volume de vistorias (projeção 3 anos)

O volume de armazenamento depende da **quantidade de vistorias/ano**, não apenas do teto de fotos por vistoria. Adota-se curva de adoção **50% → 75% → 100%** do ritmo pleno entre os anos 1 e 3, alinhada à [ESPECIFICACAO_TECNOLOGIA_E_INFRAESTRUTURA.md](ESPECIFICACAO_TECNOLOGIA_E_INFRAESTRUTURA.md).

| Cenário | Vistorias/ano (pleno) | Fotos/ano (pleno) | Fotos acumuladas (3 anos)* | Mídia acumulada (3 anos) |
|---------|----------------------|-------------------|----------------------------|--------------------------|
| **Conservador** | ~6.000 | ~60.000 | **~135.000** | **~58 GB** |
| **Moderado** (referência) | ~12.000 | ~120.000 | **~270.000** | **~116 GB** |
| **Intensivo** | ~21.000 | ~210.000 | **~470.000** | **~200 GB** |

\* Inclui curva de adoção; não inclui fotos de moradores.

**Margem adicional:** +15% para fotos de moradores e variação de tamanho → **~67 GB / ~133 GB / ~230 GB** respectivamente.

**Fórmula de recálculo:**

```
Fotos_acumuladas = Σ (vistorias_ano_n × 10) + fotos_moradores
Armazenamento_mídia (GB) = Fotos_acumuladas × 0,00043 GB
```

### 4.3 Picos operacionais de upload (offline-first)

Após jornada de campo, profissionais sincronizam **lotes de até 10 fotos por vistoria** concluída:

| Cenário de pico | Carga | Impacto |
|-----------------|-------|---------|
| 10 vistorias sincronizando ao mesmo tempo | **100 uploads** (~30 MB) | Exige pool PHP-FPM e banda de upload |
| 20 profissionais × 1 vistoria/dia (fim do turno) | **200 uploads** em ~30 min | Fila `media-conversions`: **400 jobs** |
| 1 vistoria isolada | 10 uploads + 20 conversões | ~4,3 MB persistidos |

---

## 5. REQUISITOS DE HARDWARE — SERVIDOR DE COLOCATION

Dimensionamento calibrado para o **cenário moderado** (~12.000 vistorias/ano, 10 fotos/vistoria, ~133 GB de mídia em 3 anos). Cenário intensivo exige volume dedicado de arquivos (seção 5.2).

### 5.1 Especificação mínima (cenário conservador — ~67 GB mídia em 3 anos)

| Recurso | Especificação mínima | Justificativa |
|---------|---------------------|---------------|
| **CPU** | 4 vCPUs (x86_64) | PHP-FPM, PostGIS, conversões WebP em fila |
| **RAM** | **8 GB** | 100 sessões simultâneas + containers Docker + SO |
| **Disco** | **300 GB SSD** (volume dedicado) | Mídia (~67 GB) + banco (~8 GB) + backups (~80 GB) + SO/logs (~20 GB) + margem |
| **Rede** | 100 Mbps simétrico | Navegação + picos de sincronização de fotos |
| **IOPS** | ≥ 3.000 (SSD) | Consultas PostGIS + escrita em lote de fotos |

### 5.2 Especificação recomendada (cenário moderado — ~133 GB mídia em 3 anos)

| Recurso | Especificação recomendada | Justificativa |
|---------|--------------------------|---------------|
| **CPU** | **8 vCPUs** | 100 usuários + picos de 100 uploads + fila de conversão |
| **RAM** | **16 GB** | Headroom para picos, cache PostgreSQL e filas Redis |
| **Disco** | **500 GB SSD NVMe** (volume dedicado, separado da partição raiz) | Mídia (~133 GB) + backups locais (~150 GB) + margem 30% |
| **Rede** | **1 Gbps** | Sincronização pós-campo (lotes de 10 fotos/vistoria) + backup noturno |
| **Redundância** | RAID 1 ou snapshot diário do volume | Proteção contra falha de disco |

> **Nota:** o ambiente atual (`vlcp-sufis01`: 8 vCPU / 8 GB RAM) atende CPU no cenário moderado, mas **disco e RAM** precisam de upgrade: provisionar **≥ 500 GB** dedicados a `storage/` e elevar RAM para **16 GB**. O limite atual de **512 MB** no container `app` é insuficiente para 100 usuários — elevar para **4 GB** (seção 6.3).

### 5.3 Especificação para cenário intensivo (~230 GB mídia em 3 anos)

| Recurso | Especificação |
|---------|---------------|
| **Disco** | **1 TB SSD** ou servidor de arquivos separado (MinIO/NFS — ver [ESPECIFICACAO](ESPECIFICACAO_TECNOLOGIA_E_INFRAESTRUTURA.md) §3.11) |
| **Queue workers** | 2 réplicas (`docker compose scale queue=2`) |
| **Backup off-site** | **Obrigatório desde o ano 1** — capacidade remota ≥ 500 GB |

### 5.4 Projeção de armazenamento — 3 anos (cenário moderado de referência)

| Componente | Ano 1 | Ano 2 | Ano 3 | Observação |
|------------|-------|-------|-------|------------|
| Fotografias (mídia) | ~20 GB | ~65 GB | **~133 GB** | ~6k + 9k + 12k vistorias/ano × 10 fotos × 430 KB + moradores |
| PostgreSQL (`pgdata`) | ~2 GB | ~5 GB | ~8 GB | Metadados, geometrias, tabela `media`, auditoria |
| Backups locais (30 dias) | ~30 GB | ~80 GB | **~150 GB** | `pg_dump` + snapshot parcial de mídia crescente |
| Código, logs, SO, Docker | ~15 GB | ~18 GB | ~20 GB | Estável |
| **Total utilizado** | **~67 GB** | **~168 GB** | **~311 GB** | — |
| **Total provisionado** | **500 GB** | **500 GB** | **500 GB** | Margem ~38% no ano 3 |

**Alertas de capacidade (obrigatório):**

- Aviso em **70%** de uso do volume dedicado (~350 GB);
- Aviso crítico em **85%** (~425 GB);
- Plano de expansão documentado antes de atingir 90%.

---

## 6. REQUISITOS DE SOFTWARE E TOPOLOGIA

### 6.1 Sistema operacional do host

| Item | Requisito |
|------|-----------|
| SO | Linux amd64 — Debian 12+ ou equivalente LTS |
| Docker Engine | 20.10+ com Compose v2 |
| Apache | 2.4 com `mod_proxy_fcgi` |
| TLS | Certificado institucional válido (HTTPS obrigatório) |
| Firewall | Portas de aplicação apenas em `127.0.0.1` (loopback) |
| ACL | Suporte a `setfacl` (permissões host/container — ADR-010) |

### 6.2 Topologia Docker (stack de produção)

```
Internet / rede PBH (HTTPS :443)
         │
         ▼
┌─────────────────────────────────────┐
│  Apache 2.4 (host)                  │
│  sufis.pbh.gov.br                   │
│  /ginfi/poprua-cras/public          │
│  proxy:fcgi → 127.0.0.1:9086        │
└─────────────────────────────────────┘
         │
┌────────┴────────────────────────────────────────┐
│  Rede Docker: poprua-cras                       │
│                                                 │
│  ┌──────────────┐  ┌──────────────┐            │
│  │ php84-poprua │  │ queue-poprua │            │
│  │ -cras (app)  │  │ -cras        │            │
│  │ PHP 8.4 FPM  │  │ worker filas │            │
│  └──────┬───────┘  └──────┬───────┘            │
│         │                 │                     │
│         ▼                 ▼                     │
│  ┌──────────────┐  ┌──────────────┐            │
│  │ pg17-poprua  │  │ redis-poprua │            │
│  │ -cras (DB)   │  │ -cras        │            │
│  │ PG17+PostGIS │  │ sessão/cache │            │
│  └──────────────┘  └──────────────┘            │
│         │                                       │
│    volume pgdata (persistente)                  │
└─────────────────────────────────────────────────┘
```

### 6.3 Containers e limites de recursos (produção — 100 usuários)

| Container | Função | RAM mínima | RAM recomendada | CPU |
|-----------|--------|------------|-----------------|-----|
| `php84-poprua-cras` | Aplicação PHP-FPM | 2 GB | **4 GB** | 2,0 vCPU |
| `queue-poprua-cras` | Filas `default`, `media-conversions` | 512 MB | **1 GB** | **1,0 vCPU** |
| `pg17-poprua-cras` | PostgreSQL 17 + PostGIS 3.5 | 1 GB | **2 GB** | 1,0 vCPU |
| `redis-poprua-cras` | Sessão, cache, filas | 256 MB | **512 MB** | — |
| `init-perms-poprua-cras` | Normalização de permissões (efêmero) | — | — | — |

**Total RAM containers:** ~4 GB (mín.) / ~7 GB (recom.) + ~1 GB SO/Apache = **8–16 GB** no host.

### 6.4 Configuração PHP-FPM para 100 usuários simultâneos

| Parâmetro | Valor mínimo | Valor recomendado |
|-----------|--------------|-------------------|
| `pm` | dynamic | dynamic |
| `pm.max_children` | 30 | **50** |
| `pm.start_servers` | 8 | 10 |
| `pm.min_spare_servers` | 4 | 5 |
| `pm.max_spare_servers` | 15 | 20 |
| `PHP_MEMORY_LIMIT` | 256M | 256M |
| `PHP_MAX_EXECUTION_TIME` | 300 | 300 |
| `PHP_UPLOAD_MAX_FILE_SIZE` | 64M | 64M |
| `PHP_POST_MAX_SIZE` | 64M | 64M |
| `PHP_OPCACHE_ENABLE` | 1 | 1 |

**Estimativa de concorrência:**

- 100 usuários simultâneos ≈ 20–40 requisições PHP concorrentes em pico (navegação, mapa, listagens);
- Upload de fotos: até **100 uploads simultâneos** quando 10 vistorias sincronizam 10 fotos cada (pico pós-jornada);
- Por vistoria concluída: **10 uploads** (~3 MB) + **20 jobs** de conversão WebP na fila `media-conversions`;
- Carga diária típica (moderado, pleno): ~120.000 fotos/ano ≈ **~330 fotos/dia** ≈ **~660 jobs/dia** de conversão;
- Pico pós-campo (20 vistorias/dia sincronizando juntas): **200 uploads** + **400 jobs** — fila esvazia em **≤ 1 h** com 1 worker (1 vCPU); considerar **2 workers** no cenário intensivo.

### 6.4.1 Worker de fila — requisitos adicionais

| Parâmetro | Valor |
|-----------|-------|
| Filas processadas | `default`, `media-conversions` |
| `--timeout` | 90 s (conversão WebP de imagens até 10 MB) |
| Réplicas | 1 (moderado) / **2** (intensivo ou fila > 500 jobs) |
| Gatilho de escala | Fila `media-conversions` > 500 jobs por > 30 min |

### 6.5 Portas (loopback exclusivo)

| Porta host | Destino | Uso |
|------------|---------|-----|
| 9086 | PHP-FPM (container app) | FastCGI via Apache |
| 5434 | PostgreSQL | Administração local |
| 6380 | Redis | Administração local |
| 2226 | SSH sidecar | Manutenção via túnel |

**Acesso externo:** exclusivamente HTTPS (443) no path `/ginfi/poprua-cras/public`.

### 6.6 Persistência de dados

| Dado | Caminho | Tipo |
|------|---------|------|
| Código-fonte | `/var/www/html/joomla_sufis/ginfi/poprua-cras/` | Bind mount (Git) |
| Banco PostgreSQL | Volume Docker `pgdata` | Persistente |
| Fotografias | `storage/app/public/` (Spatie MediaLibrary) | **Volume dedicado no host** |
| Backups | `/var/backups/poprua-cras/` | Bind mount, partição separada |
| Logs | `storage/logs/` | Rotacionados |

---

## 7. REQUISITOS DE DESEMPENHO E DISPONIBILIDADE

### 7.1 Indicadores de desempenho (SLA sugerido)

| Métrica | Meta | Observação |
|---------|------|------------|
| Disponibilidade mensal | ≥ **99,5%** | Exclui janelas de manutenção programada |
| Tempo de resposta (páginas web, p95) | ≤ **3 s** | Exclui upload de fotos |
| Tempo de resposta (API fotos, p95) | ≤ **10 s** | Upload de ~300 KB compactado |
| Tempo de conversão WebP (fila) | ≤ **1 h** após pico de sincronização | 10 vistorias × 10 fotos = 200 uploads → 400 jobs |
| RPO (perda máxima de dados) | **24 h** | Backup diário |
| RTO (tempo de restauração) | ≤ **8 h** | Cenário moderado (~311 GB total no ano 3) |

### 7.2 Capacidade para 100 usuários simultâneos — cenários de teste

O provedor de infraestrutura deve demonstrar capacidade mediante teste de carga (homologação ou produção):

| Cenário | Carga | Critério de aceite |
|---------|-------|-------------------|
| Navegação concurrente | 100 sessões ativas consultando listagens e mapa | p95 ≤ 3 s, taxa de erro < 1% |
| Upload por vistoria | **10 uploads sequenciais** de ~300 KB (1 vistoria completa) | 100% HTTP 201 em ≤ 60 s |
| Sincronização offline (pico) | **100 uploads simultâneos** (10 vistorias × 10 fotos) | 100% HTTP 201; fila esvazia em ≤ 1 h |
| Conversão WebP | 400 jobs enfileirados (pico de 20 vistorias) | Todas as derivações `thumb`/`preview` geradas em ≤ 1 h |
| Disponibilidade pós-deploy | Smoke-test automatizado | 4/4 verificações verdes |

---

## 8. REQUISITOS DE REDE

| Requisito | Especificação |
|-----------|---------------|
| DNS | Resolução de `sufis.pbh.gov.br` |
| HTTPS | Certificado válido; header `X-Forwarded-Proto: https` no Apache |
| Entrada | Porta 443 (Apache); demais portas bloqueadas externamente |
| Saída HTTPS | GitHub (deploy CI/CD), eventual backup off-site (S3/SFTP) |
| Largura de banda — upload diário (moderado, pleno) | ~330 fotos × 300 KB ≈ **~100 MB/dia**; picos pós-campo: **~30–60 MB** em 30 min |
| Largura de banda — navegação (100 usuários) | ~500 MB–1 GB/dia |
| Largura de banda — backup noturno | ~50–150 GB em janela de 4 h (**1 Gbps obrigatório** no ano 2+) |

---

## 9. REQUISITOS DE SEGURANÇA

Conforme hardening implementado (ADR-008):

| Requisito | Descrição |
|-----------|-----------|
| Containers | Imagens com digest pinning; `no-new-privileges:true` |
| Capabilities | `cap_drop: [ALL]` com `cap_add` mínimo |
| Redis | Autenticação obrigatória (`requirepass`) |
| Sessões | Criptografadas (`SESSION_ENCRYPT=true`), driver Redis |
| PHP | Execução como `www-data` (não-root) |
| Banco e Redis | Inacessíveis pela internet; rede Docker interna + loopback |
| TLS | Terminação HTTPS na borda institucional |
| LGPD | Fotografias de moradores em situação de rua — controle de acesso RBAC |

---

## 10. BACKUP E CONTINUIDADE

| Item | Especificação |
|------|---------------|
| Backup do banco | `pg_dump` diário (formato custom, compressão 9) |
| Backup de mídia | Inclusão de `storage/app/public/` no pacote de backup |
| Destino local | `/var/backups/poprua-cras/` — retenção **30 dias** |
| Backup off-site | **Obrigatório** — S3 ou SFTP institucional (`BACKUP_DISK`) |
| Destino off-site | Capacidade mínima **100 GB** (ano 1) → **300 GB** (ano 3, cenário moderado) |
| Teste de restore | **Semestral** — validar `pg_restore` + integridade de amostra de fotos |
| Comando operacional | `bash docker/backup.sh` / Spatie Laravel Backup (scheduler diário) |

**Estimativa de backup off-site em 3 anos (moderado):** ~133 GB de mídia + ~8 GB de banco + incrementais → provisionar **300 GB** remotos com lifecycle para tier frio.

---

## 11. MONITORAMENTO E OPERAÇÃO

| Item | Frequência | Responsável |
|------|------------|-------------|
| Disponibilidade HTTP (URL pública) | Contínua | Infraestrutura |
| Uso de disco (`storage/`, `pgdata`, backups) | Diária | Infraestrutura |
| Alertas de disco (70% / 85%) | Automático | Infraestrutura |
| Fila Redis `media-conversions` | Diária | Aplicação |
| Logs Laravel (`ERROR`/`CRITICAL`) | Diária | Aplicação |
| Healthcheck containers | Contínua (`docker ps`) | Infraestrutura |
| Smoke-test pós-deploy | A cada deploy | CI/CD (`docker/smoke-test.sh`) |
| Contagem de mídia (`SELECT COUNT(*) FROM media`) | Mensal | Aplicação |
| Revisão de projeção vs. real | Trimestral | Gestão + Infra |

---

## 12. DEPLOY E MANUTENÇÃO

| Item | Especificação |
|------|---------------|
| Pipeline | Git push → GitHub Actions → runner self-hosted → `docker/deploy.sh` |
| URL produção | `https://sufis.pbh.gov.br/ginfi/poprua-cras/public` |
| Janela de manutenção | Preferencialmente fora do horário de campo (após 20h ou fins de semana) |
| Migrations | Automáticas no deploy (`php artisan migrate --force`) |
| Cache | Limpeza automática pós-deploy |
| Scheduler | Cron Laravel (backup, `media:clean-orphaned` às 03:00) |

---

## 13. ENTREGÁVEIS DO PROVEDOR DE INFRAESTRUTURA

1. Servidor provisionado conforme seção 5 (mínimo ou recomendado);
2. Volume SSD **dedicado** para `storage/` e `/var/backups/` (não compartilhar partição raiz);
3. Apache configurado com vhost FastCGI (`docker/apache-vhost.conf`);
4. Docker Compose operacional (app + queue + db + redis);
5. Certificado TLS válido;
6. Backup local automatizado (retenção 30 dias);
7. Backup off-site configurado e testado;
8. Monitoramento de disco e disponibilidade;
9. Documentação de credenciais e procedimentos de restore;
10. Smoke-test verde (`bash docker/smoke-test.sh` — 4/4).

---

## 14. CRITÉRIOS DE ACEITE

| # | Critério | Verificação |
|---|----------|-------------|
| 1 | URL pública responde HTTP 200 com CSRF | Smoke-test |
| 2 | Upload de foto via API retorna 201 | Teste funcional |
| 3 | Conversões WebP (`thumb`, `preview`) geradas em ≤ 1 h após pico de sincronização | Inspeção de `storage/` + fila Redis |
| 4 | 100 sessões concorrentes com p95 ≤ 3 s | Teste de carga |
| 5 | Backup diário executado e restaurável | Teste semestral |
| 6 | Uso de disco monitorado com alertas 70%/85% | Dashboard/alerta |
| 7 | Portas 9086, 5434, 6380 acessíveis apenas via loopback | Scan de portas |
| 8 | Deploy automático via GitHub Actions funcional | Deploy de teste |

---

## 15. ESTIMATIVA DE CARGA DE REDE E PROCESSAMENTO — RESUMO

**Cenário moderado de referência** (~12.000 vistorias/ano em pleno, 10 fotos/vistoria):

| Indicador | Valor (3 anos) |
|-----------|----------------|
| Vistorias acumuladas | ~27.000 |
| Fotos acumuladas (vistorias + moradores) | **~310.000** |
| Armazenamento de mídia | **~133 GB** |
| Armazenamento por vistoria | **~4,3 MB** |
| Upload médio diário (pleno) | **~100 MB** (~33 vistorias × 10 fotos) |
| Jobs de conversão/dia (pleno) | **~660** |
| Pico de sincronização | **100 uploads** + **400 jobs** (10 vistorias simultâneas) |
| Requisições HTTP/dia (100 usuários ativos) | ~5.000–15.000 |
| Sessões Redis simultâneas (pico) | 100 |
| RAM necessária (host) | 8 GB (mín.) / **16 GB** (recom.) |
| Disco necessário (ano 3) | **~311 GB usado** / **500 GB provisionado** |

---

## 16. REVISÃO E ADEQUAÇÃO

Este Termo de Referência deve ser **revisado trimestralmente** nos primeiros 12 meses de operação plena, comparando:

- Contagem real de fotos e vistorias/mês (`media`, `vistorias`);
- Uso de disco (`storage/app/public/`, `pgdata`);
- Picos de usuários simultâneos (access logs);
- Tempo de resposta e tamanho da fila `media-conversions`.

**Gatilhos para upgrade de hardware:**

| Gatilho | Ação |
|---------|------|
| Disco > 70% | Planejar expansão de volume |
| RAM > 85% sustentado | Elevar para 16 GB |
| p95 > 3 s em horário de pico | Aumentar `pm.max_children` ou vCPUs |
| Fila `media-conversions` > 500 jobs por > 30 min | Escalar worker (2 réplicas ou 1 vCPU dedicada) |
| Média > 15.000 vistorias/ano por 2 trimestres | Recalcular projeção; considerar SIZEM-STORAGE separado |

---

## 17. REFERÊNCIAS

| Documento | Conteúdo |
|-----------|----------|
| [ESPECIFICACAO_TECNOLOGIA_E_INFRAESTRUTURA.md](ESPECIFICACAO_TECNOLOGIA_E_INFRAESTRUTURA.md) | Especificação técnica completa (inclui projeção 5 anos) |
| [UC-007](casos-de-uso/UC-007-upload-offline-fotos.md) | Upload offline de fotografias |
| [ADR-009](adr/ADR-009-eliminar-google-drive-fotos-locais.md) | Armazenamento local de fotos |
| [ADR-006](adr/ADR-006-infraestrutura-docker-canonical.md) | Stack Docker canônica |
| [ADR-008](adr/ADR-008-hardening-cras-paridade-geo.md) | Hardening de segurança |
| `docker-compose.yml` | Definição da stack de produção |

---

*Documento elaborado com base no repositório `poprua-cras` e premissas operacionais informadas (**10 fotos por vistoria**, 100 usuários simultâneos, horizonte 3 anos) — julho/2026.*
