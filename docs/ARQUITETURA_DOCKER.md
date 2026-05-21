# Arquitetura Docker — Aplicacao PopRua Geo

> Como o Docker funciona para servir a aplicacao PopRua Geo no servidor sufis

---

## O que e o PopRua Geo?

Sistema de **georreferenciamento e gestao de populacao em situacao de rua** da SUFIS/PBH. Permite mapear pontos de concentracao, registrar vistorias, gerenciar moradores e gerar relatorios com dados geoespaciais.

Funcionalidades principais:
- **Mapa interativo** — visualizacao de pontos georreferenciados com clustering
- **Pontos** — locais de concentracao de populacao de rua
- **Vistorias** — registro de vistorias com fotos (upload offline-first)
- **Moradores** — cadastro, historico, entrada/saida/transferencia entre pontos
- **Geocodificacao** — busca de enderecos e coordenadas via API
- **Dados GIS** — bairros, regionais e limite do municipio (PostGIS)
- **Admin** — gestao de usuarios, roles e permissoes (Spatie)
- **Power BI** — dashboard publico integrado
- **Google Drive** — integracao para upload de arquivos

---

## O que acontece quando um usuario acessa o PopRua Geo?

```
1. Usuario digita no navegador:
   https://sufis.pbh.gov.br/ginfi/poprua-geo/public/login

2. A requisicao chega no Apache (rodando no host Debian):
   Apache ve que o path comeca com /ginfi/poprua-geo/public/
   e consulta a regra configurada em php84-poprua-geo.conf

3. Apache encaminha o PHP para o container via FastCGI:
   proxy:fcgi://127.0.0.1:9084
   (porta 9084 do loopback -> container php84-poprua-geo)

4. Dentro do container, o PHP-FPM recebe a requisicao:
   Processa o arquivo index.php (bind mount do host)

5. O Laravel conecta nos servicos da stack:
   - PostgreSQL+PostGIS (container db) -> dados e geometrias
   - Redis (container redis) -> sessoes, cache e filas

6. A resposta HTML volta pelo caminho inverso:
   PHP-FPM -> Apache -> navegador do usuario
```

Visualmente:

```
  Navegador
      |
      | HTTPS (porta 443)
      v
+---------------------------+
|  Apache 2.4 (host)       |
|  sufis.pbh.gov.br        |
|  /ginfi/poprua-geo/public |
|       |                   |
|       | fcgi://127.0.0.1:9084
|       v                   |
+---------------------------+
      |
+===============================+
|  Rede Docker: poprua-geo      |
|                                |
|  +-------------------------+   |
|  | php84-poprua-geo        |   |
|  | PHP 8.4 + Nginx         |   |
|  | (serversideup)          |   |
|  | Porta 9000 -> 9084      |   |
|  | User: www-data          |   |
|  | Mem: 512M               |   |
|  +-------------------------+   |
|       |             |          |
|       v             v          |
|  +---------+  +---------+     |
|  | pg17    |  | redis   |     |
|  | PostGIS |  | 7-alpine|     |
|  | :5432   |  | :6379   |     |
|  | 512M    |  | 128M    |     |
|  +---------+  +---------+     |
|       |                        |
|  postgres-data/                |
|  (persistente)                 |
+===============================+
```

**Diferenca do efantini**: o PopRua Geo tem uma **stack completa** com 3 containers (app + banco + cache) em rede isolada. O efantini usa banco externo.

---

## Onde fica cada coisa

### No host (servidor Debian)

| O que | Onde | Para que |
|---|---|---|
| Codigo-fonte | `/var/www/html/joomla_sufis/ginfi/poprua-geo/` | Arquivos PHP, views, config |
| Dockerfile | `/opt/docker/poprua-geo/Dockerfile` (host) / `docker/Dockerfile` (repo) | Receita para construir a imagem |
| docker-compose.yml | `/opt/docker/poprua-geo/docker-compose.yml` | Definicao da stack (3 containers) |
| .env (compose) | `/opt/docker/poprua-geo/.env` | Senha do PostgreSQL |
| Dados PostgreSQL | `/opt/docker/poprua-geo/postgres-data/` | Volume persistente do banco |
| Config Apache | `/etc/apache2/conf-enabled/php84-poprua-geo.conf` | Regra de proxy para o container |

### Dentro do container app (php84-poprua-geo)

| O que | Onde | Para que |
|---|---|---|
| PHP-FPM + Nginx | Imagem serversideup (Debian 13 trixie) | Processa requisicoes PHP |
| PHP | 8.4.19 | Runtime |
| Nginx | 1.26.3 | Servidor web interno |
| Extensoes PHP | ctype, curl, dom, exif, fileinfo, gd, json, mbstring, openssl, pdo, pdo_mysql, pdo_pgsql, pdo_sqlite, redis, sodium, xml, xmlreader, xmlwriter, zip, opcache | Funcionalidades da app |
| Composer | 2.9.5 (`/usr/local/bin/composer`) | Gerenciador de dependencias |
| Node.js | 22.22.1 | Runtime JS (build frontend) |
| npm | 10.9.4 | Gerenciador de pacotes JS |
| GitHub CLI | 2.88.1 | Operacoes Git/GitHub |
| Codigo da app | `/var/www/html/.../poprua-geo/` | Bind mount do host |

> **Importante**: o container app roda como **www-data** (nao-root) por padrao da imagem serversideup.

---

## Os 3 containers da stack

### 1. App (php84-poprua-geo) — PHP 8.4 + Nginx

```dockerfile
FROM serversideup/php:8.4-fpm-nginx

USER root

# Extensoes PHP
RUN docker-php-ext-install exif

# GitHub CLI
RUN curl -fsSL https://cli.github.com/packages/githubcli-archive-keyring.gpg \
        -o /usr/share/keyrings/githubcli-archive-keyring.gpg \
    && chmod go+r /usr/share/keyrings/githubcli-archive-keyring.gpg \
    && echo "deb [arch=$(dpkg --print-architecture) signed-by=/usr/share/keyrings/githubcli-archive-keyring.gpg] https://cli.github.com/packages stable main" \
        > /etc/apt/sources.list.d/github-cli.list \
    && apt-get update -qq \
    && apt-get install -y --no-install-recommends gh \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Claude Code (AI assistant CLI) - instalacao nativa recomendada
# 1. Remove instalacao npm se existir (evita conflito)
# 2. Instala via script nativo em ~/.local/bin/claude
# 3. Adiciona ao PATH permanentemente
RUN npm uninstall -g @anthropic-ai/claude-code 2>/dev/null || true \
    && curl -fsSL https://claude.ai/install.sh | bash \
    && echo 'export PATH="$HOME/.local/bin:$PATH"' >> /root/.bashrc

ENV PATH="/root/.local/bin:${PATH}"

USER www-data
```

O Dockerfile tambem esta versionado em `docker/Dockerfile` no repositorio.

**Particularidade**: usa a imagem `serversideup/php` que inclui **Nginx + PHP-FPM no mesmo container**. Diferente dos outros containers que usam apenas FPM.

Extensoes PHP disponiveis: ctype, curl, dom, exif, fileinfo, gd, json, mbstring, openssl, pdo, pdo_mysql, pdo_pgsql, pdo_sqlite, redis, sodium, xml, xmlreader, xmlwriter, zip, opcache.

### 2. Banco (pg17-poprua-geo) — PostgreSQL 17 + PostGIS

```yaml
db:
  image: postgis/postgis:17-3.5
  container_name: pg17-poprua-geo
  ports:
    - "127.0.0.1:5433:5432"     # acessivel do host na porta 5433
  volumes:
    - /opt/docker/poprua-geo/postgres-data:/var/lib/postgresql/data
  environment:
    POSTGRES_DB: poprua_geo
    POSTGRES_USER: poprua
    POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}   # vem do .env
  healthcheck:
    test: pg_isready -U poprua -d poprua_geo
```

**PostGIS** e a extensao espacial que permite armazenar e consultar geometrias (pontos, poligonos de bairros, regionais, limite do municipio).

**Dados persistentes**: ficam em `/opt/docker/poprua-geo/postgres-data/` no host. Se o container for recriado, os dados permanecem.

### 3. Cache (redis-poprua-geo) — Redis 7

```yaml
redis:
  image: redis:7-alpine
  container_name: redis-poprua-geo
  ports:
    - "127.0.0.1:6379:6379"
  healthcheck:
    test: redis-cli ping
```

Usado para:
- **Sessoes** (`SESSION_DRIVER=redis`)
- **Cache** (`CACHE_STORE=redis`)
- **Filas** (`QUEUE_CONNECTION=redis`)

---

## Rede isolada

Os 3 containers estao na rede `poprua-geo` (bridge):

```
+----------------------------------+
|  Rede: poprua-geo (bridge)       |
|                                  |
|  app  <----->  db                |
|   |              nome: "db"      |
|   |              porta: 5432     |
|   |                              |
|   +--------->  redis             |
|                nome: "redis"     |
|                porta: 6379       |
+----------------------------------+
```

Dentro da rede, os containers se encontram pelo **nome do servico**:
- `DB_HOST=db` (nao precisa de IP)
- `REDIS_HOST=redis` (nao precisa de IP)

Nenhum outro container do servidor enxerga essa rede.

---

## docker-compose.yml completo comentado

```yaml
services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    image: php84-poprua-geo:local
    container_name: php84-poprua-geo
    restart: unless-stopped
    ports:
      - "127.0.0.1:9084:9000"       # Apache aponta para ca
      - "127.0.0.1:2224:22"         # SSH direto no container
    volumes:
      - /var/www/html/.../poprua-geo:/var/www/html/.../poprua-geo
                                      # bind mount (so esta app)
      - /opt/docker/poprua-geo/claude-data:/root/.claude
                                      # persistir config/historico do Claude Code entre rebuilds
      - /opt/docker/poprua-geo/ssh-data:/root/.ssh
                                      # persistir chaves SSH entre rebuilds
    environment:
      PHP_MEMORY_LIMIT: 256M
      PHP_MAX_EXECUTION_TIME: 300    # scripts longos (upload fotos)
      PHP_UPLOAD_MAX_FILE_SIZE: 64M  # fotos de vistorias
      PHP_POST_MAX_SIZE: 64M
    depends_on:
      db: { condition: service_healthy }
      redis: { condition: service_healthy }
                                      # so inicia apos db e redis estarem prontos
    deploy:
      resources:
        limits:
          memory: 512M
          cpus: '1.0'
    networks: [poprua-geo]

  db:
    image: postgis/postgis:17-3.5    # PostgreSQL 17 com extensao espacial
    container_name: pg17-poprua-geo
    restart: unless-stopped
    ports:
      - "127.0.0.1:5433:5432"       # acesso do host (ferramentas, debug)
    volumes:
      - /opt/docker/poprua-geo/postgres-data:/var/lib/postgresql/data
                                      # dados persistentes no host
    environment:
      POSTGRES_DB: poprua_geo
      POSTGRES_USER: poprua
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U poprua -d poprua_geo"]
      interval: 10s
      timeout: 5s
      retries: 5
    deploy:
      resources:
        limits:
          memory: 512M
    networks: [poprua-geo]

  redis:
    image: redis:7-alpine
    container_name: redis-poprua-geo
    restart: unless-stopped
    ports:
      - "127.0.0.1:6379:6379"
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
    deploy:
      resources:
        limits:
          memory: 128M
    networks: [poprua-geo]

networks:
  poprua-geo:
    driver: bridge                   # rede privada entre os 3 containers
```

---

## Config Apache (proxy reverso)

```apache
# /etc/apache2/conf-enabled/php84-poprua-geo.conf

<Directory "/var/www/html/joomla_sufis/ginfi/poprua-geo/public">
    <FilesMatch \.php$>
        SetHandler "proxy:fcgi://127.0.0.1:9084"
    </FilesMatch>
    RequestHeader set X-Forwarded-Proto "https"
    AllowOverride All
    Require all granted
</Directory>
```

---

## Tecnologias utilizadas

### Backend

| Tecnologia | Versao | Uso |
|---|---|---|
| PHP | 8.4 | Runtime |
| Laravel | 12.x | Framework web |
| PostgreSQL | 17 | Banco de dados relacional |
| PostGIS | 3.5 | Extensao espacial (geometrias, coordenadas) |
| Redis | 7 | Cache, sessoes e filas |
| Spatie Permission | 6.x | Roles e permissoes |
| Spatie MediaLibrary | 11.x | Upload e gestao de fotos |
| Laravel Sanctum | 4.x | Autenticacao API (tokens) |
| Laravel Breeze | 2.x | Autenticacao web (login/registro) |
| Google API Client | 2.x | Integracao Google Drive |
| proj4php | 2.x | Conversao de projecoes cartograficas |

### Frontend

| Tecnologia | Versao | Uso |
|---|---|---|
| Alpine.js | 3.x | Reatividade leve no HTML |
| Leaflet | 1.9 | Mapas interativos |
| Leaflet MarkerCluster | 1.5 | Agrupamento de marcadores no mapa |
| Axios | 1.x | Requisicoes HTTP |
| Vite | 7.x | Bundler frontend |
| Laravel Vite Plugin | 2.x | Integracao Laravel + Vite |

### Infraestrutura

| Item | Valor |
|---|---|
| Container app | `php84-poprua-geo` (serversideup/php:8.4-fpm-nginx) |
| Container banco | `pg17-poprua-geo` (postgis/postgis:17-3.5) |
| Container cache | `redis-poprua-geo` (redis:7-alpine) |
| Rede Docker | `poprua-geo` (bridge isolada) |
| Servidor | `vlcp-sufis01` (Debian 9.13, 8GB RAM, 8 CPUs) |
| Apache | Proxy fcgi -> `127.0.0.1:9084` |

---

## Modulos da aplicacao

| Modulo | Rotas | Descricao |
|---|---|---|
| **Mapa** | `/mapa` | Visualizacao geoespacial com Leaflet + MarkerCluster |
| **Pontos** | `/pontos`, `/pontos/{id}` | Locais de concentracao, georreferenciamento |
| **Vistorias** | `/vistorias` (CRUD completo) | Registro com fotos, relatorios |
| **Moradores** | `/moradores` (resource) | Cadastro, historico, entrada/saida/transferencia |
| **Admin** | `/admin/users`, `/admin/roles`, `/admin/permissions` | Gestao de acesso |
| **API** | `/api/pontos`, `/api/geo/*`, `/api/moradores/*` | Endpoints JSON para o mapa e mobile |
| **Power BI** | `/powerbi` (publico) | Dashboard integrado |

---

## Operacoes do dia a dia

### Ver status dos 3 containers

```bash
ssh sufis
sudo docker ps | grep poprua-geo
# php84-poprua-geo   Up 2 hours (healthy)   127.0.0.1:9084->9000
# redis-poprua-geo   Up 2 hours (healthy)   127.0.0.1:6379->6379
# pg17-poprua-geo    Up 2 hours (healthy)   127.0.0.1:5433->5432
```

### Ver logs

```bash
# Logs do PHP-FPM/Nginx
sudo docker logs php84-poprua-geo --tail=50 -f

# Logs do PostgreSQL
sudo docker logs pg17-poprua-geo --tail=50 -f

# Logs do Laravel
tail -f /var/www/html/joomla_sufis/ginfi/poprua-geo/storage/logs/laravel.log
```

### Entrar no container da app via SSH

```bash
# Acesso direto via SSH (requer porta 2224 no docker-compose.yml)
ssh -p 2224 root@127.0.0.1

# Ou configurar alias no ~/.ssh/config do host:
# Host poprua
#     HostName 127.0.0.1
#     Port 2224
#     User root
# Depois basta: ssh poprua
```

### Entrar nos containers via docker exec

```bash
# Container da app
sudo docker exec -it php84-poprua-geo bash
php artisan route:list
php artisan migrate:status

# Container do banco
sudo docker exec -it pg17-poprua-geo psql -U poprua -d poprua_geo
\dt         -- listar tabelas
\d pontos   -- descrever tabela pontos

# Container do Redis
sudo docker exec -it redis-poprua-geo redis-cli
KEYS *      -- ver chaves armazenadas
INFO memory -- ver uso de memoria
```

### Reiniciar a stack inteira

```bash
cd /opt/docker/poprua-geo
sudo docker compose restart
```

### Reiniciar apenas um servico

```bash
sudo docker restart php84-poprua-geo   # so a app
sudo docker restart pg17-poprua-geo    # so o banco
sudo docker restart redis-poprua-geo   # so o cache
```

### Rebuild da imagem

```bash
cd /opt/docker/poprua-geo
sudo docker compose up -d --build
# Reconstroi apenas o container app (banco e redis nao mudam)
```

### Ver uso de recursos

```bash
sudo docker stats php84-poprua-geo pg17-poprua-geo redis-poprua-geo --no-stream
# NAME              MEM USAGE / LIMIT    MEM %
# php84-poprua-geo  32MiB / 512MiB       6.24%
# pg17-poprua-geo   24MiB / 512MiB       4.63%
# redis-poprua-geo  3.4MiB / 128MiB      2.64%
```

### Acessar banco via host

```bash
# Conectar no PostgreSQL de fora do container
psql -h 127.0.0.1 -p 5433 -U poprua -d poprua_geo
```

### Backup do banco

```bash
sudo docker exec pg17-poprua-geo pg_dump -U poprua poprua_geo > backup_poprua_geo.sql
```

---

## Persistencia de dados no rebuild

Ao reconstruir o container app (`docker compose up -d --build`), tudo que esta **dentro do container** e perdido. Os volumes garantem que dados importantes sobrevivam:

| Dado | Volume | Sobrevive rebuild? |
|---|---|---|
| Codigo-fonte PHP | bind mount do host | Sim |
| Banco PostgreSQL | `postgres-data/` no host | Sim |
| Skills do Claude Code (`.claude/skills/`) | Dentro do repo (bind mount) | Sim |
| Config/historico do Claude Code (`/root/.claude/`) | `claude-data/` no host | **Sim (requer volume)** |
| Chaves SSH (`/root/.ssh/`) | `ssh-data/` no host | **Sim (requer volume)** |
| Pacotes Composer (`vendor/`) | Dentro do container | Nao — rodar `composer install` apos rebuild |
| Pacotes Node (`node_modules/`) | Dentro do container | Nao — rodar `npm install` apos rebuild |

> **IMPORTANTE**: Os volumes `claude-data` e `ssh-data` devem ser adicionados ao `docker-compose.yml` do host
> para que configuracoes, memorias, historico do Claude Code e chaves SSH nao sejam perdidos em rebuilds.
> Procedimento no host (executar ANTES do rebuild):
> ```bash
> ssh sufis
> cd /opt/docker/poprua-geo
> # criar diretorios para os volumes
> sudo mkdir -p claude-data ssh-data
> # copiar dados atuais do container antes do rebuild
> sudo docker cp php84-poprua-geo:/root/.claude/. claude-data/
> sudo docker cp php84-poprua-geo:/root/.ssh/. ssh-data/
> sudo chmod 700 ssh-data
> sudo chmod 600 ssh-data/id_rsa
> # adicionar ao docker-compose.yml no servico app.volumes:
> #   - /opt/docker/poprua-geo/claude-data:/root/.claude
> #   - /opt/docker/poprua-geo/ssh-data:/root/.ssh
> # recriar o container
> sudo docker compose up -d
> ```

---

## Quando preciso fazer rebuild?

| Situacao | Precisa rebuild? | Comando |
|---|---|---|
| Alterei codigo PHP | Nao | Nada (bind mount) |
| Alterei .env da app | Nao | `sudo docker restart php84-poprua-geo` |
| composer install | Nao | `sudo docker exec php84-poprua-geo composer install` |
| Nova extensao PHP | **Sim** | Editar Dockerfile + `docker compose up -d --build` |
| Mudei porta/memoria | Nao | `docker compose up -d` (recria container) |
| Mudei config Apache | Nao | `sudo systemctl reload apache2` |
| Atualizei imagem PostGIS | **Sim** | `docker compose pull db && docker compose up -d` |
| Perdi dados do banco | Restaurar backup | `psql < backup_poprua_geo.sql` |
| Rebuild feito | Reinstalar deps | `docker exec php84-poprua-geo composer install` |

---

## Diagrama completo da infraestrutura

```
Internet
    |
    v
+=====================================================+
|  Servidor vlcp-sufis01 (Debian 9.13)                |
|                                                     |
|  Apache 2.4                                         |
|  +-----------------------------------------------+  |
|  | /ginfi/poprua-geo/public/* -> :9084 (fcgi)    |  |
|  +-----------------------------------------------+  |
|       |                                             |
|       v                                             |
|  +===============================================+  |
|  |  Rede Docker: poprua-geo (bridge isolada)     |  |
|  |                                               |  |
|  |  +-----------------+                          |  |
|  |  | php84-poprua-geo|                          |  |
|  |  | PHP 8.4 + Nginx |                          |  |
|  |  | www-data        |                          |  |
|  |  | 512M RAM        |                          |  |
|  |  +--------+--------+                          |  |
|  |           |       |                           |  |
|  |           v       v                           |  |
|  |  +--------+  +--------+                       |  |
|  |  |pg17    |  |redis   |                       |  |
|  |  |PostGIS |  |7-alpine|                       |  |
|  |  |512M RAM|  |128M RAM|                       |  |
|  |  |:5433   |  |:6379   |                       |  |
|  |  +--------+  +--------+                       |  |
|  |      |                                        |  |
|  |  postgres-data/ (persistente no host)         |  |
|  +===============================================+  |
+=====================================================+
```

Todos os 3 containers estao isolados na rede `poprua-geo`. Comunicacao externa apenas via portas mapeadas em `127.0.0.1`. O banco de dados e o Redis **nao sao acessiveis pela internet** — somente pelo host e pelo container da app.
