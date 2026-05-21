# UC-001 — Minha Equipe

**Versao:** 1.0
**Data:** 2026-05-21
**Status:** Implementado

---

## Objetivo

Permitir que cada profissional de campo defina quais colegas costumam trabalhar junto com ele. Essa lista, chamada de "Minha Equipe", agiliza o registro de zeladorias: ao criar uma nova zeladoria, os colegas marcados como equipe ja aparecem pre-selecionados na secao de participantes, evitando que o usuario precise selecionar as mesmas pessoas toda vez.

---

## Atores

| Ator | Descricao |
|------|-----------|
| **Profissional de campo** | Qualquer usuario ativo do sistema que tenha permissao para criar zeladorias. E quem monta e utiliza a equipe. |
| **Colega elegivel** | Usuario ativo que possui a permissao "participar de equipes vistoria". Aparece como opcao para ser incluido na equipe. |
| **Administrador** | Gerencia o cadastro geral de membros de equipe (Supervisores, Coordenadores, GCM, SLU, Agentes de Campo) pela area administrativa. |

---

## Pre-condicoes

1. O usuario esta autenticado no sistema.
2. O usuario possui permissao para criar zeladorias.
3. Existem outros usuarios ativos com a permissao "participar de equipes vistoria".

---

## Fluxo Principal — Montar a equipe

| Passo | Ator | Acao | Resultado |
|-------|------|------|-----------|
| 1 | Profissional | Acessa "Minha Equipe" no menu lateral (secao Operacional). | Sistema exibe a lista de todos os colegas elegiveis, em ordem alfabetica. |
| 2 | — | — | Colegas que ja fazem parte da equipe aparecem com o checkbox marcado. |
| 3 | Profissional | Marca ou desmarca os colegas desejados. | O contador "X de Y selecionados" atualiza em tempo real. |
| 4 | Profissional | Clica em "Salvar equipe". | Sistema grava a selecao e exibe mensagem de confirmacao: "Sua equipe foi atualizada — N membro(s)." |

**Regra importante:** O profissional nunca aparece na propria lista — nao e possivel adicionar a si mesmo.

---

## Fluxo Alternativo A — Equipe vazia

| Passo | Ator | Acao | Resultado |
|-------|------|------|-----------|
| 3a | Profissional | Desmarca todos os colegas e salva. | Sistema grava a equipe vazia. Nenhum participante sera pre-selecionado nas proximas zeladorias. |

## Fluxo Alternativo B — Nenhum colega cadastrado

| Passo | Ator | Acao | Resultado |
|-------|------|------|-----------|
| 1b | — | — | Sistema exibe a mensagem "Nenhum outro usuario cadastrado no sistema." |

---

## Efeito na Zeladoria

A equipe configurada impacta diretamente o formulario de zeladoria em dois momentos:

### Ao criar uma nova zeladoria

Na secao "Participantes" do formulario, os colegas da "Minha Equipe" ja aparecem **pre-selecionados** (checkbox marcado). O profissional pode ajustar — marcar ou desmarcar qualquer colega — antes de salvar.

Uma dica visual informa: *"Os pre-selecionados vem da sua Minha Equipe"*, com link direto para a pagina de configuracao.

### Ao salvar a zeladoria (criar ou editar)

O sistema **atualiza automaticamente** a "Minha Equipe" do profissional para refletir exatamente os participantes marcados naquela zeladoria. Isso significa que:

- Se o profissional adicionar um colega novo na zeladoria, esse colega passa a fazer parte da equipe.
- Se o profissional desmarcar um colega, ele sai da equipe.
- Na proxima zeladoria, a pre-selecao ja reflete a equipe mais recente.

**Excecao:** quando quem edita a zeladoria **nao e o autor original**, a equipe do autor nao e alterada. Na implementacao atual, a unica situacao em que um nao-autor interage com a zeladoria em capacidade de edicao e quando o **administrador reativa** uma zeladoria ja finalizada (permissao `reativar vistorias`). Nesse caso, o administrador apenas desbloqueia a zeladoria — quem efetivamente edita o conteudo continua sendo o autor original. A policy de autorizacao (`VistoriaPolicy::update`) garante que somente o autor pode editar; o guard no controller funciona como protecao defensiva adicional.

---

## Visualizacao dos Participantes

Na tela de detalhes de uma zeladoria, os participantes aparecem agrupados por tipo de equipe:

- Supervisores
- Coordenadores
- GCM
- SLU
- Agentes de Campo

Cada participante e exibido como uma etiqueta com seu nome.

---

## Gestao Administrativa (Cadastro de Membros)

Separado da "Minha Equipe" pessoal, existe um **cadastro administrativo** de membros de equipe, acessivel apenas por administradores (menu Administracao). Neste cadastro, o administrador pode:

| Acao | Descricao |
|------|-----------|
| **Listar** | Ver todos os membros organizados por tipo de equipe. |
| **Cadastrar** | Adicionar novo membro com nome, matricula, e-mail e tipo de equipe. |
| **Editar** | Alterar dados de um membro existente. |
| **Ativar/Desativar** | Controlar se o membro esta ativo (aparece nas opcoes) ou inativo. |
| **Remover** | Excluir um membro do cadastro. |

Os tipos de equipe disponiveis sao: **Supervisores**, **Coordenadores**, **GCM**, **SLU** e **Agentes de Campo**.

---

## Resumo das Regras de Negocio

| # | Regra |
|---|-------|
| RN1 | Apenas usuarios com permissao de criar zeladorias podem acessar "Minha Equipe". |
| RN2 | Apenas usuarios ativos com permissao "participar de equipes vistoria" aparecem como opcao. |
| RN3 | O usuario nao pode se adicionar a propria equipe. |
| RN4 | A equipe e pessoal — cada profissional tem a sua, independente dos demais. |
| RN5 | Salvar uma zeladoria atualiza automaticamente a "Minha Equipe" do autor para os participantes marcados. |
| RN6 | Se quem edita a zeladoria nao e o autor, a equipe do autor permanece inalterada. Na pratica, a unica interacao de nao-autor e a **reativacao** pelo administrador (que apenas desbloqueia a zeladoria finalizada para o autor voltar a editar). A policy garante que somente o autor pode alterar o conteudo. |
| RN7 | Equipe vazia e permitida — nenhum participante sera pre-selecionado. |

---

## Glossario

| Termo | Significado |
|-------|-------------|
| **Zeladoria** | Registro de uma visita/abordagem feita em campo (tambem chamada de "vistoria" no sistema). |
| **Participantes** | Colegas que estiveram presentes em uma zeladoria especifica. |
| **Minha Equipe** | Lista pessoal de colegas habituais de cada profissional, usada para pre-selecionar participantes. |
| **Membro de equipe** | Registro administrativo de uma pessoa vinculada a um tipo de equipe (Supervisores, GCM, etc.). |
