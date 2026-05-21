# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Projeto

POPRUA CRAS — sistema derivado do POPRUA Geo, focado na integracao com CRAS (Centro de Referencia de Assistencia Social). Laravel 12 / PHP 8.4 / PostgreSQL 17 + PostGIS 3.5 / Redis / Vite.

Fork inicial baseado em [poprua-geo](https://github.com/murtafilho/poprua-geo) em 2026-05-18. A estrutura de Ponto/Vistoria/Morador foi herdada e sera adaptada para os fluxos especificos do CRAS.

## Comandos (todos via docker exec)

```bash
# Prefixo obrigatorio — o codigo roda no container, nao no host
EXEC="sudo docker exec php84-poprua-cras"

# Migrations
$EXEC php artisan migrate --no-interaction
$EXEC php artisan migrate:status

# Testes (phpunit.xml aponta para DB poprua_cras_test no container)
$EXEC php artisan test
$EXEC php artisan test --filter=NomeDoTeste

# Lint / format
$EXEC vendor/bin/pint --dirty

# Analise estatica (larastan, level 6 com baseline)
$EXEC vendor/bin/phpstan analyse

# Cache
$EXEC php artisan cache:clear
$EXEC php artisan config:clear

# Composer / npm (Node 22 + npm + Python 3 + PCOV + pg_dump ja vem na imagem)
$EXEC composer install --no-interaction
$EXEC npm install && $EXEC npm run build

# Shell interativo (default: www-data; use -u root somente quando precisar de apt/install/chown)
sudo docker exec -it php84-poprua-cras bash
sudo docker exec -it -u root php84-poprua-cras bash   # quando precisar de root
```

## Infraestrutura Docker

Containers na rede `poprua-cras` no host `vlcp-sufis01`:

| Container | Porta Host | Uso |
|-----------|-----------|-----|
| `php84-poprua-cras` | 9086 | PHP-FPM 8.4 (codigo via bind mount) |
| `pg17-poprua-cras` | 5434 | PostgreSQL 17 + PostGIS 3.5 |
| `redis-poprua-cras` | 6380 | Cache/queue |
| `ssh-poprua-cras` | 2226 | Sidecar SSH (acesso via `ssh sufis-poprua-cras`) |
| `queue-poprua-cras` | — | Worker da queue Redis |

O codigo-fonte fica no host em `/var/www/html/joomla_sufis/ginfi/poprua-cras/` e e bind-mounted no container. Editar no host = editar no container.

**Conexao DB local (fora do container):** host=localhost port=5434 db=poprua_cras user=poprua_cras — credenciais no .env

**Acesso SSH:** `ssh sufis-poprua-cras` (alias para porta 2226). Configurar no `~/.ssh/config`:

```
Host sufis-poprua-cras
    HostName 10.0.25.8
    Port 2226
    User root
    IdentityFile ~/.ssh/id_rsa_sufis
    StrictHostKeyChecking no
```

## URL de Producao

```
https://sufis.pbh.gov.br/ginfi/poprua-cras/public
```

## Arquitetura

### Dominio central: Ponto → Vistoria → Morador

Herdado do poprua-geo. Sera revisado conforme requisitos do CRAS.

- **Ponto** — local fisico (endereco). Coordenadas lat/lng + vinculo com `EnderecoAtualizado`.
- **Vistoria** — registro de visita/abordagem. 16 flags booleanas de complexidade, ate 6 encaminhamentos, fotos via Spatie MediaLibrary, soft deletes.
- **Morador** — pessoa identificada. Tracking de movimentacao via `MoradorHistorico`.

### Dados geoespaciais (PostGIS)

| Tabela | Geometria |
|--------|-----------|
| `pontos` | POINT |
| `endereco_atualizados` | POINT |
| `geo_bairros` | MULTIPOLYGON |
| `geo_regionais` | MULTIPOLYGON |
| `geo_limite_municipio` | GEOMETRY |

Todas as geometrias usam SRID 4326 (WGS84) com indice GIST.

### Stack

| Componente | Tecnologia |
|---|---|
| Framework | Laravel 12 (PHP 8.4) |
| Auth | Laravel Breeze |
| Frontend | Vite + Blade + Leaflet + Alpine |
| PWA | Service Worker + manifest |
| Geo | PostGIS para queries espaciais |
| Permissoes | Spatie laravel-permission |
| Media | Spatie MediaLibrary (queue media-conversions) |

## Convencoes

- Todos os comandos artisan/composer/npm rodam dentro do container via `docker exec`
- Usar `--no-interaction` em todos os comandos artisan
- Usar Form Request classes para validacao
- Usar `Model::query()` em vez de `DB::` (exceto queries otimizadas)
- Eager loading para evitar N+1
- PHP 8 constructor property promotion, return types explicitos, curly braces obrigatorios
- Rodar `vendor/bin/pint --dirty` antes de finalizar

## Documentacao

| Documento | Descricao |
|-----------|-----------|
| [docs/ARQUITETURA_DOCKER.md](docs/ARQUITETURA_DOCKER.md) | Arquitetura Docker, fluxo de requisicoes, rede, operacoes |
| [docs/API.md](docs/API.md) | Referencia completa da API REST |
