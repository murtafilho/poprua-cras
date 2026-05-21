# UC-001 — Minha Equipe

**Versão:** 1.0
**Data:** 2026-05-21
**Status:** Implementado

---

## Objetivo

Permitir que cada profissional de campo defina quais colegas costumam trabalhar junto com ele. Essa lista, chamada de "Minha Equipe", agiliza o registro de zeladorias: ao criar uma nova zeladoria, os colegas marcados como equipe já aparecem pré-selecionados na seção de participantes, evitando que o usuário precise selecionar as mesmas pessoas toda vez.

---

## Atores

| Ator | Descrição |
|------|-----------|
| **Profissional de campo** | Qualquer usuário ativo do sistema que tenha permissão para criar zeladorias. É quem monta e utiliza a equipe. |
| **Colega elegível** | Usuário ativo que possui a permissão "participar de equipes vistoria". Aparece como opção para ser incluído na equipe. |
| **Administrador** | Gerencia o cadastro geral de membros de equipe (Supervisores, Coordenadores, GCM, SLU, Agentes de Campo) pela área administrativa. |

---

## Pré-condições

1. O usuário está autenticado no sistema.
2. O usuário possui permissão para criar zeladorias.
3. Existem outros usuários ativos com a permissão "participar de equipes vistoria".

---

## Fluxo Principal — Montar a equipe

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Acessa "Minha Equipe" no menu lateral (seção Operacional). | Sistema exibe a lista de todos os colegas elegíveis, em ordem alfabética. |
| 2 | — | — | Colegas que já fazem parte da equipe aparecem com o checkbox marcado. |
| 3 | Profissional | Marca ou desmarca os colegas desejados. | O contador "X de Y selecionados" atualiza em tempo real. |
| 4 | Profissional | Clica em "Salvar equipe". | Sistema grava a seleção e exibe mensagem de confirmação: "Sua equipe foi atualizada — N membro(s)." |

**Regra importante:** O profissional nunca aparece na própria lista — não é possível adicionar a si mesmo.

---

## Fluxo Alternativo A — Equipe vazia

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 3a | Profissional | Desmarca todos os colegas e salva. | Sistema grava a equipe vazia. Nenhum participante será pré-selecionado nas próximas zeladorias. |

## Fluxo Alternativo B — Nenhum colega cadastrado

| Passo | Ator | Ação | Resultado |
|-------|------|------|-----------|
| 1b | — | — | Sistema exibe a mensagem "Nenhum outro usuário cadastrado no sistema." |

---

## Efeito na Zeladoria

A equipe configurada impacta diretamente o formulário de zeladoria em dois momentos:

### Ao criar uma nova zeladoria

Na seção "Participantes" do formulário, os colegas da "Minha Equipe" já aparecem **pré-selecionados** (checkbox marcado). O profissional pode ajustar — marcar ou desmarcar qualquer colega — antes de salvar.

Uma dica visual informa: *"Os pré-selecionados vêm da sua Minha Equipe"*, com link direto para a página de configuração.

### Ao salvar a zeladoria (criar ou editar)

O sistema **atualiza automaticamente** a "Minha Equipe" do profissional para refletir exatamente os participantes marcados naquela zeladoria. Isso significa que:

- Se o profissional adicionar um colega novo na zeladoria, esse colega passa a fazer parte da equipe.
- Se o profissional desmarcar um colega, ele sai da equipe.
- Na próxima zeladoria, a pré-seleção já reflete a equipe mais recente.

**Exceção:** quando quem edita a zeladoria **não é o autor original**, a equipe do autor não é alterada. Na implementação atual, a única situação em que um não-autor interage com a zeladoria em capacidade de edição é quando o **administrador reativa** uma zeladoria já finalizada (permissão `reativar vistorias`). Nesse caso, o administrador apenas desbloqueia a zeladoria — quem efetivamente edita o conteúdo continua sendo o autor original. A policy de autorização (`VistoriaPolicy::update`) garante que somente o autor pode editar; o guard no controller funciona como proteção defensiva adicional.

---

## Visualização dos Participantes

Na tela de detalhes de uma zeladoria, os participantes aparecem agrupados por tipo de equipe:

- Supervisores
- Coordenadores
- GCM
- SLU
- Agentes de Campo

Cada participante é exibido como uma etiqueta com seu nome.

---

## Gestão Administrativa (Cadastro de Membros)

Separado da "Minha Equipe" pessoal, existe um **cadastro administrativo** de membros de equipe, acessível apenas por administradores (menu Administração). Neste cadastro, o administrador pode:

| Ação | Descrição |
|------|-----------|
| **Listar** | Ver todos os membros organizados por tipo de equipe. |
| **Cadastrar** | Adicionar novo membro com nome, matrícula, e-mail e tipo de equipe. |
| **Editar** | Alterar dados de um membro existente. |
| **Ativar/Desativar** | Controlar se o membro está ativo (aparece nas opções) ou inativo. |
| **Remover** | Excluir um membro do cadastro. |

Os tipos de equipe disponíveis são: **Supervisores**, **Coordenadores**, **GCM**, **SLU** e **Agentes de Campo**.

---

## Resumo das Regras de Negócio

| # | Regra |
|---|-------|
| RN1 | Apenas usuários com permissão de criar zeladorias podem acessar "Minha Equipe". |
| RN2 | Apenas usuários ativos com permissão "participar de equipes vistoria" aparecem como opção. |
| RN3 | O usuário não pode se adicionar à própria equipe. |
| RN4 | A equipe é pessoal — cada profissional tem a sua, independente dos demais. |
| RN5 | Salvar uma zeladoria atualiza automaticamente a "Minha Equipe" do autor para os participantes marcados. |
| RN6 | Se quem edita a zeladoria não é o autor, a equipe do autor permanece inalterada. Na prática, a única interação de não-autor é a **reativação** pelo administrador (que apenas desbloqueia a zeladoria finalizada para o autor voltar a editar). A policy garante que somente o autor pode alterar o conteúdo. |
| RN7 | Equipe vazia é permitida — nenhum participante será pré-selecionado. |

---

## Glossário

| Termo | Significado |
|-------|-------------|
| **Zeladoria** | Registro de uma visita/abordagem feita em campo (também chamada de "vistoria" no sistema). |
| **Participantes** | Colegas que estiveram presentes em uma zeladoria específica. |
| **Minha Equipe** | Lista pessoal de colegas habituais de cada profissional, usada para pré-selecionar participantes. |
| **Membro de equipe** | Registro administrativo de uma pessoa vinculada a um tipo de equipe (Supervisores, GCM, etc.). |
