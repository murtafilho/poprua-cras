# Changelog - POPRUA v2

Todas as alteracoes relevantes do sistema sao documentadas neste arquivo.

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
