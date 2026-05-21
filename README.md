# PopRua CRAS

Sistema de integracao CRAS (Centro de Referencia de Assistencia Social) com gestao geoespacial de pontos de atendimento.

> Fork do [poprua-geo](https://github.com/murtafilho/poprua-geo) — herda Ponto/Vistoria/Morador + PostGIS + PWA, e sera adaptado para os fluxos especificos do CRAS.

## Stack

| Componente | Versao |
|------------|--------|
| PHP | 8.4 |
| Laravel | 12 |
| PostgreSQL | 17 + PostGIS 3.5 |
| Redis | Cache e filas |
| Leaflet.js | Mapa interativo |
| Alpine.js | JavaScript reativo |
| Vite | Build de assets |

## Primeiros passos

O projeto roda em containers Docker. Todos os comandos artisan/composer/npm devem ser executados dentro do container.

```bash
# Prefixo para execucao no container
EXEC="sudo docker exec -u root php84-poprua-cras"

# 1. Clonar e configurar
git clone <repo-url> && cd poprua-cras
cp .env.example .env
# Editar .env: DB_PASSWORD, APP_KEY (depois), credenciais opcionais (Google Drive, R2)

# 2. Subir containers
docker compose up -d

# 3. Instalar dependencias e migrar
$EXEC composer install --no-interaction
$EXEC npm install && $EXEC npm run build
$EXEC php artisan key:generate --no-interaction
$EXEC php artisan migrate --no-interaction

# 4. Criar usuario admin
$EXEC php artisan tinker --execute="
    \$u = \App\Models\User::create(['name'=>'Admin','email'=>'admin@example.com','password'=>bcrypt('password')]);
    \$r = \Spatie\Permission\Models\Role::firstOrCreate(['name'=>'admin']);
    \$u->assignRole(\$r);
"
```

Acesse `http://localhost:9086`.

## Containers

| Container | Porta Host | Uso |
|-----------|-----------|-----|
| `php84-poprua-cras` | 9086 | PHP-FPM 8.4 (codigo via bind mount) |
| `pg17-poprua-cras` | 5434 | PostgreSQL 17 + PostGIS 3.5 |
| `redis-poprua-cras` | 6380 | Cache/queue |
| `ssh-poprua-cras` | 2226 | Sidecar SSH para acesso remoto |
| `queue-poprua-cras` | — | Worker Redis |

## Producao

URL: `https://sufis.pbh.gov.br/ginfi/poprua-cras/public`

Deploy no `vlcp-sufis01`:

```bash
# No host
sudo mkdir -p /var/www/html/joomla_sufis/ginfi/poprua-cras
cd /var/www/html/joomla_sufis/ginfi
sudo git clone https://github.com/murtafilho/poprua-cras.git poprua-cras
sudo chown -R www-data:www-data poprua-cras

sudo bash poprua-cras/docker/rebuild.sh
```

O script `rebuild.sh` gera o `docker-compose.yml` em `/opt/docker/poprua-cras/` com bind mounts absolutos e portas finais.

## Comandos uteis

```bash
EXEC="sudo docker exec -u root php84-poprua-cras"

$EXEC php artisan test                        # Testes
$EXEC php artisan test --filter=NomeDoTeste   # Teste especifico
$EXEC vendor/bin/pint --dirty                 # Lint/format
$EXEC vendor/bin/phpstan analyse              # Analise estatica (level 6)
$EXEC php artisan migrate --no-interaction    # Rodar migrations
```

## Licenca

Projeto proprietario — Prefeitura de Belo Horizonte
