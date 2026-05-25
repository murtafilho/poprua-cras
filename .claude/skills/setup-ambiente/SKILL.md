---
name: setup-ambiente
description: Configurar e diagnosticar o ambiente de desenvolvimento e producao do POPRUA CRAS. Use quando o usuario pedir para verificar, configurar ou corrigir o ambiente.
user-invocable: true
allowed-tools: Read, Grep, Bash, Write, Edit
argument-hint: [diagnosticar|corrigir|backup|tuning]
---

# Skill: Setup do Ambiente POPRUA CRAS

Use esta skill para configurar, diagnosticar e corrigir o ambiente do projeto.

**Importante:** o estado-alvo da infra esta documentado em
`docs/adr/ADR-006-infraestrutura-docker-canonical.md`. Sempre que esta
skill colidir com o ADR, o ADR prevalece.

## Infraestrutura

### Servidor (ambiente unico)

A partir de 2026-05-20 o servidor `vlcp-sufis01` E producao. Nao ha
ambiente separado.

```
SSH host:      ssh sufis (10.0.25.8, user: cassio.martins)
SSH sidecar:   ssh sufis-poprua-cras (porta 2226)
App path:      /var/www/html/joomla_sufis/ginfi/poprua-cras   (canonical)
Compose:       /var/www/html/joomla_sufis/ginfi/poprua-cras/docker-compose.yml
Rebuild:       bash docker/rebuild.sh   (wrapper fino sobre docker compose up -d --build)
```

`/opt/docker/poprua-cras/` continua existindo mas e SECUNDARIO. Hospeda:

| Item                                 | Status |
|--------------------------------------|--------|
| `ssh-data/` (authorized_keys)        | **ATIVO** — bind-mounted no SSH sidecar |
| `claude-data/` (config persistida)   | **ATIVO** — bind-mounted no SSH sidecar |
| `backups/`                           | LEGADO — ver decisao G do ADR-006 (unificacao com `/var/backups/poprua-cras/`) |
| `backup.sh`                          | LEGADO — revisar cron |
| `restore/`                           | Auxiliar de operacao |
| `postgres-data/` (~1.3GB)            | ARQUIVADA (postgres atual = volume named) |
| `*.deprecated-2026-05-20`            | Compose/Dockerfile antigos, mantidos por historico |

### Configuracao do alias `sufis-poprua-cras`

Adicionar ao `~/.ssh/config` (chmod 600):

```ssh-config
Host sufis-poprua-cras
    HostName 10.0.25.8
    Port 2226
    User root
    IdentityFile ~/.ssh/id_ed25519
    StrictHostKeyChecking no
    UserKnownHostsFile /dev/null
    ConnectTimeout 5
```

A chave publica do dev (`~/.ssh/id_ed25519.pub`) precisa estar em
`/opt/docker/poprua-cras/ssh-data/authorized_keys` (bind-mounted no
`/root/.ssh/` do sidecar — sobrevive a restarts). Adicionar:

```bash
sudo tee -a /opt/docker/poprua-cras/ssh-data/authorized_keys < ~/.ssh/id_ed25519.pub
```

Validar: `ssh sufis-poprua-cras "hostname"` deve retornar o hash do sidecar.

**Nota:** o sidecar NAO tem `docker` instalado. Use SSH para acessar
arquivos do bind mount (`/var/www/html/...`, `.env`, logs, SSL via openssl).
Para `docker ps/exec`, rode no host (`ssh sufis "sudo docker ..."` se for
de outra maquina, ou direto se ja estiver em `vlcp-sufis01`).

| Container | Imagem | Porta Host | Funcao |
|-----------|--------|-----------|--------|
| `php84-poprua-cras` | `php84-poprua-cras:local` (base: `serversideup/php:8.4-fpm-nginx`) | `127.0.0.1:9086` -> `9000` | App Laravel 12 |
| `pg17-poprua-cras` | `postgis/postgis:17-3.5` | `127.0.0.1:5434` -> `5432` | PostgreSQL 17 + PostGIS 3.5 |
| `redis-poprua-cras` | `redis:7-alpine` | `127.0.0.1:6380` -> `6379` | Cache, Session, Queue |
| `ssh-poprua-cras` | `ssh-poprua-cras:local` (debian-slim) | `0.0.0.0:2226` -> `22` | Sidecar SSH |
| `queue-poprua-cras` | `php84-poprua-cras:local` | — | Worker Redis (queue:work) |

### Banco de Dados (Producao)

```
Host interno (Docker): db
Host externo: 127.0.0.1:5434
Database: poprua_cras
User: poprua_cras
Password: conforme .env
Dados espaciais: SRID 4326 (WGS84)
```

## Comandos de Diagnostico

### 1. Saude dos containers

```bash
ssh sufis "sudo docker ps --format 'table {{.Names}}\t{{.Status}}\t{{.Ports}}' | grep poprua-cras"
```

### 2. Espaco em disco

```bash
# Volume named do Postgres (dados ativos)
ssh sufis "df -h $(sudo docker volume inspect poprua-cras_pgdata --format '{{.Mountpoint}}' | xargs dirname)"
```

### 3. Tamanho do banco

```bash
ssh sufis "sudo docker exec pg17-poprua-cras psql -U poprua_cras -d poprua_cras -c \"SELECT pg_size_pretty(pg_database_size('poprua_cras'));\""
```

### 4. Redis

```bash
ssh sufis "sudo docker exec redis-poprua-cras redis-cli info memory | grep used_memory_human"
```

### 5. Logs da aplicacao

```bash
ssh sufis-poprua-cras "tail -50 /var/www/html/joomla_sufis/ginfi/poprua-cras/storage/logs/laravel.log"
```

## Acoes de Correcao

### .env de Producao

Garantir:

```env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=error
```

**NUNCA** usar `APP_DEBUG=true` em producao.

### Backup Automatico (cron)

Destino unificado conforme decisao G do ADR-006:

```bash
sudo mkdir -p /var/backups/poprua-cras
echo "0 3 * * * docker exec pg17-poprua-cras pg_dump -U poprua_cras -Fc poprua_cras > /var/backups/poprua-cras/poprua_cras_\$(date +\%Y\%m\%d).dump && find /var/backups/poprua-cras -mtime +14 -delete" | sudo tee /etc/cron.d/backup-poprua-cras
```

### Backup Manual

```bash
# Via artisan (ja faz pg_dump em /var/backups/poprua-cras/)
docker exec php84-poprua-cras php artisan etl:run --confirm --skip-backup=false
# Ou direto
docker exec pg17-poprua-cras pg_dump -U poprua_cras -Fc poprua_cras \
  > /var/backups/poprua-cras/poprua_cras_manual_$(date +%Y%m%d_%H%M).dump
```

### Restaurar Backup

```bash
docker exec -i pg17-poprua-cras pg_restore -U poprua_cras -d poprua_cras --clean --if-exists \
  < /var/backups/poprua-cras/ARQUIVO.dump
```

## Tuning do PostgreSQL

Servidor: 8 CPUs, 8 GB RAM, banco com queries espaciais PostGIS.

| Parametro | Default | Recomendado |
|-----------|---------|-------------|
| `shared_buffers` | 128MB | **2GB** |
| `work_mem` | 4MB | **32MB** |
| `maintenance_work_mem` | 64MB | **512MB** |
| `effective_cache_size` | 4GB | **6GB** |
| `random_page_cost` | 4.0 | **1.1** |
| `wal_buffers` | -1 | **64MB** |

```bash
ssh sufis "sudo docker exec pg17-poprua-cras psql -U poprua_cras -d poprua_cras -c \"
ALTER SYSTEM SET shared_buffers = '2GB';
ALTER SYSTEM SET work_mem = '32MB';
ALTER SYSTEM SET maintenance_work_mem = '512MB';
ALTER SYSTEM SET effective_cache_size = '6GB';
ALTER SYSTEM SET random_page_cost = 1.1;
ALTER SYSTEM SET wal_buffers = '64MB';
ALTER SYSTEM SET checkpoint_completion_target = 0.9;
\""
ssh sufis "sudo docker restart pg17-poprua-cras"
```

## Parametros da Aplicacao (tabela `parametros`)

Configuracao da app (nao do ambiente) que opera SUFIS edita pela UI em
`/admin/parametros`. Lida pelo codigo via `App\Models\Parametro::get($chave, $default)`.

| Chave | Grupo | Tipo | Default | Lido em |
|---|---|---|---|---|
| `app_nome` | geral | string | POPRUA CRAS | layouts, titulos |
| `app_orgao` | geral | string | Prefeitura de Belo Horizonte | layouts, relatorios |
| `info_precaria_dias` | workflow | integer | 60 | `PontoService::infoPrecariaDias()` (status "Informacao Precaria" para pontos sem vistoria ha mais de N dias) |
| `exigir_comunicado` | workflow | boolean | 0 (off) | `Store/UpdateVistoriaRequest::validateComunicadoObrigatorio()` — se ligado, bloqueia agendar `data_prevista_zeladoria` sem `houve_comunicado=Sim` na mesma vistoria |
| `vistorias_por_pagina` | listagem | integer | 5 | listagens paginadas |
| `mapa_centro_lat` / `mapa_centro_lng` / `mapa_zoom_padrao` | mapa | float / float / integer | -19.9135 / -43.9514 / 13 | mapa Leaflet |
| `peso_*` (16 fatores) | complexidade | integer | varia | `Ponto::calcularComplexidade()` — multiplicadores VI-SPDAT |
| `complexidade_critico/alto/medio` | complexidade | integer | 8 / 5 / 3 | thresholds de classificacao de pontos |

### Habilitar `exigir_comunicado`

Quando SUFIS quiser impor que toda zeladoria agendada tenha comunicado previo:

```bash
ssh sufis "sudo docker exec php84-poprua-cras php artisan tinker --execute \"App\\Models\\Parametro::set('exigir_comunicado', '1');\""
```

Ou via UI: `/admin/parametros` → grupo Workflow → editar valor. Validacao
ja esta wired-up nos requests (vide `validateComunicadoObrigatorio`). Default
e `0` (off) para nao quebrar instalacoes existentes sem aviso.

## Checklist de Configuracao

### Producao
- [ ] `APP_ENV=production` e `APP_DEBUG=false`
- [ ] Backup automatico do banco configurado
- [ ] PostgreSQL com tuning adequado
- [ ] Disco com pelo menos 20% livre
- [ ] Apache vhost habilitado (`php84-poprua-cras.conf`)
- [ ] Healthcheck dos 5 containers passando
- [ ] Logs em nivel `error`

### Desenvolvimento
- [ ] Containers rodando (app, db, redis, queue)
- [ ] `.env` com credenciais corretas
- [ ] Migracoes executadas (`php artisan migrate`)
- [ ] Cache limpo (`php artisan config:clear`)
- [ ] Node modules instalados (`npm install`)
- [ ] Assets compilados (`npm run build`)

## Instrucoes para o Agente

Ao receber `$ARGUMENTS`:

- **diagnosticar**: Executar comandos de diagnostico e reportar status
- **corrigir**: Identificar e corrigir problemas (pedir confirmacao antes)
- **backup**: Executar backup manual ou configurar automatico
- **tuning**: Verificar e aplicar tuning do PostgreSQL (pedir confirmacao antes de reiniciar)
- **sem argumento**: Executar diagnostico completo e sugerir correcoes
