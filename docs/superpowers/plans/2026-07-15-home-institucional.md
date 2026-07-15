# Home institucional — Plano de Implementação

> **For agentic workers:** execute task-by-task. Steps use checkbox syntax.

**Goal:** Página `/` institucional com marca, versão, ADPF 976 e lançador de funções.

**Architecture:** `HomeController` invokable; rota pública; layout dedicado; CSS em `app.css`; testes Feature.

**Tech Stack:** Laravel 12 · Blade · tokens PBH em `app.css`

## Global Constraints

- Copy ADPF: nunca “sistema oficial da ADPF”
- Ícones SVG idênticos semanticamente ao sidebar
- pt-BR com acentuação

---

### Task 1: Rota + controller + redirect pós-login

- [x] Teste feature: visitante GET `/` → 200, vê Entrar e ADPF
- [x] Teste: autenticado GET `/` → vê atalho Mapa
- [x] `HomeController`, rota pública `home`, remover redirect antigo
- [x] `AuthenticatedSessionController` + `RedirectIfAuthenticated` → `home`
- [x] Atualizar `AuthenticationTest` e `ExampleTest`

### Task 2: View + CSS

- [x] `resources/views/home/index.blade.php` + classes `.home-*`
- [x] Grade 3×2 autenticada; Entrar para guest
- [x] Sidebar logo → `route('home')`
- [x] `pint` + testes verdes
