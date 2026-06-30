# UC-006 — Rascunho de Zeladoria (Salvamento Parcial)

**Versão:** 1.0
**Data:** 2026-06-24
**Status:** Implementado

---

## Objetivo

Permitir que o profissional de campo **salve o progresso** do formulário de nova zeladoria antes de concluir as 7 etapas, evitando perda de dados por fechamento acidental do navegador, queda de conexão ou interrupção em campo. Atende ao requisito **1.2** do levantamento GFAES/PBH (`docs/Levantamento_alteracao_sistema_zeladoria.md`) e fecha o único item 🔴 pendente da auditoria de zeladoria.

**Referências:** `docs/AUDITORIA_Zeladoria.md` §1.2 · UC-002 (ciclo de vida pós-criação) · ADR-001 (rascunho ≠ vistoria aberta).

---

## Situação Atual

| Aspecto | Comportamento hoje |
|---------|-------------------|
| Formulário | Wizard de 7 etapas em `vistorias/create` |
| Persistência | Somente no `POST /vistorias` (submit final) |
| Proteção local | `beforeunload` se `formDirty = true` (`vistoria-form.js`) |
| Offline | Fotos enfileiradas via Service Worker; **dados do form não** |
| Rascunho server-side | **Inexistente** |

**Problema:** perda total do preenchimento se o app fechar antes do submit — comum em campo (bateria, chamada, rede instável).

---

## Atores

| Ator | Descrição |
|------|-----------|
| **Profissional de campo** | Preenche zeladoria; espera retomar de onde parou. |
| **Sistema** | Persiste rascunho server-side, restaura ao reabrir, descarta após registro definitivo. |

---

## Escopo v1

### Incluído

- Autosave com debounce (5 s após última alteração)
- Botão manual **"Salvar rascunho"** no header do formulário
- Indicador visual: `Rascunho salvo às HH:MM` / `Salvando...` / `Erro ao salvar`
- Retomada ao abrir `vistorias/create` com confirmação
- Um rascunho ativo por usuário **por contexto de ponto** (ver RN2)
- Restauração da **etapa atual** do wizard
- Limpeza automática do rascunho após `store` bem-sucedido

### Fora do escopo v1

- Rascunho na **edição** de zeladoria existente (somente criação)
- Persistir **fotos** no rascunho server-side (continuam via fila offline existente ou re-anexo no submit)
- Rascunho **multi-dispositivo** simultâneo (last-write-wins)
- Sincronização offline do payload JSON (v1 exige rede para autosave; fila local fica para v2)

---

## Modelo de Dados Proposto

### Tabela `vistorias_rascunhos`

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `id` | bigint PK | — |
| `user_id` | FK users | Dono do rascunho |
| `ponto_id` | FK pontos, nullable | Ponto existente; null se coords ainda não resolveram ponto |
| `lat` | decimal(17,14), nullable | Ancora espacial quando `ponto_id` null |
| `lng` | decimal(17,14), nullable | Ancora espacial quando `ponto_id` null |
| `payload` | jsonb | Campos do formulário serializados |
| `etapa_atual` | smallint | Índice 0–6 do wizard |
| `updated_at` | timestamp | Último salvamento |

**Índice único:** `(user_id, ponto_id)` WHERE `ponto_id IS NOT NULL`  
**Índice único alternativo:** `(user_id, lat, lng)` WHERE `ponto_id IS NULL` — coords arredondadas a 6 casas para evitar duplicatas por jitter de GPS.

### Conteúdo de `payload`

Espelho dos campos de `StoreVistoriaRequest` serializáveis (sem arquivos):

- Dados básicos: `data_abordagem`, `tipo_abordagem_id`, `resultado_acao_id`, etc.
- Flags de complexidade (16 booleanos + quantidades)
- Encaminhamentos `e1_id`…`e6_id`
- Participantes, moradores presentes, `novos_moradores` (array JSON)
- Observações, campos de zeladoria prevista, complemento de ponto
- **Não incluir:** `fotos`, `legendas_fotos` (binários)

---

## Fluxo Principal — Autosave

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Preenche qualquer campo do wizard. | JS marca `formDirty`; timer de debounce (5 s) reinicia. |
| 2 | Sistema | Timer expira sem nova edição. | `PATCH /api/vistorias/rascunho` com payload serializado + `etapa_atual`. |
| 3 | Sistema | — | Upsert em `vistorias_rascunhos`; header exibe "Rascunho salvo às HH:MM". |
| 4 | Sistema | — | `formDirty` permanece false até nova edição (evita loop de `beforeunload` após save). |

---

## Fluxo Alternativo A — Salvar Manualmente

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Clica "Salvar rascunho" no header. | Save imediato (cancela debounce pendente). |
| 2 | — | — | Mesmo endpoint e feedback do autosave. |

Botão visível em **todas as etapas** (atende requisito PDF de "Salvar em cada etapa").

---

## Fluxo Alternativo B — Retomar Rascunho

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Abre `vistorias/create` (com ou sem query string do mapa). | Backend verifica rascunho do usuário para o contexto (ponto ou lat/lng). |
| 2 | Sistema | Rascunho encontrado. | Modal: "Continuar rascunho de DD/MM/AAAA HH:MM?" — opções **Continuar** / **Descartar e começar novo**. |
| 3a | Profissional | Continuar. | Formulário pré-populado; wizard posicionado em `etapa_atual`; participantes/moradores restaurados. |
| 3b | Profissional | Descartar. | Rascunho deletado; formulário vazio (ou pré-preenchido só com dados da URL do mapa). |

**Prioridade de match:** se URL traz `ponto_id` implícito (via ponto próximo ≤ 50 m), match por `ponto_id`; senão match por lat/lng da URL; senão rascunho mais recente do usuário (único global fallback — configurável).

---

## Fluxo Alternativo C — Concluir Zeladoria Definitiva

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Submete formulário na etapa Revisar (`POST /vistorias`). | Fluxo atual de `VistoriaController::store` — inalterado. |
| 2 | Sistema | Vistoria criada com sucesso. | Delete do rascunho correspondente ao contexto `(user_id, ponto_id ou lat/lng)`. |
| 3 | — | — | Redirecionamento para `vistorias.show` como hoje. |

---

## Fluxo Alternativo D — Falha de Rede no Autosave

| Passo | Condição | Resultado |
|-------|----------|-----------|
| 1 | Request falha (timeout, offline) | Indicador: "Não foi possível salvar — tentando novamente…" |
| 2 | — | Retry exponencial (3 tentativas); `formDirty` permanece true |
| 3 | Esgotadas tentativas | Toast de aviso; dados permanecem só no client até reconexão |
| 4 | Rede volta | Próxima edição dispara novo debounce |

---

## API Proposta

| Método | Rota | Descrição |
|--------|------|-----------|
| `PATCH` | `/api/vistorias/rascunho` | Upsert rascunho (body: `payload`, `etapa_atual`, `ponto_id?`, `lat?`, `lng?`) |
| `GET` | `/api/vistorias/rascunho` | Recupera rascunho do usuário para contexto (query: `ponto_id` ou `lat`+`lng`) |
| `DELETE` | `/api/vistorias/rascunho` | Descarta rascunho explicitamente |

Middleware: `web`, `auth`, `throttle:30,1`.

Validação do payload: subset relaxado de `StoreVistoriaRequest` — **nenhum campo obrigatório** no rascunho (RN4).

---

## Camada de Serviço

Novo método em `VistoriaService` (ou `VistoriaRascunhoService` se preferir agregado separado):

```
salvarRascunho(User, array $payload, int $etapa, ?int $pontoId, ?float $lat, ?float $lng): VistoriaRascunho
recuperarRascunho(User, ?int $pontoId, ?float $lat, ?float $lng): ?VistoriaRascunho
descartarRascunho(User, ?int $pontoId, ?float $lat, ?float $lng): void
descartarPorUsuario(User): void  // após store
```

Controller fino: `Api\VistoriaRascunhoController` delegando ao service.

---

## Frontend (`vistoria-form.js`)

| Componente | Alteração |
|------------|-----------|
| `serializeForm()` | Coleta FormData → JSON (exclui files) |
| `restoreForm(data)` | Preenche campos + `novosMoradores` + checkboxes |
| `scheduleAutosave()` | Debounce 5 s |
| `saveRascunho()` | Fetch PATCH + update indicator |
| `onLoad` | GET rascunho → modal retomada |
| Header | Badge de status + botão "Salvar rascunho" |

Registrar entry no `vite.config.js` apenas se extrair módulo separado; caso contrário, estender `vistoria-form.js` existente.

---

## Resumo das Regras de Negócio

| # | Regra |
|---|-------|
| RN1 | Rascunho **não** cria registro em `vistorias` — não aparece em listagens, mapa ou relatórios. |
| RN2 | Máximo **um rascunho** por `(user_id, ponto_id)` ou `(user_id, lat, lng)` arredondados. |
| RN3 | Rascunho pertence exclusivamente ao usuário que o criou; outro usuário não pode ler nem sobrescrever. |
| RN4 | Validação relaxada no save de rascunho — campos incompletos são permitidos. |
| RN5 | Submit final (`store`) usa validação completa de `StoreVistoriaRequest` — inalterada. |
| RN6 | Rascunho é **deletado** após vistoria definitiva criada com sucesso. |
| RN7 | Fotos **não** fazem parte do payload de rascunho v1. |
| RN8 | Autosave debounce padrão: **5 segundos**; configurável via parâmetro `rascunho_debounce_ms`. |
| RN9 | Retomada restaura **etapa do wizard** além dos campos. |
| RN10 | Rascunho expira após **30 dias** sem atualização (job `rascunhos:limpar`; parâmetro `rascunho_dias_expiracao`). |

---

## Critérios de Aceite

- [ ] Profissional preenche etapa 1–3, fecha aba, reabre create no mesmo ponto → dados restaurados após confirmar.
- [ ] Indicador "Rascunho salvo às HH:MM" aparece após autosave bem-sucedido.
- [ ] Botão "Salvar rascunho" persiste imediatamente sem esperar debounce.
- [ ] Submit final cria vistoria normalmente e remove rascunho. *(coberto por `VistoriaRascunhoControllerTest::test_store_vistoria_deletes_rascunho`)*
- [ ] "Descartar e começar novo" apaga rascunho e exibe form limpo. *(API: `test_delete_discards_rascunho`; UI manual)*
- [ ] Dois usuários no mesmo ponto mantêm rascunhos independentes. *(manual / policy por `user_id`)*
- [ ] Falha de rede não corrompe rascunho server-side anterior. *(manual)*
- [x] Testes Feature: upsert, retrieve, delete, policy (owner only), cleanup on store — `VistoriaRascunhoControllerTest`.

---

## Estimativa de Implementação

| Camada | Esforço |
|--------|---------|
| Migration + Model + Service | 2 h |
| API Controller + Policy + Requests | 1,5 h |
| Frontend autosave + restore + UI | 3 h |
| Testes | 1,5 h |
| **Total** | **6–8 h** |

Alinhado à estimativa da auditoria.

---

## Riscos e Mitigações

| Risco | Mitigação |
|-------|-----------|
| Payload JSON diverge do form após refactor | Teste de round-trip serialize/restore; versionar `payload_version` no JSON |
| Conflito rascunho vs ponto criado no meio do fluxo | Ao resolver `ponto_id` no store, migrar rascunho de lat/lng para ponto_id |
| Payload grande (moradores inline) | jsonb sem limite prático; monitorar tamanho médio |
| Usuário confunde rascunho com zeladoria salva | Copy claro no modal; rascunho nunca listado em `/vistorias` |

---

## Glossário

| Termo | Significado |
|-------|-------------|
| **Rascunho** | Persistência temporária server-side do formulário de criação, anterior à vistoria definitiva. |
| **Autosave** | Salvamento automático após debounce de inatividade. |
| **Contexto** | Par `(ponto_id)` ou `(lat, lng)` que identifica unicamente um rascunho em andamento. |
| **Etapa** | Uma das 7 abas do wizard (Dados → Revisar). |

---

## Implementado em

| Componente | Local |
|-----------|-------|
| Migration | `database/migrations/2026_06_24_000000_create_vistorias_rascunhos_table.php` |
| Model | `app/Models/VistoriaRascunho.php` |
| Service | `app/Services/VistoriaRascunhoService.php` |
| API | `app/Http/Controllers/Api/VistoriaRascunhoController.php` |
| Policy | `app/Policies/VistoriaRascunhoPolicy.php` |
| Frontend | `resources/js/vistoria-form.js` + botão no header de `vistorias/create` |
| Testes | `tests/Feature/Api/VistoriaRascunhoControllerTest.php` (7 testes) |
| Limpeza pós-store | `VistoriaController::store` |

---

## Próximo Passo (fase 3, opcional)

- Sincronização offline do payload quando SW detectar reconexão
- Incluir fotos no payload de rascunho (ver dívida UC-007)
