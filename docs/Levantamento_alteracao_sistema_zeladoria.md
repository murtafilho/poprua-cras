# Levantamento de Alteracoes — Sistema de Zeladoria

> **Origem:** `Levantamento_alteracao_sistema_zeladoria.pdf`
> **Emissor:** Aldo Alves — GFAES / Subsecretaria de Fiscalizacao / SMPU PBH
> **Data:** Belo Horizonte, 23 de marco de 2026
> **Versao MD:** convertida em 2026-05-19

---

## 1. Contexto e Justificativa

Conforme deliberado em reuniao, seguem as sugestoes de melhorias, correcoes e inclusoes para o novo sistema de Zeladoria, visando otimizar processos e garantir a integridade dos dados.

---

## 1.1 Inclusao de Participantes da Vistoria

**Descricao:** e fundamental incluir uma etapa no processo de vistoria para registrar os participantes envolvidos no cadastro.

**Participantes obrigatorios:**

- Equipe de Supervisores
- Equipe de Coordenadores
- Equipe da GCM
- Equipe SLU
- Equipe de Agentes de Campo

**Observacao:** como as equipes sao formadas diariamente, e essencial que o supervisor indique, no momento do cadastro, os integrantes especificos da equipe responsavel pela vistoria.

**Obs.** deve ter um cadastramento dos membros no sistema para que este possa puxar quando da vistoria.

**Exemplos do PDF (membros nomeados):**

- *Supervisores da Zeladoria:* Eliane Mesquita dos Santos (Mat. 16400-0), Elisane dos Santos Gomes (Mat. 356558), Helen de Fatima Moreira (Mat. 514336), Leandro Gomes Melo (Mat. 597315), Michelle Helene Gerard (Mat. 215345), Michele Leles Marinho (Mat. 122080), Rodrigo Nogueira Carneiro de Mendonca (Mat. 514281), Tatiana Maciel Figueiredo (Mat. 280055), Thiago Antunes de Siqueira (Mat. 516151).
- *Coordenadores da Zeladoria:* Hudson Abner Pinto (BM 328050-9), Rodrigo Goncalves de Morais (BM 115248-1), opcao "Nao teve acompanhamento de coordenadores".
- *Guarda Civil Municipal:* Saulo Sa, Paulo Franca, Rogerio Alves, Vicente Fonseca, Andre Silva, Wagner Souza, opcao "Nao teve acompanhamento da GCM".

---

## 1.2 Implementacao de Salvamento Parcial por Etapa

**Problema:** atualmente, o salvamento dos dados so e possivel ao concluir a ultima etapa da vistoria.

**Solucao proposta:**

- Incluir um botao **"Salvar"** em cada etapa do processo, permitindo o salvamento parcial dos dados.
- Alternativamente, implementar um salvamento automatico em intervalos regulares.

**Justificativa:** evita a perda de informacoes em caso de fechamento acidental do sistema ou interrupcoes durante o preenchimento.

---

## 1.3 Inclusao de Data e Horario Previstos para Acao de Zeladoria

**Requisito legal:** conforme portaria vigente, e obrigatoria a realizacao de vistoria previa para comunicacao do dia e horario da acao de zeladoria.

**Funcionalidades necessarias:**

- Campo especifico para registro da **data e periodo previstos** da acao de zeladoria, quando o tipo de abordagem selecionado for **"Comunicacao de Zeladoria"**.
- Geracao automatica de uma **agenda ou planilha exportavel** (formato Excel ou PDF) para programacao das equipes.
- Relatorios com filtros por:
  - Data
  - Periodo
  - Supervisor
  - Regional
  - Endereco

**Campos no formulario (proposta visual):**

```
Data prevista da zeladoria : xx/xx/xxxx      Periodo: Manha / Tarde
```

---

## 1.4 Integracao com Galeria de Fotos em Dispositivos Moveis

**Problema:** no sistema atual, ao selecionar a opcao **"Tirar foto"** em dispositivos moveis, abre-se apenas a camera, sem acesso a galeria de imagens.

**Solucao proposta:** incluir **duas opcoes distintas**:

1. **"Tirar foto"** (abre a camera).
2. **"Anexar arquivo"** (permite acesso a galeria ou explorador de arquivos).

**Obs.** em cada foto ou arquivo anexado ter a possibilidade de escrever uma legenda descritiva.

---

## 1.5 Filtro de Busca por Supervisor

**Melhoria:** incluir a opcao de busca e filtro por **supervisor responsavel** nas pesquisas de vistorias.

---

## 1.6 Filtro por Data Prevista da Acao de Zeladoria

**Funcionalidade:**

- Permitir busca e filtro por **data prevista** para acoes de zeladoria (conforme item 1.3).
- Incluir opcao de **exportacao em PDF** do roteiro de vistorias.

---

## 1.7 Ajuste de Localizacao (Ponto) da Vistoria

**Problema:** apos o cadastro da vistoria, nao ha possibilidade de ajustar o ponto de localizacao no sistema.

**Solucao proposta:** implementar funcionalidade para **editar ou ajustar o ponto da vistoria** apos o cadastro inicial.

---

## 1.8 Finalizacao de Vistoria

**Objetivo:** garantir a integridade dos dados cadastrados.

**Solucao proposta:**

- Incluir um botao **"Finalizar Vistoria"**, que bloqueie edicoes posteriores apos a conclusao.
- **Excecao:** possibilidade de complementacao mediante justificativa.

---

## 1.9 Incluir Informacoes sobre Lavacao e Tipos de Protocolo

A fim de subsidiar dados estatisticos sobre numero de lavacao e tipos de protocolos, incluir nas acoes realizadas as seguintes perguntas:

- **Houve lavacao:** (sim/nao)
- **Tipo de protocolo aplicado:** (Chuva / Frio / Normal)

---

## 1.10 Correcao de Erro no Registro de Horario

**Problema:** o sistema exibe o horario **"00:00"** em vistorias, mesmo quando preenchido corretamente.

**Acao necessaria:**

- Corrigir o bug para que o horario seja registrado conforme o input do usuario.

**Sintoma observado (PDF):** detalhe de vistoria com data `17/03/2026` mostrando hora `00:00` no cabecalho, mesmo havendo dados completos preenchidos.

---

## 1.11 Correcao de Erro no Relatorio (Erro 404)

**Problema:** ao acessar o relatorio de vistoria pelo mapa, retorna o erro **"404"** ("Categoria nao encontrada").

**Acao necessaria:**

- Verificar e corrigir a rota ou link de acesso ao relatorio.

---

Belo Horizonte, 23 de marco de 2026.
Aldo Alves — GFAES.
