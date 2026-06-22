---
name: ux-friction
description: >
  Auditoria automatizada de UX e atrito navegando fluxos criticos do POPRUA CRAS
  via Playwright. Mede cliques, tempo, campos, erros, feedback e touch targets em
  8 fluxos (login, pontos, criar vistoria, editar/finalizar, morador, fotos, mapa,
  admin). Produz score 0-100 por fluxo + score geral ponderado, screenshots dos
  pontos de atrito, e matriz de melhorias Q1-Q4. Suporta viewport desktop e mobile.
  Use sempre que o usuario pedir para auditar UX, medir atrito, testar fluxos,
  verificar usabilidade, analisar experiencia do usuario, ou variacoes como
  'ux audit', 'friction audit', 'testar usabilidade', 'atrito', 'experiencia do
  usuario', 'testar fluxos', 'navegar pelo sistema', 'quantos cliques', 'demora
  pra carregar', 'pagina pesada', 'formulario longo', 'UX do campo', 'testar no
  celular', 'viewport mobile', 'touch targets', 'audit mobile'.
---

# UX Friction Audit — POPRUA CRAS

Auditoria automatizada de UX navegando fluxos criticos via Playwright, medindo atrito real (cliques, tempo, campos, erros, feedback) e produzindo score 0-100 por fluxo + score geral ponderado.

**Stack:** Laravel 12 / Blade / Alpine.js / Leaflet / Vite. PWA usado em campo por equipes CRAS.
**Dominio:** Ponto -> Vistoria -> Morador. Mapa georreferenciado. Admin com RBAC.

## Ambientes e seguranca

O Playwright roda **a partir da maquina do dev** (local, v1.58+) contra um alvo
configuravel por `ALVO`. **Padrao = `prod`** (homologacao pos-migracao Geo->CRAS).

| ALVO | APP_URL | Checks de DB (read-only) |
|---|---|---|
| **prod** (padrao) | `https://sufis.pbh.gov.br/ginfi/poprua-cras/public` | via `ssh sufis "sudo docker exec pg17-poprua-cras psql ..."` |
| local | `http://localhost:8088` | `psql -h 127.0.0.1 -p 5434 -U poprua_cras ...` |

### ⚠️ Seguranca em PRODUCAO — fluxos de ESCRITA

F3 (criar vistoria), F4 (finalizar), F5 (criar morador) e F8 (criar usuario)
**GRAVAM no banco de producao**. Por isso, em `prod` a skill roda por padrao em
**modo nao-destrutivo**: navega, mede tempo/campos/cliques, tira screenshots e
preenche os formularios, mas **NAO submete** os passos de escrita (para no passo
anterior ao submit/finalizar). Para exercitar o submit de verdade, passe `--write`:
os registros sao criados **marcados** (vistoria `nomes_pessoas` e morador
`nome_registro` com prefixo `[HOMOLOG]`) e **removidos/soft-deleted ao final**,
com log do que foi criado. NUNCA rode `--write` sem combinar a limpeza.

### Credenciais (env — nunca commitar)

```bash
export UX_USER="${UX_USER:-claude.test@interno.local}"   # migrado do geo, role agentes-campo
export UX_PASS="${UX_PASS:?defina a senha do usuario de teste}"
# F8 (admin) exige um admin: export UX_ADMIN_USER=... UX_ADMIN_PASS=...
```

Se a senha de `claude.test` for desconhecida, um admin pode reseta-la em prod
(e conta de teste, sem dados reais):

```bash
ssh sufis "sudo docker exec -u www-data php84-poprua-cras php /var/www/html/joomla_sufis/ginfi/poprua-cras/artisan tinker --execute \"\\\$u=App\\Models\\User::where('email','claude.test@interno.local')->first(); \\\$u->password=bcrypt('SENHA_TESTE'); \\\$u->save(); echo 'ok';\""
```

### Pre-flight

```bash
ALVO="${ALVO:-prod}"
if [ "$ALVO" = "prod" ]; then
  APP_URL="${APP_URL:-https://sufis.pbh.gov.br/ginfi/poprua-cras/public}"
else
  APP_URL="${APP_URL:-http://localhost:8088}"
fi

# 1. App (login) respondendo
curl -s -o /dev/null -w "%{http_code}" "$APP_URL/login" | grep -qE "200|302" \
  && echo "OK app" || echo "FAIL app"

# 2. Playwright local
npx playwright --version 2>/dev/null && echo "OK playwright" || echo "FAIL playwright -> npx playwright install chromium"

# 3. Dados minimos + usuario de teste (read-only). Em prod, via ssh:
ssh sufis "sudo docker exec pg17-poprua-cras psql -U poprua_cras -d poprua_cras -tAF'|' -c \"
  SELECT (SELECT count(*) FROM pontos), (SELECT count(*) FROM vistorias),
         (SELECT string_agg(r.name,',') FROM users u JOIN model_has_roles mr ON mr.model_id=u.id
            JOIN roles r ON r.id=mr.role_id WHERE u.email='${UX_USER:-claude.test@interno.local}')\""
# (local: trocar por  psql -h 127.0.0.1 -p 5434 -U poprua_cras -d poprua_cras -tAF'|' -c "...")
```

- **prod**: app atras de proxy HTTPS; o Playwright local precisa de rota ate
  `sufis.pbh.gov.br` (rede RMI/PBH). Se app FAIL em prod, e rede/proxy — **NAO**
  "subir servidor" (prod nao usa `php artisan serve`).
- **local**: se app FAIL, `php artisan serve --port=8088 &` e revalidar.
- playwright FAIL: `npx playwright install chromium`.
- dados `0|0`: avisar — auditoria parcial.

---

## Os 8 Fluxos

### F1 — Login -> Dashboard
**Rota:** `GET /login` -> POST credentials -> `GET /`
**Objetivo:** Medir tempo de login + carregamento do dashboard com graficos.

```
Playwright:
1. navigate(APP_URL + '/login')
2. waitForLoadState('networkidle') -> medir t_login_page
3. fill('[name=email]', ADMIN_EMAIL)
4. fill('[name=password]', ADMIN_PASSWORD)
5. click('button[type=submit]')
6. waitForURL('**/') -> medir t_login_submit
7. waitForLoadState('networkidle') -> medir t_dashboard_load
8. screenshot('f1-dashboard.png')
9. Contar elementos visiveis no dashboard (cards, graficos)
```

**Metricas:**
- `t_login_page`: tempo para carregar pagina de login
- `t_login_submit`: tempo de submit ate redirect
- `t_dashboard_load`: tempo total ate dashboard interativo
- `dashboard_elements`: quantidade de cards/graficos visiveis

**Scoring:** Base 100; t_login_page > 2s -5; t_login_submit > 3s -10; t_dashboard_load > 5s -15; < 3 elementos no dashboard -5.

---

### F2 — Listar Pontos + Filtrar
**Rota:** `GET /pontos`
**Objetivo:** Medir carregamento da listagem, uso de filtros, paginacao.

```
Playwright:
1. navigate(APP_URL + '/pontos')
2. waitForLoadState('networkidle') -> medir t_list_load
3. Contar linhas na tabela (tr count)
4. screenshot('f2-pontos-list.png')
5. Se filtro existe: preencher campo de busca, submit
6. waitForLoadState('networkidle') -> medir t_filter
7. Verificar se resultados filtrados sao menores que totais
8. Se paginacao existe: clicar proxima pagina -> medir t_paginate
```

**Metricas:**
- `t_list_load`: tempo de carregamento inicial
- `t_filter`: tempo de resposta do filtro
- `t_paginate`: tempo de paginacao
- `rows_visible`: linhas visiveis sem scroll
- `filter_available`: booleano
- `pagination_available`: booleano

**Scoring:** Base 100; t_list_load > 3s -10; sem filtro -10; sem paginacao -5; t_filter > 2s -5; < 5 rows_visible -5.

---

### F3 — Criar Vistoria Completa
**Rota:** `GET /pontos/{id}/vistorias/create`
**Objetivo:** Fluxo mais critico — criar vistoria com wizard multi-step, campos, checkboxes, encaminhamentos.

```
Playwright:
1. Navegar ate um ponto com vistorias/create
2. waitForLoadState('networkidle') -> medir t_form_load
3. Contar steps do wizard (stepper items)
4. Contar campos por step:
   - Para cada step: click no stepper, contar inputs visiveis
5. Contar total de campos obrigatorios (required)
6. Contar total de campos opcionais
7. Tentar submeter vazio -> contar erros de validacao -> medir clareza
8. Preencher campos minimos por step
9. screenshot('f3-step-N.png') em cada step
10. Submit -> medir t_submit
11. Verificar redirect/flash de sucesso
```

**Metricas:**
- `t_form_load`: tempo de carregamento do formulario
- `wizard_steps`: quantidade de steps
- `total_fields`: campos totais
- `required_fields`: campos obrigatorios
- `fields_per_step`: media de campos por step
- `t_submit`: tempo de submit
- `validation_messages_clear`: booleano (mensagens claras em pt-BR)
- `clicks_to_complete`: total de cliques para preencher e submeter
- `scroll_depth`: se precisa scroll dentro de algum step

**Scoring (peso 2x):** Base 100; > 20 campos totais -1/extra (cap -15); > 5 steps -3/extra; t_form_load > 3s -10; t_submit > 5s -10; mensagens de erro em ingles -10; scroll > 3x viewport em step -5; > 15 cliques para completar -1/extra (cap -10).

---

### F4 — Editar Vistoria + Finalizar
**Rota:** `GET /vistorias/{id}/edit` -> POST finalizar
**Objetivo:** Medir atrito de edicao e fluxo de finalizacao (confirm dialog).

```
Playwright:
1. navigate(APP_URL + '/vistorias/{id}/edit')
2. waitForLoadState('networkidle') -> medir t_edit_load
3. Verificar pre-populacao dos campos (campos com valor vs vazios)
4. Contar campos editaveis
5. Modificar 1 campo
6. screenshot('f4-edit.png')
7. Clicar "Salvar" -> medir t_save
8. Clicar "Finalizar" -> verificar se pede confirmacao
9. Se confirm dialog: aceitar -> medir t_finalize
10. Verificar status atualizado
```

**Metricas:**
- `t_edit_load`: tempo de carregamento
- `prepopulated_ratio`: campos preenchidos / total
- `t_save`: tempo de save
- `has_confirm_dialog`: booleano (finalizar pede confirmacao)
- `t_finalize`: tempo de finalizacao
- `status_feedback`: booleano (mostra toast/flash de sucesso)

**Scoring (peso 2x):** Base 100; t_edit_load > 3s -10; prepopulated_ratio < 0.8 -10; sem confirm -15; sem feedback apos finalizar -10; t_save > 5s -10.

---

### F5 — Cadastrar Morador na Vistoria
**Rota:** Modal dentro de `/pontos/{id}/vistorias/create`
**Objetivo:** Medir atrito do modal de morador (abertura, campos, salvamento).

```
Playwright:
1. Estar no formulario de vistoria (F3)
2. Clicar botao "Adicionar Morador" -> medir t_modal_open
3. Verificar se modal abriu (overlay visivel)
4. Contar campos do modal
5. Contar campos obrigatorios
6. screenshot('f5-modal-morador.png')
7. Preencher campos minimos
8. Clicar "Salvar" -> medir t_modal_save
9. Verificar se modal fechou e morador apareceu na lista
```

**Metricas:**
- `t_modal_open`: tempo de abertura
- `modal_fields`: campos no modal
- `modal_required`: campos obrigatorios
- `t_modal_save`: tempo de salvamento
- `morador_added_feedback`: booleano (morador aparece na lista)
- `modal_keyboard_trap`: booleano (foco preso no modal)

**Scoring:** Base 100; t_modal_open > 1s -10; > 10 campos -1/extra (cap -10); sem feedback ao salvar -15; sem overlay click-outside -5; t_modal_save > 3s -10.

---

### F6 — Upload de Fotos (Vistoria)
**Rota:** Dentro de F3/F4 (step de fotos)
**Objetivo:** Medir atrito de upload, preview, feedback de progresso.

```
Playwright:
1. Estar no step de fotos do formulario de vistoria
2. screenshot('f6-upload-area.png')
3. Verificar se existe area de upload visivel
4. Verificar presenca de preview de imagem
5. Verificar indicador de progresso
6. Contar botoes (camera, galeria)
7. Verificar tamanho minimo dos botoes (touch target >= 44px)
8. Avaliar se instrucoes sao claras
```

**Metricas:**
- `upload_area_visible`: booleano
- `has_preview`: booleano
- `has_progress_indicator`: booleano
- `camera_button_exists`: booleano
- `gallery_button_exists`: booleano
- `touch_targets_ok`: booleano (botoes >= 44px)
- `instructions_present`: booleano

**Scoring:** Base 100; sem area visivel -20; sem preview -10; sem progresso -10; botoes < 44px -10; sem instrucoes -5; apenas 1 metodo de upload -5.

---

### F7 — Mapa + Navegar para Ponto
**Rota:** `GET /mapa`
**Objetivo:** Medir carregamento do mapa, interacao com markers, navegacao.

```
Playwright:
1. navigate(APP_URL + '/mapa')
2. waitForLoadState('networkidle') -> medir t_mapa_load
3. Aguardar tiles carregados (verificar leaflet-tile-loaded ou similar)
4. screenshot('f7-mapa.png')
5. Contar markers visiveis
6. Se marker existe: clicar -> verificar popup/tooltip
7. Se popup tem link: clicar -> medir t_navigate
8. Verificar controles de zoom visiveis
```

**Metricas:**
- `t_mapa_load`: tempo de carregamento do mapa
- `markers_visible`: quantidade
- `has_popup`: booleano
- `popup_has_link`: booleano
- `t_navigate`: tempo de navegacao marker -> ponto
- `zoom_controls`: booleano

**Scoring:** Base 100; t_mapa_load > 5s -15; 0 markers -10; sem popup -10; popup sem link pro ponto -5; sem zoom controls -5.

---

### F8 — Admin: Criar Usuario + Atribuir Role
**Rota:** `GET /admin/users/create`
**Objetivo:** Medir atrito administrativo.

```
Playwright:
1. navigate(APP_URL + '/admin/users/create')
2. waitForLoadState('networkidle') -> medir t_admin_load
3. Contar campos do formulario
4. screenshot('f8-admin-create.png')
5. Verificar se roles sao selecionaveis (dropdown/checkboxes)
6. Preencher campos
7. Submit -> medir t_admin_submit
8. Verificar redirect/feedback
```

**Metricas:**
- `t_admin_load`: tempo de carregamento
- `admin_fields`: campos
- `role_selector_type`: tipo (dropdown/checkboxes/radio)
- `t_admin_submit`: tempo de submit
- `success_feedback`: booleano

**Scoring:** Base 100; t_admin_load > 3s -5; > 10 campos -1/extra; sem feedback -10; t_admin_submit > 5s -10.

---

## Metricas Transversais (aplicadas a TODOS os fluxos)

Alem das metricas por fluxo, verificar em cada pagina visitada:

```bash
# Touch targets — elementos interativos < 44x44px
page.evaluate(() => {
  const interactive = document.querySelectorAll('a, button, input, select, textarea, [x-on\\:click], [role=button]');
  let small = 0;
  interactive.forEach(el => {
    const rect = el.getBoundingClientRect();
    if (rect.width > 0 && rect.height > 0 && (rect.width < 44 || rect.height < 44)) small++;
  });
  return { total: interactive.length, small };
})

# Loading states — presenca de spinners/skeletons
page.evaluate(() => {
  const hasLoading = document.querySelector('.spinner, .loading, .skeleton, [x-show*=loading], [x-cloak]');
  return !!hasLoading;
})

# Mensagens em pt-BR — verificar se erros/labels estao em portugues
page.evaluate(() => {
  const enPatterns = /required|invalid|error|success|warning|please|submit|cancel|delete|confirm/i;
  const labels = Array.from(document.querySelectorAll('label, .error, .alert, button'));
  const english = labels.filter(el => enPatterns.test(el.textContent));
  return { total: labels.length, english: english.length };
})

# Scroll depth
page.evaluate(() => {
  return {
    scrollHeight: document.documentElement.scrollHeight,
    viewportHeight: window.innerHeight,
    ratio: document.documentElement.scrollHeight / window.innerHeight
  };
})
```

**Penalizacoes transversais (por pagina):**
- Touch targets < 44px: -1/elemento (cap -10 por fluxo)
- Sem loading states: -3
- Labels em ingles: -2 por pagina com labels en
- Scroll > 3x viewport: -5

---

## Execucao

### Modo de invocacao

- `ux-friction` — auditoria completa dos 8 fluxos (alvo **prod**, modo nao-destrutivo)
- `ux-friction F3` — auditar apenas um fluxo especifico
- `ux-friction mobile` — auditar com viewport mobile (375x812, iPhone 14)
- `ux-friction comparar` — rodar desktop + mobile e comparar scores
- `ux-friction local` — alvo dev local (`ALVO=local`, `http://localhost:8088`)
- `ux-friction --write` — exercita os submits de escrita em prod com registros
  marcados `[HOMOLOG]` + limpeza ao final (ver "Seguranca em PRODUCAO" acima).
  Sem `--write`, F3/F4/F5/F8 param antes do submit (default seguro em prod).

### Viewport

| Modo | Width | Height |
|------|-------|--------|
| Desktop (padrao) | 1280 | 720 |
| Mobile | 375 | 812 |

### Fluxo de execucao

```
1. Pre-flight (app + playwright + dados + usuario)
2. Login (compartilhado entre todos os fluxos)
3. Para cada fluxo F1..F8:
   a. Navegar ate a rota
   b. Coletar metricas
   c. Tirar screenshots
   d. Calcular score do fluxo
   e. Se erro: try/catch, degradar, logar, continuar
4. Calcular metricas transversais
5. Agregar score geral
6. Gravar JSON + screenshots
7. Gerar relatorio
```

### Sessao compartilhada

IMPORTANTE: usar uma unica sessao de browser para todos os fluxos. O login feito em F1 persiste para F2..F8. Nao abrir/fechar browser entre fluxos.

---

## Scoring

### Pesos dos fluxos

| Fluxo | Peso | Justificativa |
|-------|------|---------------|
| F3 Criar Vistoria | 2x | Fluxo principal do sistema |
| F4 Editar + Finalizar | 2x | Conclusao da vistoria |
| F5 Cadastrar Morador | 1.5x | Sub-fluxo critico |
| F6 Upload Fotos | 1.5x | Essencial em campo |
| F7 Mapa | 1x | Navegacao espacial |
| F1 Login/Dashboard | 1x | Primeiro contato |
| F2 Listar Pontos | 1x | Fluxo comum |
| F8 Admin | 0.5x | Uso esporadico |

### Formula

```bash
# Score geral = soma ponderada
TOTAL_PESO=$((2 + 2 + 15 + 15 + 10 + 10 + 10 + 5))  # = 10.5 normalizado
# Usar inteiros x10 para evitar decimais
SCORE_GERAL=$(( (F1*10 + F2*10 + F3*20 + F4*20 + F5*15 + F6*15 + F7*10 + F8*5) / 105 ))
```

### Thresholds

| Score | Status | Significado |
|-------|--------|-------------|
| >= 85 | OK | UX fluido, atrito minimo |
| 70-84 | WARN | Atrito perceptivel, melhorias recomendadas |
| 50-69 | CRIT | Atrito significativo, impacta produtividade |
| < 50 | BLOCKER | UX impede uso efetivo em campo |

---

## Contrato JSON

```json
{
  "skill": "ux-friction",
  "timestamp": "2026-05-22T14:00:00-03:00",
  "viewport": "desktop",
  "overall_score": 78,
  "status": "WARN",
  "app_url": "http://localhost:8088",
  "flows": {
    "F1-login-dashboard": {
      "score": 90,
      "weight": 1,
      "metrics": {
        "t_login_page": 1.2,
        "t_login_submit": 0.8,
        "t_dashboard_load": 2.1,
        "dashboard_elements": 5
      },
      "findings": [],
      "screenshot": "f1-dashboard.png"
    }
  },
  "transversal": {
    "small_touch_targets": 12,
    "pages_without_loading": 3,
    "pages_with_english": 1,
    "excessive_scroll_pages": 0
  },
  "findings": [
    {
      "id": "UX-001",
      "flow": "F3",
      "severity": "ALTO",
      "title": "22 campos no formulario de vistoria",
      "description": "O formulario de criacao tem 22 campos distribuidos em 4 steps. Em campo (mobile), isso requer muito scroll e digitacao.",
      "impact": 7,
      "effort_hours": 8,
      "suggestion": "Agrupar campos relacionados, pre-preencher com defaults, considerar input por voz para observacoes"
    }
  ],
  "screenshots_dir": ".claude/audits/ux-screenshots/"
}
```

---

## Relatorio

```
## UX Friction Audit — POPRUA CRAS
**Data:** YYYY-MM-DD | **Viewport:** desktop/mobile | **Score geral:** XX/100 (STATUS)

### Scores por Fluxo

| # | Fluxo | Score | Peso | Status | Destaques |
|---|-------|-------|------|--------|-----------|
| F1 | Login/Dashboard | XX | 1x | OK/WARN/CRIT | ... |
| F3 | Criar Vistoria | XX | 2x | ... | ... |

### Top Friction Points

| # | Flow | Severidade | Problema | Sugestao |
|---|------|------------|----------|----------|

### Metricas Transversais

| Metrica | Valor | Threshold | Status |
|---------|-------|-----------|--------|

### Screenshots
[Links para screenshots em .claude/audits/ux-screenshots/]

### Quick Wins (Q1)
| # | Melhoria | Flow | Esforco | Ganho |
|---|----------|------|---------|-------|
```

---

## Tabela de Percalcos

### UXP-001 — App nao responde
- **local:** `php artisan serve --port=8088 &` + sleep 3 + revalidar.
- **prod:** NAO subir servidor. E rede/proxy — checar rota ate `sufis.pbh.gov.br`
  (RMI/PBH) e `ssh sufis "curl -sI $APP_URL/login"` direto do host.

### UXP-002 — Playwright nao instalado
- **Fix:** `npx playwright install chromium`

### UXP-003 — Login falha (credenciais)
- **Fix:** usar `UX_USER`/`UX_PASS` (default `claude.test@interno.local`). Em prod,
  se a senha for desconhecida, resetar a conta de teste via tinker (ver "Credenciais"
  acima). NAO ha mais `admin@poprua.test`/`password` em prod (era seed de dev).

### UXP-004 — Nenhum ponto no banco
- **Fix:** degradar F2, F3, F7; marcar como `degraded: true`

### UXP-005 — Modal nao abre (Alpine nao inicializou)
- **Fix:** `page.waitForFunction(() => window.Alpine)` antes de interagir; timeout 10s

### UXP-006 — Mapa nao carrega tiles
- **Fix:** `page.waitForFunction(() => document.querySelectorAll('.leaflet-tile-loaded').length > 4)` com timeout 15s

### UXP-007 — Screenshot falha por dialog aberto
- **Fix:** `page.on('dialog', d => d.dismiss())` no inicio da sessao

### UXP-008 — Formulario com steps dinamicos (Alpine x-show)
- **Fix:** para cada step, clicar no stepper e `page.waitForTimeout(300)` antes de contar campos

---

## Integracoes

### Com quality-audit
O score do `ux-friction` pode ser incorporado como D9 no quality-audit (peso 5-10%), mas e independente e pode ser rodado separadamente.

### Com foto-audit
F6 (Upload de Fotos) complementa a foto-audit mas foca na UX do upload, nao na arquitetura/performance do MediaLibrary.

### Com screenshot-audit
O ux-friction tira screenshots automaticamente; o screenshot-audit pode analisar esses screenshots para WCAG/contraste como etapa pos-auditoria.

---

## Licoes

1. Sessao compartilhada evita re-login entre fluxos — mais rapido e mais realista.
2. `waitForLoadState('networkidle')` e o melhor indicador de "pagina pronta" para metricas de tempo.
3. Formularios com Alpine x-show requerem wait entre cliques no stepper.
4. Touch targets de 44px sao o minimo WCAG para uso em campo com luvas/chuva.
5. Mensagens de erro em ingles sao atrito real para equipes CRAS (maioria nao fala ingles).
6. Scroll depth importa mais em mobile — campo e no celular.
