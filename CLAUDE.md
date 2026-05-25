# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Projeto

POPRUA CRAS — sistema derivado do POPRUA Geo, focado na integracao com CRAS (Centro de Referencia de Assistencia Social). Laravel 12 / PHP 8.4 / PostgreSQL 17 + PostGIS 3.5 / Redis / Vite.

Fork inicial baseado em [poprua-geo](https://github.com/murtafilho/poprua-geo) em 2026-05-18. A estrutura de Ponto/Vistoria/Morador foi herdada e sera adaptada para os fluxos especificos do CRAS.

## Ambientes

### Producao (Docker em vlcp-sufis01)

Comandos via `docker exec` — ver skills `setup-ambiente` e `docker/rebuild.sh`.

```bash
EXEC="sudo docker exec php84-poprua-cras"
$EXEC php artisan migrate --no-interaction
$EXEC php artisan test
$EXEC vendor/bin/pint --dirty
$EXEC vendor/bin/phpstan analyse
```

### Desenvolvimento local (ambiente preferido para dev/refatoracao)

O ambiente local usa PHP 8.4, PostgreSQL 16 + PostGIS 3.4, Redis 7 e Node 22 instalados nativamente. O `.env` aponta para localhost. Todos os comandos rodam direto, sem `docker exec`.

```bash
# Migrations
php artisan migrate

# Testes
php artisan test
php artisan test --filter=NomeDoTeste

# Lint / format
vendor/bin/pint --dirty

# Analise estatica (larastan, level 6 com baseline)
vendor/bin/phpstan analyse

# Cache
php artisan cache:clear
php artisan config:clear

# Dependencias
composer install
npm install && npm run build

# Servidor de desenvolvimento
php artisan serve --port=8088
```

**Conexao DB local:** host=127.0.0.1 port=5432 db=poprua_cras user=poprua_cras password=poprua_cras
**Banco de teste:** poprua_cras_test (mesmo host, mesma senha)

## Acesso ao Sistema (dev)

**URL:** http://localhost:8088 (servidor via `php artisan serve --port=8088`)
**Login:** murtafilho@gmail.com / xman74102
**Role:** admin (pode editar qualquer vistoria, acessar parametrizacao)

Para screenshots e testes E2E via Playwright, as paginas autenticadas redirecionam ao login. Usar `npx playwright screenshot` para capturar paginas publicas (login). Para paginas autenticadas, usar o browser MCP do Playwright com login via formulario ou testar via `php artisan test`.

**Rotas principais:**
- `/vistorias` — listagem de zeladorias (cards com workflow)
- `/minhas-vistorias` — zeladorias do usuario logado (reutiliza mesma view)
- `/pontos` — listagem de pontos
- `/mapa` — mapa georreferenciado (restrito a BH)
- `/moradores` — gestao de pessoas em situacao de rua
- `/admin/parametros` — parametrizacao do sistema (admin)

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

- Em desenvolvimento local, comandos rodam direto (sem `docker exec`); em producao, via `docker exec`
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
