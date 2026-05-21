# UC-002 — Finalizacao de Zeladoria e Habilitacao de Imagens para Relatorio Publico

**Versao:** 1.0
**Data:** 2026-05-21
**Status:** Implementado

---

## Objetivo

Descrever o ciclo de vida completo de uma zeladoria apos sua criacao, com foco nos fluxos de **finalizacao**, **habilitacao de fotografias para relatorio publico** e **geracao do relatorio impresso** (formato A4) que compoe o processo administrativo.

---

## Atores

| Ator | Descricao |
|------|-----------|
| **Profissional de campo** (owner) | Autor da zeladoria. Unico que pode editar e finalizar enquanto a zeladoria esta aberta. |
| **Administrador** | Usuario com permissoes administrativas. Pode reativar zeladoria finalizada, cancelar zeladoria finalizada e — via permissao `reativar vistorias` — devolver a zeladoria ao estado aberto para que o owner a edite novamente. |
| **Qualquer usuario autenticado** | Pode visualizar zeladorias, acessar o relatorio e adicionar complementacao a zeladorias finalizadas. |

---

## Pre-condicoes

1. O usuario esta autenticado no sistema.
2. Existe pelo menos uma zeladoria cadastrada.

---

## Maquina de Estados

```
                   owner                  admin
  [ABERTA] ──────finalizar──────▶ [FINALIZADA]
     │                                │    │
     │ owner cancela                  │    │ admin cancela
     ▼                                │    ▼
  [CANCELADA] ◀───────────────────────┘ [CANCELADA]
                                  │
                    admin reativa │
                                  ▼
                              [ABERTA]
```

- **ABERTA** — editavel pelo owner; fotos podem ser adicionadas, removidas e marcadas como publicas.
- **FINALIZADA** — bloqueada para edicao de conteudo; permite complementacao textual, geracao de relatorio e habilitacao/desabilitacao de fotos para o relatorio publico.
- **CANCELADA** — estado terminal; nenhuma acao e permitida alem de visualizacao.

---

## Fluxo Principal — Finalizar Zeladoria

| Passo | Ator | Acao | Resultado |
|-------|------|------|-----------|
| 1 | Owner | Acessa a pagina de detalhes da zeladoria (`vistorias.show`). | Sistema exibe todos os dados, participantes, moradores, fotos e o botao "Finalizar Zeladoria". |
| 2 | Owner | Clica em "Finalizar Zeladoria" e confirma no dialogo de confirmacao. | Sistema grava: `finalizada = true`, `finalizada_em = agora`, `finalizada_por = usuario logado`. |
| 3 | — | — | O botao "Editar" desaparece. Aparecem os botoes "Relatorio", "Complementar" e, se o usuario tiver permissao, "Reativar para Edicao". |
| 4 | — | — | A zeladoria fica protegida contra alteracao de conteudo (Policy `update` retorna `false`). |

**Variante 4a — Salvar e Finalizar (via formulario de edicao):**

| Passo | Ator | Acao | Resultado |
|-------|------|------|-----------|
| 4a.1 | Owner | No formulario de edicao, marca o checkbox "Finalizar apos salvar" e clica em "Salvar". | Sistema salva todas as alteracoes e, em seguida, finaliza a zeladoria na mesma requisicao. |

---

## Fluxo Alternativo A — Habilitar Imagens para Relatorio Publico

Este fluxo **deve ser executado antes da finalizacao**. A API `toggle-publica` exige autorizacao de edicao (`Policy::update`), que e negada quando a zeladoria esta finalizada. Portanto, o owner deve marcar as fotos como publicas enquanto a zeladoria esta aberta.

| Passo | Ator | Acao | Resultado |
|-------|------|------|-----------|
| 1 | Owner | Acessa o formulario de edicao da zeladoria (aba "Fotos"). | Sistema exibe as fotos ja cadastradas em grid, cada uma com um icone indicando seu estado: cadeado (privada) ou olho aberto (publica). |
| 2 | Owner | Clica no icone de visibilidade de uma foto. | Sistema envia requisicao `POST /api/vistorias/{id}/fotos/{mediaId}/toggle-publica` e alterna a propriedade `publica` da foto. O icone atualiza em tempo real. |
| 3 | Owner | Repete o passo 2 para quantas fotos desejar. | Cada foto fica marcada individualmente. |
| 4 | Owner | Apos marcar todas as fotos desejadas, finaliza a zeladoria (Fluxo Principal). | As fotos marcadas como publicas serao as unicas exibidas no relatorio impresso (A4). |

**Nota:** a alternancia da visibilidade e uma operacao atomica via API — nao depende de salvar o formulario.

**Caso precise ajustar fotos apos finalizacao:** o administrador deve reativar a zeladoria (Fluxo D), o owner ajusta a visibilidade das fotos e finaliza novamente.

**Significado dos estados de visibilidade:**

| Estado | Icone | Comportamento |
|--------|-------|---------------|
| **Privada** | Cadeado | Visivel apenas na aplicacao (detalhes e formulario). Nao aparece no relatorio impresso. |
| **Publica** | Olho aberto | Visivel na aplicacao **e** no relatorio impresso que compoe o processo administrativo. |

---

## Fluxo Alternativo B — Gerar Relatorio para Processo Administrativo

| Passo | Ator | Acao | Resultado |
|-------|------|------|-----------|
| 1 | Qualquer usuario | Acessa a pagina de detalhes da zeladoria e clica em "Relatorio". | Sistema exibe o relatorio em tela (`vistorias.report`) com todos os dados e todas as fotos. |
| 2 | Usuario | Clica em "Imprimir" (ou acessa diretamente `vistorias.report.print`). | Sistema gera a versao A4 do relatorio, filtrando as fotos para exibir **somente as marcadas como publicas** (`publica = true`). |
| 3 | — | — | O relatorio impresso inclui: cabecalho institucional, dados completos da zeladoria, fotos publicas, hash de verificacao (SHA-256) e rodape com numero do relatorio e paginacao. |
| 4 | Usuario | Imprime ou salva como PDF. | O documento esta pronto para anexacao ao processo administrativo. |

**Regra:** o relatorio pode ser gerado a qualquer momento, mas so deve ser utilizado para compor processo apos a finalizacao da zeladoria, garantindo que os dados estao consolidados.

---

## Fluxo Alternativo C — Complementar Zeladoria Finalizada

Permite adicionar informacoes textuais a uma zeladoria ja finalizada sem reabrir para edicao completa.

| Passo | Ator | Acao | Resultado |
|-------|------|------|-----------|
| 1 | Qualquer usuario | Na pagina de detalhes de uma zeladoria finalizada, preenche o campo "Complementacao" (minimo 10 caracteres) e clica em "Enviar". | Sistema valida a justificativa. |
| 2 | — | — | O texto e **apensado** ao campo `observacao` existente, precedido por separador com data/hora e nome do usuario. |
| 3 | — | — | A zeladoria permanece finalizada. O complemento fica visivel nos detalhes e no relatorio. |

**Formato do apensamento:**

```
--- Complementacao em DD/MM/AAAA HH:MM por Nome do Usuario ---
Justificativa: [texto digitado]
```

---

## Fluxo Alternativo D — Reativar Zeladoria Finalizada

Unica situacao prevista em que um nao-owner interage com a zeladoria em capacidade administrativa. O administrador **nao edita** o conteudo — apenas desbloqueia para que o owner volte a editar.

| Passo | Ator | Acao | Resultado |
|-------|------|------|-----------|
| 1 | Administrador | Na pagina de detalhes de uma zeladoria finalizada, clica em "Reativar para Edicao". | Sistema verifica que o usuario possui permissao `reativar vistorias`. |
| 2 | — | — | Sistema grava: `finalizada = false`, `finalizada_em = null`, `finalizada_por = null`. |
| 3 | — | — | A zeladoria volta ao estado ABERTA. O botao "Editar" reaparece — mas somente o **owner original** pode utiliza-lo (Policy `update` exige `user_id === usuario logado`). |

**Importante:** a reativacao nao altera a autoria. Apos a reativacao, o administrador nao pode editar o conteudo — apenas o autor original pode.

---

## Fluxo Alternativo E — Cancelar Zeladoria

| Passo | Ator | Acao | Resultado |
|-------|------|------|-----------|
| 1a | Owner | Cancela uma zeladoria **aberta**. | Sistema grava: `cancelada = true`, `cancelada_em = agora`, `cancelada_por = usuario logado`. |
| 1b | Administrador | Cancela uma zeladoria **finalizada** (requer permissao `cancelar vistorias`). | Mesmo efeito do 1a. |
| 2 | — | — | Estado terminal. Nao e possivel editar, reativar ou finalizar. O registro e mantido para auditoria. |

**Importante:** apos a finalizacao, o owner **nao pode** cancelar a zeladoria. A Policy `cancelar` retorna `false` para o owner quando `finalizada = true`. Somente um administrador com permissao `cancelar vistorias` pode cancelar uma zeladoria finalizada.

---

## Resumo das Regras de Negocio

| # | Regra |
|---|-------|
| RN1 | Somente o owner pode editar e finalizar uma zeladoria aberta. |
| RN2 | Finalizar grava `finalizada = true` com timestamp e identificacao de quem finalizou. |
| RN3 | Zeladoria finalizada nao pode ser editada — a Policy `update` retorna `false` quando `finalizada = true`. |
| RN4 | O owner pode finalizar diretamente (botao "Finalizar") ou via checkbox "Finalizar apos salvar" no formulario de edicao. |
| RN5 | Cada foto possui a propriedade `publica` (default `false`). Apenas fotos com `publica = true` aparecem no relatorio impresso (A4). |
| RN6 | A alternancia de visibilidade da foto exige autorizacao de edicao (`Policy::update`) — portanto so e possivel enquanto a zeladoria esta aberta. O owner deve marcar as fotos desejadas antes de finalizar. Para ajustar apos finalizacao, e necessario reativar (Fluxo D). |
| RN7 | O relatorio impresso (formato A4) inclui hash de verificacao para integridade documental. |
| RN8 | Complementacao textual pode ser adicionada a zeladorias finalizadas por qualquer usuario autenticado, sem reabrir para edicao. |
| RN9 | Reativacao (FINALIZADA → ABERTA) e exclusiva de administradores com permissao `reativar vistorias`. Apos reativacao, somente o owner original pode editar. |
| RN10 | Cancelamento e estado terminal e irreversivel. Owner so pode cancelar zeladorias **abertas** — apos a finalizacao, perde essa capacidade. Somente o administrador (com permissao `cancelar vistorias`) pode cancelar zeladorias finalizadas. |
| RN11 | Todas as transicoes de estado (finalizacao, reativacao, cancelamento) registram timestamp e usuario responsavel para trilha de auditoria. |

---

## Matriz de Permissoes por Estado

| Acao | ABERTA | FINALIZADA | CANCELADA |
|------|--------|------------|-----------|
| Visualizar | Todos | Todos | Todos |
| Editar conteudo | Owner | Ninguem | Ninguem |
| Finalizar | Owner | — | — |
| Reativar | — | Admin (`reativar vistorias`) | — |
| Cancelar | Owner | Somente Admin (`cancelar vistorias`); owner nao pode | — |
| Complementar | — | Todos | — |
| Toggle foto publica | Owner | Ninguem (requer reativacao) | Ninguem |
| Gerar relatorio | Todos | Todos | Todos |
| Imprimir relatorio A4 | Todos | Todos | Todos |

---

## Glossario

| Termo | Significado |
|-------|-------------|
| **Zeladoria** | Registro de uma visita/abordagem feita em campo (sinonimo de "vistoria" no sistema). |
| **Finalizar** | Bloquear a zeladoria para edicao, consolidando os dados para geracao de relatorio. |
| **Reativar** | Desbloquear zeladoria finalizada, devolvendo-a ao estado aberto para que o owner a edite. |
| **Complementar** | Adicionar nota textual a zeladoria finalizada sem reabrir para edicao completa. |
| **Foto publica** | Fotografia marcada para inclusao no relatorio impresso que compoe o processo administrativo. |
| **Foto privada** | Fotografia visivel apenas na aplicacao, omitida do relatorio impresso. |
| **Relatorio impresso** | Documento em formato A4 contendo os dados da zeladoria e somente as fotos publicas, destinado a compor processo administrativo. |
| **Hash de verificacao** | Codigo SHA-256 incluido no relatorio impresso para comprovacao de integridade do documento. |
| **Owner** | Autor original da zeladoria (`user_id`). Unico com permissao para editar o conteudo. |
