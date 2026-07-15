# Spec — Fatia 3: listar pendentes + abrir create offline

- **Data:** 2026-07-15
- **Status:** aprovado (design) — Implementado (2026-07-15)
- **Fase do roadmap:** Fase 0, fatia 3 de 3 do "offline de vistorias"
- **Escopo:** (1) listar vistorias da outbox em Minhas Zeladorias; (2) abrir `/vistorias/create` já offline após visita prévia online

## Objetivo

Fechar os dois itens deferidos das fatias 1–2 sem reescrever o fluxo web.

## Decisões

1. **Listagem:** só em `/minhas-vistorias`. Injeção client-side no `tbody` via `getSyncableVistorias()`. Badge “Pendente de envio”; sem link `show` até sincronizar. Meta de exibição (`endereco_label`) gravada no enqueue a partir do DOM.
2. **Create offline:** no load online do create, gravar a resposta no Cache API (`CACHE_NAME`). SW: network-first; se falhar, tenta URL exata e depois shell `/vistorias/create` sem query. JS no form sincroniza `lat`/`lng` da query string nos hidden inputs (permite shell + coords offline).
3. **Fora:** Play Store, plugins nativos, iOS, editar/complementar offline, precache install-time de todas as rotas.

## Critérios de aceite

- [x] Minhas Zeladorias mostra linhas da outbox com badge “Pendente de envio”.
- [x] Após visitar create online, reconectar offline e abrir create (mesmo path ou shell+query) renderiza o formulário.
- [x] Submit offline continua enfileirando; badge e sync existentes não regridem.
- [x] `npm run build` ok; testes Feature de vistoria API verdes.
