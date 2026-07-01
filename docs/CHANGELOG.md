# Changelog - SIZEM

Todas as alteracoes relevantes do sistema sao documentadas neste arquivo.

---

## [0.4.5] - 2026-06-24

### Erradicação Google Drive / R2 (ADR-009)

#### Removido
- `config/services.php` — credenciais `google_drive`
- `config/filesystems.php` — disco `r2_fotos`

#### Documentação
- Referências a Google Drive, R2 e `cloud_status` em API, REGRAS_NEGOCIO, README, ARQUITETURA_DOCKER, CLAUDE.md
- Skill `foto-audit` v1.1 — rubrica local-only (sem cloud sync)

---

## [0.4.4] - 2026-06-24

### Rascunho fase 2 e testes admin

#### Novo
- Comando `rascunhos:limpar` — expira rascunhos após N dias (`rascunho_dias_expiracao`, default 30)
- Parâmetros `rascunho_debounce_ms` e `rascunho_dias_expiracao` seedados em `/admin/parametros`

#### Melhorado
- Autosave do rascunho lê debounce de `Parametro::get('rascunho_debounce_ms')` via `VISTORIA_RASCUNHO_CTX`

#### Testes
- `ParametroControllerTest`, `LimparRascunhosExpiradosCommandTest`

---

## [0.4.3] - 2026-06-24

### Documentação e upload offline

#### Melhorado
- Legendas de fotos persistidas no IndexedDB e enviadas no sync offline (`updatePendingPhotoLegenda`)

#### Documentação
- UC-008 — dashboard de gestão (indicadores + gráfico evolução)
- UC-009 — runbook de cutover ETL Geo→CRAS
- UC-010 — parametrização administrativa (`/admin/parametros`)

---

## [0.4.2] - 2026-06-24

### Participantes e datas legadas

#### Melhorado
- Enum `TipoEquipe` — participantes agrupados por role (Supervisores, GCM, SLU, etc.) em create/edit/show e Minha Equipe
- Helper `FormatoData` — oculta hora `00:00` em datas legadas na tela de detalhes

#### Testes
- `TipoEquipeTest`, `FormatoDataTest`, `ParticipantesEquipeTest`

---

## [0.4.1] - 2026-06-24

### Zeladoria — Condicional UI e documentação

#### Melhorado
- Campos `data_prevista_zeladoria` / `periodo_zeladoria` só para tipo Comunicação de Zeladoria (auditoria 1.3)
- Atalho "Ajustar localização" no header de `vistorias/show` (auditoria 1.7)
- **ADR-009 F2:** IndexedDB consolidado em `offline-upload.js` (form, edit, show, app, SW)
- Export **Excel/CSV** do roteiro (`format=csv`, UTF-8 BOM, separador `;`)

#### Documentação
- UC-007 — upload offline de fotos (IndexedDB + Service Worker)
- UC-009 — migração ETL Geo → CRAS (one-shot)
- Auditoria zeladoria: plano de ações atualizado

---

## [0.4.0] - 2026-06-24

### Zeladoria — Rascunho (UC-006)

#### Novo
- **Salvamento parcial server-side** — tabela `vistorias_rascunhos`, API `GET/PATCH/DELETE /api/vistorias/rascunho`
- **Autosave** no wizard de criacao (debounce 5s) + botao "Salvar rascunho" + retomada ao reabrir formulario
- **Limpeza automatica** do rascunho apos `POST /vistorias` bem-sucedido

#### Documentacao
- Casos de uso UC-003 (Morador), UC-004 (Mapa), UC-005 (Ponto), UC-006 (Rascunho)
- Auditoria zeladoria item 1.2 marcado como implementado (91% de cobertura)

---

## [0.3.0] - 2026-03-15
### Infraestrutura

#### Corrigido
- **Porta SSH do container** — corrigida de 2222 para 2224 no rebuild.sh e docker-compose.yml (conflito com container php82-novo-sif que ja usava 2222)
- Documentacao ARQUITETURA_DOCKER.md atualizada com a porta correta

### Paginacao e Ajuste de Pontos

#### Novo
- **Componente `<x-pagination-bar>`** — paginacao reutilizavel com contador estilizado, seletor de itens por pagina e navegacao por numeros de pagina com ellipsis inteligente
- **Modo ajuste de ponto no mapa** — ao clicar em um ponto (lista de pontos ou vistorias), o mapa abre isolado no ponto com crosshair centralizado, permitindo reposicionar e salvar as novas coordenadas via API
- **Painel de ajuste** — painel fixo na parte inferior do mapa com endereco do ponto, coordenadas em tempo real e botoes Salvar/Cancelar
- **Edicao de ponto** — view `pontos/edit` com mini-mapa interativo, autocomplete de endereco e campos de identificacao

#### Melhorado
- **Crosshair do mapa** — movido para dentro do `#map` para alinhar com o centro real do Leaflet; contraste aumentado (2px, opacidade 0.75, box-shadow)
- **API `updateCoordenadas`** — agora atualiza a coluna `geom` (PostGIS) junto com `lat/lng`
- Paginacao duplicada em 5 views substituida pelo componente unico

#### Corrigido
- Deslocamento do crosshair em relacao ao ponto causado por padding do header/bottom-nav
- Permissoes de cache de views (root → www-data)

---

## [0.2.0] - 2026-03-15

### Dashboard, Sync e Menu

#### Novo
- **Dashboard qualitativo** — grafico de evolucao do fenomeno com status cumulativo por ponto ao longo do tempo
- **`DashboardController`** — indicadores gerais e serie historica completa
- **Comando `sync:mysql-to-postgres`** — sincronizacao do banco MySQL legado com backup automatico e rollback
- **Comando `pontos:vincular-enderecos`** — fallback alfanumerico para vincular pontos do legado MySQL
- **Minhas Vistorias** — listagem filtrada por usuario logado com filtros de data e resultado
- **Slideshow de fotos** — navegacao por setas, teclado e swipe mobile na view de vistoria

#### Melhorado
- **Menu reorganizado** — sidebar e bottom-nav com itens: Dashboard, Mapa, Minhas, Moradores, Mais
- **View moradores/edit** — padronizada com design system
- Pontos fora do limite municipal de BH eliminados

#### Adicionado
- Campo `observacao` na tabela `pontos`
- Documentacao completa da arquitetura Docker (`docs/ARQUITETURA_DOCKER.md`)

#### Removido
- Layouts obsoletos (`breeze`, `navigation`)

---

## [0.1.0] - 2026-03-05

### Mapa, UX Mobile e Review Tab

#### Novo
- **Crosshair no mapa** — linhas h+v no centro para indicar ponto de vistoria
- **Bottom sheet de endereco** — exibe endereco do crosshair via `moveend` (zoom >= 16)
- **Bottom-nav persistente** — navegacao inferior em todas as paginas
- **Tab "Revisar"** — checklist de campos obrigatorios nos formularios de vistoria (create/edit)
- **Confirmacao de voltar** — prevencao ao pressionar botao voltar do Android (History API)
- **Painel de camadas** — botao fechar e fontes maiores

#### Melhorado
- Clique no mapa centraliza com `flyTo` zoom 18
- FAB de localizacao apenas centraliza sem plotar marcador
- Bottom sheet com safe-area-inset para dispositivos com notch

#### Removido
- Zoom control nativo do Leaflet
- Badge de zoom no header
- FAB "Nova Vistoria" (substituido pelo crosshair + botao "Nova Acao")
- Botoes Anterior/Proxima dos formularios de vistoria
- Hamburger do header (existe no bottom-nav "Mais")
