# CLAUDE.md — SIZEM (repositorio)

## Fonte da verdade

**GitHub** (`git@github.com:murtafilho/poprua-cras.git`, branch `main`) e a autoridade canonica do codigo.

```
maquina local  --git push-->  GitHub  --Actions-->  vlcp-sufis01 (deploy.sh)
```

Clone de trabalho: este repositorio. Producao recebe deploy automatico no push em `main` (GitHub Actions + self-hosted runner no vlcp-sufis01).

```bash
bash poprua st              # git status local
bash poprua push            # envia para GitHub + aguarda deploy automatico
bash poprua deploy          # deploy manual no servidor (fallback)
bash poprua release "msg"   # commit + push + aguarda deploy
bash poprua setup-server    # configura origin no servidor (1x)
```

SIZEM — Sistema Integrado de Zeladoria Municipal, derivado do POPRUA Geo, focado na integracao com CRAS (Zeladoria Urbana). Laravel 12 / PHP 8.4 / PostgreSQL + PostGIS / Redis / Vite.

Fork inicial baseado em [poprua-geo](https://github.com/murtafilho/poprua-geo) em 2026-05-18.

## Ambientes

### Desenvolvimento local (ambiente preferido para dev/refatoracao)

PHP 8.4, PostgreSQL 18.4 + PostGIS 3.6, Redis 7 e Node 22 instalados nativamente. O `.env` aponta para localhost; todos os comandos rodam direto, sem `docker exec`.

> O PG18 roda no cluster `18/main` na **porta 5433** (criado side-by-side; o cluster `16/main` na 5432 segue ativo para outros projetos da maquina). Por isso `DB_PORT=5433` no `.env` e no `phpunit.xml`.

```bash
php artisan migrate
php artisan test
php artisan test --filter=NomeDoTeste
vendor/bin/pint --dirty
vendor/bin/phpstan analyse
php artisan cache:clear && php artisan config:clear
composer install
npm install && npm run build
php artisan serve --port=8088
```

**DB local:** host=127.0.0.1 port=5433 db=poprua_cras user=poprua_cras password=poprua_cras
**DB de teste:** poprua_cras_test (mesmo host/senha — configurado em `phpunit.xml`)

### Producao (Docker em vlcp-sufis01)

Publicar: `bash poprua push` (da maquina local) — o deploy roda sozinho no servidor via `.github/workflows/deploy-production.yml`. Fallback manual: `bash poprua deploy`.

Os mesmos comandos artisan rodam via `docker exec` (ver `docker/rebuild.sh` e `../CLAUDE.md`).

URL de producao: `https://sufis.pbh.gov.br/ginfi/poprua-cras/public`

## Acesso ao Sistema (dev)

**URL:** http://localhost:8088 · **Login:** murtafilho@gmail.com / xman74102 · **Role:** admin

**Rotas principais:** `/mapa` · `/vistorias` e `/minhas-vistorias` · `/pontos` · `/moradores` · `/admin/parametros`

## Arquitetura

### Dominio central: Ponto → Vistoria → Morador

- **Ponto** — local fisico (endereco). Coordenadas lat/lng + vinculo com `EnderecoAtualizado`.
- **Vistoria** — registro de visita/abordagem. Flags de complexidade, encaminhamentos, fotos via Spatie MediaLibrary.
- **Morador** — pessoa identificada. Tracking via `MoradorHistorico`.

### Camada de servicos

Controllers finos; logica em `app/Services/` (`VistoriaService`, `PontoService`, `MoradorService`, etc.).

### Ciclo de vida da Vistoria

Estados: **aberto → finalizado → cancelado** (ADR-001). Regras em `VistoriaPolicy`.

### Upload de fotos offline-first

Service Worker + IndexedDB (`offline-upload.js`). Spatie MediaLibrary na queue `media-conversions`.

### Dados geoespaciais (PostGIS)

Queries em `GeoService`. SRID 4326.

### Stack & frontend

Laravel 12 · Breeze · Spatie (permission, medialibrary, activitylog) · Sanctum.

Frontend: Blade + Alpine + Leaflet. Design system em `resources/css/app.css` (tema PBH claro). PWA com service worker. Entries JS em `vite.config.js`.

## Convencoes

- Form Request classes para validacao.
- Autorizacao via Policies.
- Logica nova no Service, nao no controller.
- `vendor/bin/pint --dirty` antes de finalizar.
- UI/relatorios: nomenclatura **Zeladoria** quando aplicavel.
- Service Worker: incrementar `CACHE_VERSION` em `public/sw.js` ao mudar assets offline.
- Sempre em pt-BR com acentuacao correta.

## ETL (Geo → CRAS)

`etl/migrate.sql` via postgres_fdw. Skill `poprua-etl`.

## Skills do projeto (.claude/skills/)

`setup-ambiente` · `quality-audit` · `ux-friction` · `foto-audit` · `poprua-etl` · `vistoria`

## Documentacao

| Documento | Descricao |
|-----------|-----------|
| [docs/adr/](docs/adr/) | Architecture Decision Records |
| [docs/casos-de-uso/](docs/casos-de-uso/) | Casos de uso |
| [../CLAUDE.md](../CLAUDE.md) | Infra, SSH, banco, diagnostico |