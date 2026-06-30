# UC-004 — Mapa de Campo e Registro de Nova Ação

**Versão:** 1.0
**Data:** 2026-06-24
**Status:** Implementado

---

## Objetivo

Descrever o **mapa interativo** — tela principal de operação em campo — que permite visualizar pontos georreferenciados, consultar endereço pelo crosshair central, filtrar por resultado da última zeladoria e iniciar o registro de uma **nova ação** (zeladoria) na posição indicada. Inclui modos especiais de ajuste de coordenadas e geocodificação de pontos sem localização precisa.

---

## Atores

| Ator | Descrição |
|------|-----------|
| **Profissional de campo** | Usuário autenticado que navega o mapa, consulta pontos e registra zeladorias. |
| **Administrador / gestor** | Mesmo fluxo de visualização; sem permissões exclusivas no mapa além das já definidas nas demais rotas. |

---

## Pré-condições

1. O usuário está autenticado no sistema.
2. O dispositivo possui conexão de rede para carregar tiles, camadas GeoJSON e pontos (PWA permite uso parcial offline após cache).
3. Para "Nova Ação" com endereço pré-preenchido, zoom ≥ 17 e API de endereços disponível.

---

## Visão Geral da Interface

```
┌─────────────────────────────────────────┐
│  [Busca endereço]              [Menu ≡] │  ← header
├─────────────────────────────────────────┤
│                                         │
│              ─── crosshair ───          │  ← centro fixo do mapa
│                   +                     │
│         (marcadores clusterizados)      │
│                                         │
│  [Nova Ação]              [📍 GPS]      │  ← FABs
└─────────────────────────────────────────┘
```

- **Crosshair:** linhas horizontal e vertical fixas no centro visual do mapa (`#map`), indicando o ponto de referência para nova ação e consulta de endereço.
- **Sem maxBounds:** o mapa **não** restringe panning ao limite de BH (decisão intencional — `maxBoundsViscosity` distorce `flyTo` no mobile e desalinha o crosshair com GPS).
- **Centralização padrão:** Belo Horizonte (`BH_CENTER`: -19.9135, -43.9514), zoom 12.

---

## Fluxo Principal — Registrar Nova Ação pelo Mapa

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Acessa `/mapa` (home do sistema para usuários de BH). | Mapa carrega com camada satélite, limite municipal e pontos visíveis. |
| 2 | Profissional | Navega até o local desejado (pan/zoom). | Crosshair permanece no centro; endereço do centro é buscado via `GET /api/enderecos/por-coordenadas` no evento `moveend` (com debounce/abort de requisições anteriores). |
| 3 | — | — | Botão "Nova Ação" aparece somente com **zoom ≥ 17** (`MIN_ZOOM_NOVA_ACAO`). |
| 4 | Profissional | Clica em "Nova Ação". | Redirecionamento para `/vistorias/create` com query string: coordenadas do crosshair + dados de endereço (se disponíveis via reverse geocoding). |
| 5 | Profissional | Preenche e salva a zeladoria no formulário. | Sistema cria ou reutiliza ponto nas coordenadas informadas (`PontoService::findOrCreateFromCoordinates`, raio 50 m). |

---

## Fluxo Alternativo A — Buscar Endereço

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Digita no campo "Buscar endereço..." no header. | Autocomplete consulta API de endereços. |
| 2 | Profissional | Seleciona um resultado. | Mapa centraliza com `flyTo` no endereço escolhido (zoom 18). |

---

## Fluxo Alternativo B — Consultar Ponto Existente

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Clica em um marcador de ponto no mapa. | Mapa faz `flyTo` zoom 18 na posição do marcador. Marcadores são agrupados em clusters (`markerClusterGroup`, desagrupam no zoom 18). |
| 2 | Profissional | Interage com popup/detalhes do ponto (conforme implementação do marcador). | Pode acessar relatório de vistoria via modal iframe (`/vistorias/{id}/relatorio`). |

Pontos são carregados via `GET /api/pontos?north=&south=&east=&west=` conforme bounding box visível; recarregamento ocorre ao mover o mapa para área não cacheada.

---

## Fluxo Alternativo C — Minha Localização (GPS)

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Clica no FAB de localização (canto inferior). | Sistema solicita `navigator.geolocation.getCurrentPosition` (one-shot, não contínuo). |
| 2 | — | — | Mapa centraliza nas coordenadas obtidas **sem** plotar marcador permanente de GPS. |
| 3 | — | — | Profissional posiciona o crosshair sobre o local real e prossegue com "Nova Ação". |

**Decisão de UX:** não usar `watchPosition` — a primeira leitura do watch é imprecisa (IP/rede) e deslocava o mapa quilômetros do ponto real.

---

## Fluxo Alternativo D — Painel de Camadas e Filtros

Acessível pelo botão de menu (≡) no header.

### D.1 — Mapa base

| Opção | Descrição |
|-------|-----------|
| Ruas | Tile OpenStreetMap |
| Satélite | Tile Esri World Imagery (padrão) |

### D.2 — Camadas sobrepostas

| Camada | Fonte | Padrão |
|--------|-------|--------|
| Regionais | `GET /api/geo/regionais` | Desligada |
| Bairros | `GET /api/geo/bairros` | Desligada |
| Limite Municipal | `GET /api/geo/limite-municipio` | **Ligada** |
| Pontos | API bounding box | **Ligada** |

Camadas de bairros e regionais exibem labels com nomes quando ativadas.

### D.3 — Filtro por resultado

Checkboxes filtram marcadores pela cor/resultado da **última zeladoria** do ponto:

| Cor | Resultado |
|-----|-----------|
| Vermelho | Fenômeno persiste |
| Laranja | Impactado parcialmente |
| Cinza escuro | Deixou de ocorrer |
| Cinza | PSR ausente |
| Azul | Não constatado |
| Verde | Em conformidade |
| Roxo | Sem vistoria |

Filtros aplicam-se client-side sobre marcadores já carregados (`applyFilters`).

---

## Fluxo Alternativo E — Ajustar Coordenadas de Ponto

Entrada via listagem de pontos (`/pontos`) → clique em ponto → mapa com `?ajustar=1&ponto_id={id}&lat=&lng=`.

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Mapa abre isolado no ponto com crosshair centralizado. | Botão "Nova Ação" oculto. Painel fixo inferior exibe endereço e coordenadas em tempo real. |
| 2 | Profissional | Move o mapa para reposicionar o crosshair. | Coordenadas atualizadas no evento `moveend`. |
| 3 | Profissional | Clica em "Salvar". | `PATCH /api/pontos/{id}/coordenadas` atualiza `lat`, `lng` e coluna PostGIS `geom`. |
| 4 | — | — | Redirecionamento para listagem de pontos com mensagem de sucesso. |

---

## Fluxo Alternativo F — Geocodificar Ponto sem Coordenadas

Entrada via fluxo de pontos não georreferenciados com `?geocoded=1&ponto_id={id}`.

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Mapa exibe marcador amarelo inicial na posição estimada. | Painel "Confirmar Localização" visível no layout. |
| 2 | Profissional | Clica no mapa para ajustar posição. | Marcador atualiza para verde na nova posição. |
| 3 | Profissional | Clica em "Confirmar". | `PATCH /api/pontos/{id}/coordenadas` persiste coordenadas; retorno ao mapa normal com zoom 19. |

---

## Modos de Operação (Query String)

| Parâmetro | Modo | Comportamento |
|-----------|------|---------------|
| *(nenhum)* | Normal | Mapa completo com pontos, filtros e Nova Ação |
| `lat`, `lng`, `zoom` | Foco | Centraliza em coordenadas; exibe marcador azul |
| `ajustar=1&ponto_id=` | Ajuste | Reposicionar ponto existente |
| `geocoded=1&ponto_id=` | Geocode | Confirmar localização de ponto sem geo |
| `endereco`, `referencia` | Contexto | Texto exibido no popup do marcador focal |

---

## Resumo das Regras de Negócio

| # | Regra |
|---|-------|
| RN1 | O mapa exige autenticação (`/mapa` dentro do grupo `auth`). |
| RN2 | O crosshair é fixo no centro visual; coordenadas de referência = centro do mapa (`map.getCenter()`). |
| RN3 | "Nova Ação" só é exibida com zoom ≥ 17. |
| RN4 | Nova ação redireciona para `/vistorias/create` com lat/lng e dados de endereço quando disponíveis. |
| RN5 | Pontos são clusterizados; clusters desagrupam no zoom 18; clique em marcador centraliza zoom 18. |
| RN6 | Carregamento de pontos é lazy por bounding box; moveend fora da área cacheada dispara novo fetch. |
| RN7 | Limite municipal é camada padrão; maxBounds **não** é aplicado (decisão UX mobile/GPS). |
| RN8 | GPS usa leitura única (`getCurrentPosition`); não plota marcador de localização. |
| RN9 | Ajuste de coordenadas atualiza `lat`, `lng` e `geom` (PostGIS SRID 4326) atomicamente via API. |
| RN10 | Endereço do crosshair é obtido por reverse geocoding; requisições concorrentes são canceladas (AbortController). |

---

## Integrações

| Sistema | Uso no mapa |
|---------|-------------|
| `/api/pontos` | Marcadores clusterizados |
| `/api/enderecos/*` | Busca, autocomplete, reverse geocoding |
| `/api/geo/*` | Camadas bairros, regionais, limite municipal |
| `/vistorias/create` | Destino do fluxo "Nova Ação" |
| `/vistorias/{id}/relatorio` | Modal de relatório |
| Service Worker (`sw.js`) | PWA — cache de assets; registro no layout |

---

## Glossário

| Termo | Significado |
|-------|-------------|
| **Nova Ação** | Início do registro de uma zeladoria na posição do crosshair. |
| **Crosshair** | Indicador visual fixo no centro do mapa; referência espacial para coordenadas. |
| **Cluster** | Agrupamento de marcadores próximos em zoom baixo; expande ao aproximar. |
| **Modo ajuste** | Fluxo de reposicionamento de ponto existente a partir da listagem. |
| **Modo geocode** | Fluxo de confirmação de coordenadas para ponto sem georreferência. |
| **Resultado do ponto** | Status herdado da última zeladoria não excluída vinculada ao ponto. |
