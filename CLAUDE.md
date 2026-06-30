# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Projeto

POPRUA CRAS — sistema derivado do POPRUA Geo, focado na integração com o CRAS (Centro de Referência de Assistência Social). Laravel 12 / PHP 8.4 / PostgreSQL 17 + PostGIS 3.5 / Redis / Vite.

Fork inicial baseado em [poprua-geo](https://github.com/murtafilho/poprua-geo) em 2026-05-18. A estrutura de Ponto/Vistoria/Morador foi herdada e está sendo adaptada para os fluxos específicos do CRAS.

## Ambientes

### Desenvolvimento local (ambiente preferido para dev/refatoração)

PHP 8.4, PostgreSQL 18.4 + PostGIS 3.6, Redis 7 e Node 22 instalados nativamente. O `.env` aponta para localhost; todos os comandos rodam direto, sem `docker exec`.

> O PG18 roda no cluster `18/main` na **porta 5433** (criado side-by-side; o cluster `16/main` na 5432 segue ativo para outros projetos da máquina). Por isso `DB_PORT=5433` no `.env` e no `phpunit.xml`.

```bash
php artisan migrate
php artisan test
php artisan test --filter=NomeDoTeste   # um único teste
vendor/bin/pint --dirty                  # lint/format (rodar antes de finalizar)
vendor/bin/phpstan analyse               # larastan level 6 com baseline
php artisan cache:clear && php artisan config:clear
composer install
npm install && npm run build
php artisan serve --port=8088
```

**DB local:** host=127.0.0.1 port=5433 db=poprua_cras user=poprua_cras password=poprua_cras
**DB de teste:** poprua_cras_test (mesmo host/senha — configurado em `phpunit.xml`, sobrescreve o `.env`)

### Produção (Docker em vlcp-sufis01)

Os mesmos comandos rodam via `docker exec` (ver skill `setup-ambiente` e `docker/rebuild.sh`):

```bash
EXEC="sudo docker exec php84-poprua-cras"
$EXEC php artisan migrate --no-interaction
$EXEC php artisan test
$EXEC vendor/bin/pint --dirty
$EXEC vendor/bin/phpstan analyse
```

URL de produção: `https://sufis.pbh.gov.br/ginfi/poprua-cras/public`

## Acesso ao Sistema (dev)

**URL:** http://localhost:8088 · **Login:** murtafilho@gmail.com / xman74102 · **Role:** admin

Páginas autenticadas redirecionam ao login. Para screenshots/E2E, usar `npx playwright screenshot` apenas em páginas públicas (login); para páginas autenticadas, usar o Playwright MCP com login via formulário, ou testar via `php artisan test`.

**Rotas principais:** `/mapa` (home, restrito a BH) · `/vistorias` e `/minhas-vistorias` (cards com workflow) · `/pontos` · `/moradores` · `/admin/parametros` (admin).

## Arquitetura

### Domínio central: Ponto → Vistoria → Morador

Herdado do poprua-geo, sendo revisado conforme requisitos do CRAS.

- **Ponto** — local físico (endereço). Coordenadas lat/lng + vínculo com `EnderecoAtualizado`. `PontoObserver` reage a mudanças.
- **Vistoria** — registro de visita/abordagem. ~30 flags booleanas de complexidade, até 6 encaminhamentos (`e1_id`..`e6_id`), fotos via Spatie MediaLibrary, soft deletes, activity log.
- **Morador** — pessoa identificada. Tracking de movimentação via `MoradorHistorico` (entrada/saída/transferência entre pontos). Dados PII — rotas sempre autenticadas.

### Camada de serviços (padrão central)

Controllers são finos: injetam Services no construtor e delegam a lógica. **A lógica de negócio e as queries vivem em `app/Services/`, não nos controllers.** Um service por agregado: `VistoriaService`, `PontoService`, `MoradorService`, `EnderecoService`, `GeoService`, `FotoService`, `DashboardService`, `ProfileService`. Ao adicionar comportamento, estenda o service correspondente em vez de inflar o controller.

### Ciclo de vida da Vistoria (máquina de estados)

Estados: **aberto → finalizado → cancelado** (ver ADR-001). Regras de transição vivem em `VistoriaPolicy`, **não** inline nos controllers:

- Dono edita/finaliza/cancela a vistoria *aberta*.
- Admin reativa vistoria *finalizada* (volta para *aberta*) e pode cancelar *finalizada*.
- Vistoria finalizada não pode ser alterada silenciosamente.

Rotas correspondentes: `finalizar`, `reativar`, `cancelar`, `complementar`. Autorização sempre via `VistoriaPolicy` / `PontoPolicy`.

### Upload de fotos offline-first

Fotos de campo funcionam sem rede: o Service Worker (`public/sw.js`) + IndexedDB (`resources/js/offline-upload.js`) enfileiram uploads e fazem replay quando a conexão volta. Endpoints em `routes/api.php` (`POST /api/vistorias/fotos`, status, toggle-pública, legenda). Armazenamento via Spatie MediaLibrary (conversões processadas na queue `media-conversions`). Comando `media:clean-orphaned` remove mídia órfã.

### Dados geoespaciais (PostGIS)

| Tabela | Geometria |
|--------|-----------|
| `pontos`, `endereco_atualizados` | POINT |
| `geo_bairros`, `geo_regionais` | MULTIPOLYGON |
| `geo_limite_municipio` | GEOMETRY |

Todas SRID 4326 (WGS84) com índice GIST. Queries espaciais centralizadas em `GeoService` e expostas via `Api\GeoController` / `Api\GeocodingController`. GeoJSON de referência em `public/*.json`. `proj4php` para reprojeção.

### Parametrização

O model `Parametro` (chave/valor) guarda configuração editável em runtime via `/admin/parametros`. Tabelas de domínio (`TipoAbordagem`, `ResultadoAcao`, `Encaminhamento`, `CaracteristicaAbrigo`, `TipoAbrigoDesmontado`) alimentam os selects dos formulários — não hardcodar essas opções.

### Stack & frontend

Laravel 12 (PHP 8.4) · Breeze (auth) · Spatie: laravel-permission (roles, ex.: `admin` via `middleware('role:admin')`), medialibrary, activitylog, backup · Sanctum.

Frontend é **Blade + Alpine + Leaflet**, **sem Tailwind**: design system próprio em `resources/css/app.css` ("Field Instrument", mobile-first, dark, alto contraste). PWA (manifest + service worker). Cada página tem seu próprio entry JS em `resources/js/` registrado em `vite.config.js` (ex.: `vistoria-form.js`, `mapa.js`, `dashboard.js`); ao criar uma página nova com JS, adicione o entry ao `vite.config.js`. `chart.js` é separado em chunk manual.

## Convenções

- Local: comandos diretos; produção: via `docker exec`.
- Form Request classes para validação (`app/Http/Requests/`).
- `Model::query()` em vez de `DB::` (exceto queries otimizadas).
- Eager loading para evitar N+1.
- PHP 8: constructor property promotion, return types explícitos, curly braces obrigatórias.
- Autorização via Policies; nunca checar permissão inline no controller.
- Lógica nova vai no Service, não no controller.
- Rodar `vendor/bin/pint --dirty` antes de finalizar.
- Sempre em pt-BR com acentuação correta (código, comentários, UI, mensagens).

## ETL (Geo → CRAS)

Migração one-shot do POPRUA Geo (produção temporária) para o CRAS (fonte de verdade canônica) via `etl/migrate.sql` (postgres_fdw, transação única). O schema do CRAS sempre vence. Pré-flight `etl:schema-diff` falha se houver divergência não prevista. Comandos em `app/Console/Commands/Etl/`. Ver skill `poprua-etl`.

## Skills do projeto (.claude/skills/)

`setup-ambiente` · `quality-audit` · `ux-friction` · `foto-audit` · `poprua-etl` · `vistoria` (workflow).

## Documentação

| Documento | Descrição |
|-----------|-----------|
| [docs/adr/](docs/adr/) | Architecture Decision Records — **registrar aqui** decisões arquiteturais (ADR-001 = ciclo de vida da vistoria) |
| [docs/casos-de-uso/](docs/casos-de-uso/) | Casos de uso (UC-001…UC-010 implementados) |
| [docs/REGRAS_NEGOCIO.md](docs/REGRAS_NEGOCIO.md) | Regras de negócio |
| [docs/ARQUITETURA_DOCKER.md](docs/ARQUITETURA_DOCKER.md) | Arquitetura Docker, rede, operações |
| [docs/API.md](docs/API.md) | Referência da API REST |
