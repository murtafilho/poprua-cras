# Fatia 3 offline — Plano de Implementação

> **For agentic workers:** execute task-by-task.

**Goal:** Listar outbox em Minhas Zeladorias e servir create via Cache API após visita online.

**Architecture:** Meta de display no enqueue; injeção em `vistoria-index.js`; precache + fallback de shell no SW; sync lat/lng da URL no form.

**Tech Stack:** IndexedDB · Cache API · Service Worker · Vite entry `vistoria-index.js`

## Global Constraints

- pt-BR com acentuação
- Incrementar `CACHE_VERSION` em `public/sw.js`
- Não alterar versão IndexedDB de `poprua_fotos`

---

### Task 1: Docs — marcar entregas anteriores

- [x] Specs/planos home, Capacitor, outbox, ações: Status = Implementado; checkboxes `[x]` onde o código existe

### Task 2: Meta de display no enqueue + listagem

- [x] `create.blade.php`: `data-endereco-label` no form
- [x] `enqueueVistoria`: gravar `endereco_label` / `tipo_label` no record
- [x] `vistoria-index.js`: em `/minhas-vistorias`, prepend rows pendentes; esconder empty-state se houver pendentes

### Task 3: Create offline (cache + SW)

- [x] `vistoria-form.js`: sync lat/lng da URL; `cacheCreatePage()`
- [x] `sw.js`: bump CACHE_VERSION; fallback shell create
- [x] `npm run build` + testes API vistoria

### Task 4: Fechar checklists da fatia 3

- [x] Atualizar spec fatia 3 e planos
