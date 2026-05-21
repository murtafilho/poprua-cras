# UC-002 — Finalização de Zeladoria e Habilitação de Imagens para Relatório Público

**Versão:** 1.0
**Data:** 2026-05-21
**Status:** Implementado

---

## Objetivo

Descrever o ciclo de vida completo de uma zeladoria após sua criação, com foco nos fluxos de **finalização**, **habilitação de fotografias para relatório público** e **geração do relatório impresso** (formato A4) que compõe o processo administrativo.

---

## Atores

| Ator | Descrição |
|------|-----------|
| **Profissional de campo** (owner) | Autor da zeladoria. Único que pode editar e finalizar enquanto a zeladoria está aberta. |
| **Administrador** | Usuário com permissões administrativas. Pode reativar zeladoria finalizada, cancelar zeladoria finalizada e — via permissão `reativar vistorias` — devolver a zeladoria ao estado aberto para que o owner a edite novamente. |
| **Qualquer usuário autenticado** | Pode visualizar zeladorias, acessar o relatório e adicionar complementação a zeladorias finalizadas. |

---

## Pré-condições

1. O usuário está autenticado no sistema.
2. Existe pelo menos uma zeladoria cadastrada.

---

## Máquina de Estados

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

- **ABERTA** — editável pelo owner; fotos podem ser adicionadas, removidas e marcadas como públicas.
- **FINALIZADA** — bloqueada para edição de conteúdo; permite complementação textual e geração de relatório.
- **CANCELADA** — estado terminal; nenhuma ação é permitida além de visualização.

---

## Fluxo Principal — Finalizar Zeladoria

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Owner | Acessa a página de detalhes da zeladoria (`vistorias.show`). | Sistema exibe todos os dados, participantes, moradores, fotos e o botão "Finalizar Zeladoria". |
| 2 | Owner | Clica em "Finalizar Zeladoria" e confirma no diálogo de confirmação. | Sistema grava: `finalizada = true`, `finalizada_em = agora`, `finalizada_por = usuário logado`. |
| 3 | — | — | O botão "Editar" desaparece. Aparecem os botões "Relatório", "Complementar" e, se o usuário tiver permissão, "Reativar para Edição". |
| 4 | — | — | A zeladoria fica protegida contra alteração de conteúdo (Policy `update` retorna `false`). |

**Variante 4a — Salvar e Finalizar (via formulário de edição):**

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 4a.1 | Owner | No formulário de edição, marca o checkbox "Finalizar após salvar" e clica em "Salvar". | Sistema salva todas as alterações e, em seguida, finaliza a zeladoria na mesma requisição. |

---

## Fluxo Alternativo A — Habilitar Imagens para Relatório Público

Este fluxo **deve ser executado antes da finalização**. A API `toggle-publica` exige autorização de edição (`Policy::update`), que é negada quando a zeladoria está finalizada. Portanto, o owner deve marcar as fotos como públicas enquanto a zeladoria está aberta.

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Owner | Acessa o formulário de edição da zeladoria (aba "Fotos"). | Sistema exibe as fotos já cadastradas em grid, cada uma com um ícone indicando seu estado: cadeado (privada) ou olho aberto (pública). |
| 2 | Owner | Clica no ícone de visibilidade de uma foto. | Sistema envia requisição `POST /api/vistorias/{id}/fotos/{mediaId}/toggle-publica` e alterna a propriedade `publica` da foto. O ícone atualiza em tempo real. |
| 3 | Owner | Repete o passo 2 para quantas fotos desejar. | Cada foto fica marcada individualmente. |
| 4 | Owner | Após marcar todas as fotos desejadas, finaliza a zeladoria (Fluxo Principal). | As fotos marcadas como públicas serão as únicas exibidas no relatório impresso (A4). |

**Nota:** a alternância da visibilidade é uma operação atômica via API — não depende de salvar o formulário.

**Caso precise ajustar fotos após finalização:** o administrador deve reativar a zeladoria (Fluxo D), o owner ajusta a visibilidade das fotos e finaliza novamente.

**Significado dos estados de visibilidade:**

| Estado | Ícone | Comportamento |
|--------|-------|---------------|
| **Privada** | Cadeado | Visível apenas na aplicação (detalhes e formulário). Não aparece no relatório impresso. |
| **Pública** | Olho aberto | Visível na aplicação **e** no relatório impresso que compõe o processo administrativo. |

---

## Fluxo Alternativo B — Gerar Relatório para Processo Administrativo

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Qualquer usuário | Acessa a página de detalhes da zeladoria e clica em "Relatório". | Sistema exibe o relatório em tela (`vistorias.report`) com todos os dados e todas as fotos. |
| 2 | Usuário | Clica em "Imprimir" (ou acessa diretamente `vistorias.report.print`). | Sistema gera a versão A4 do relatório, filtrando as fotos para exibir **somente as marcadas como públicas** (`publica = true`). |
| 3 | — | — | O relatório impresso inclui: cabeçalho institucional, dados completos da zeladoria, fotos públicas, hash de verificação (SHA-256) e rodapé com número do relatório e paginação. |
| 4 | Usuário | Imprime ou salva como PDF. | O documento está pronto para anexação ao processo administrativo. |

**Regra:** o relatório pode ser gerado a qualquer momento, mas só deve ser utilizado para compor processo após a finalização da zeladoria, garantindo que os dados estão consolidados.

---

## Fluxo Alternativo C — Complementar Zeladoria Finalizada

Permite adicionar informações textuais a uma zeladoria já finalizada sem reabrir para edição completa.

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Qualquer usuário | Na página de detalhes de uma zeladoria finalizada, preenche o campo "Complementação" (mínimo 10 caracteres) e clica em "Enviar". | Sistema valida a justificativa. |
| 2 | — | — | O texto é **apensado** ao campo `observacao` existente, precedido por separador com data/hora e nome do usuário. |
| 3 | — | — | A zeladoria permanece finalizada. O complemento fica visível nos detalhes e no relatório. |

**Formato do apensamento:**

```
--- Complementação em DD/MM/AAAA HH:MM por Nome do Usuário ---
Justificativa: [texto digitado]
```

---

## Fluxo Alternativo D — Reativar Zeladoria Finalizada

Única situação prevista em que um não-owner interage com a zeladoria em capacidade administrativa. O administrador **não edita** o conteúdo — apenas desbloqueia para que o owner volte a editar.

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Administrador | Na página de detalhes de uma zeladoria finalizada, clica em "Reativar para Edição". | Sistema verifica que o usuário possui permissão `reativar vistorias`. |
| 2 | — | — | Sistema grava: `finalizada = false`, `finalizada_em = null`, `finalizada_por = null`. |
| 3 | — | — | A zeladoria volta ao estado ABERTA. O botão "Editar" reaparece — mas somente o **owner original** pode utilizá-lo (Policy `update` exige `user_id === usuário logado`). |

**Importante:** a reativação não altera a autoria. Após a reativação, o administrador não pode editar o conteúdo — apenas o autor original pode.

---

## Fluxo Alternativo E — Cancelar Zeladoria

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1a | Owner | Cancela uma zeladoria **aberta**. | Sistema grava: `cancelada = true`, `cancelada_em = agora`, `cancelada_por = usuário logado`. |
| 1b | Administrador | Cancela uma zeladoria **finalizada** (requer permissão `cancelar vistorias`). | Mesmo efeito do 1a. |
| 2 | — | — | Estado terminal. Não é possível editar, reativar ou finalizar. O registro é mantido para auditoria. |

**Importante:** após a finalização, o owner **não pode** cancelar a zeladoria. A Policy `cancelar` retorna `false` para o owner quando `finalizada = true`. Somente um administrador com permissão `cancelar vistorias` pode cancelar uma zeladoria finalizada.

---

## Resumo das Regras de Negócio

| # | Regra |
|---|-------|
| RN1 | Somente o owner pode editar e finalizar uma zeladoria aberta. |
| RN2 | Finalizar grava `finalizada = true` com timestamp e identificação de quem finalizou. |
| RN3 | Zeladoria finalizada não pode ser editada — a Policy `update` retorna `false` quando `finalizada = true`. |
| RN4 | O owner pode finalizar diretamente (botão "Finalizar") ou via checkbox "Finalizar após salvar" no formulário de edição. |
| RN5 | Cada foto possui a propriedade `publica` (default `false`). Apenas fotos com `publica = true` aparecem no relatório impresso (A4). |
| RN6 | A alternância de visibilidade da foto exige autorização de edição (`Policy::update`) — portanto só é possível enquanto a zeladoria está aberta. O owner deve marcar as fotos desejadas antes de finalizar. Para ajustar após finalização, é necessário reativar (Fluxo D). |
| RN7 | O relatório impresso (formato A4) inclui hash de verificação para integridade documental. |
| RN8 | Complementação textual pode ser adicionada a zeladorias finalizadas por qualquer usuário autenticado, sem reabrir para edição. |
| RN9 | Reativação (FINALIZADA → ABERTA) é exclusiva de administradores com permissão `reativar vistorias`. Após reativação, somente o owner original pode editar. |
| RN10 | Cancelamento é estado terminal e irreversível. Owner só pode cancelar zeladorias **abertas** — após a finalização, perde essa capacidade. Somente o administrador (com permissão `cancelar vistorias`) pode cancelar zeladorias finalizadas. |
| RN11 | Todas as transições de estado (finalização, reativação, cancelamento) registram timestamp e usuário responsável para trilha de auditoria. |

---

## Matriz de Permissões por Estado

| Ação | ABERTA | FINALIZADA | CANCELADA |
|------|--------|------------|-----------|
| Visualizar | Todos | Todos | Todos |
| Editar conteúdo | Owner | Ninguém | Ninguém |
| Finalizar | Owner | — | — |
| Reativar | — | Admin (`reativar vistorias`) | — |
| Cancelar | Owner | Somente Admin (`cancelar vistorias`); owner não pode | — |
| Complementar | — | Todos | — |
| Toggle foto pública | Owner | Ninguém (requer reativação) | Ninguém |
| Gerar relatório | Todos | Todos | Todos |
| Imprimir relatório A4 | Todos | Todos | Todos |

---

## Glossário

| Termo | Significado |
|-------|-------------|
| **Zeladoria** | Registro de uma visita/abordagem feita em campo (sinônimo de "vistoria" no sistema). |
| **Finalizar** | Bloquear a zeladoria para edição, consolidando os dados para geração de relatório. |
| **Reativar** | Desbloquear zeladoria finalizada, devolvendo-a ao estado aberto para que o owner a edite. |
| **Complementar** | Adicionar nota textual a zeladoria finalizada sem reabrir para edição completa. |
| **Foto pública** | Fotografia marcada para inclusão no relatório impresso que compõe o processo administrativo. |
| **Foto privada** | Fotografia visível apenas na aplicação, omitida do relatório impresso. |
| **Relatório impresso** | Documento em formato A4 contendo os dados da zeladoria e somente as fotos públicas, destinado a compor processo administrativo. |
| **Hash de verificação** | Código SHA-256 incluído no relatório impresso para comprovação de integridade do documento. |
| **Owner** | Autor original da zeladoria (`user_id`). Único com permissão para editar o conteúdo. |
