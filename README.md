# SIZEM — Sistema Integrado de Zeladoria Municipal

Sistema de gestão geoespacial de vistorias e zeladoria urbana, com foco em fluxos de CRAS (Centro de Referência de Assistência Social) e monitoramento territorial de população em situação de rua.

> Fork do [poprua-geo](https://github.com/murtafilho/poprua-geo) — herda Ponto / Vistoria / Morador + PostGIS + PWA, adaptado para zeladoria e assistência.

**Licença:** [MIT](LICENSE) · Copyright © 2025–2026 Roberto Luciano

## Stack

| Componente | Versão |
|------------|--------|
| PHP | 8.4 |
| Laravel | 12 |
| PostgreSQL | 17 + PostGIS 3.5 |
| Redis | Cache e filas |
| Leaflet.js | Mapa interativo |
| Alpine.js | JavaScript reativo |
| Vite | Build de assets |
| Capacitor | App Android (pasta `mobile/`) |

## Funcionalidades

- Mapa de pontos de concentração (PostGIS)
- Vistorias / zeladorias em campo (web + PWA offline)
- Cadastro de moradores e anexos fotográficos
- Relatórios e gestão com RBAC (Spatie Permission)
- App mobile Capacitor (sincronização / outbox)

## Primeiros passos

O projeto roda em containers Docker. Comandos `artisan` / `composer` / `npm` devem rodar dentro do container.

```bash
# Prefixo para execução no container
EXEC="sudo docker exec -u root php84-poprua-cras"

# 1. Clonar e configurar
git clone https://github.com/murtafilho/poprua-cras.git
cd poprua-cras
cp .env.example .env
# Editar .env: DB_PASSWORD, etc.

# 2. Subir containers
docker compose up -d

# 3. Dependências e migrate
$EXEC composer install --no-interaction
$EXEC npm install && $EXEC npm run build
$EXEC php artisan key:generate --no-interaction
$EXEC php artisan migrate --no-interaction

# 4. Usuário admin
$EXEC php artisan tinker --execute="
    \$u = \App\Models\User::create(['name'=>'Admin','email'=>'admin@example.com','password'=>bcrypt('password')]);
    \$r = \Spatie\Permission\Models\Role::firstOrCreate(['name'=>'admin']);
    \$u->assignRole(\$r);
"
```

Acesse `http://localhost:9086`.

## Containers (dev)

| Container | Porta host | Uso |
|-----------|------------|-----|
| `php84-poprua-cras` | 9086 | PHP-FPM 8.4 |
| `pg17-poprua-cras` | 5434 | PostgreSQL 17 + PostGIS |
| `redis-poprua-cras` | 6380 | Cache / queue |
| `queue-poprua-cras` | — | Worker Redis |

## Comandos úteis

```bash
EXEC="sudo docker exec -u root php84-poprua-cras"

$EXEC php artisan test
$EXEC vendor/bin/pint --dirty
$EXEC vendor/bin/phpstan analyse
```

## Contribuindo

Issues e PRs são bem-vindos. Antes de abrir PR:

1. Rode os testes (`php artisan test`)
2. Mantenha o estilo com Pint
3. Não versionar `.env`, dumps SQL nem mídia de produção

## Licença

Distribuído sob a licença **MIT**. Veja [LICENSE](LICENSE).
