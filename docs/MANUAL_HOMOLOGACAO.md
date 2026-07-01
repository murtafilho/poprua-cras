# Manual de Homologação — SIZEM

**Sistema:** POPRUA CRAS (Zeladoria Urbana — PBH)  
**Versão do manual:** 1.0  
**Data:** 2026-06-30  
**Público:** profissionais de campo, supervisores, gestores e equipe de TI que validam o sistema antes do uso em produção.

---

## 1. Objetivo

Este manual orienta a **homologação funcional** do POPRUA CRAS: conjunto de testes manuais que o usuário executa no ambiente indicado pela coordenação, registrando se cada item funcionou conforme esperado.

A homologação **não substitui** o treinamento de uso do sistema, mas garante que os fluxos críticos de trabalho (mapa, zeladoria, moradores, fotos e relatórios) estão adequados à rotina do CRAS.

**Ao final**, a equipe responsável assina o **Termo de Aceite** (seção 8) ou devolve a lista de falhas para correção.

---

## 2. Ambiente e acesso

| Item | Informação |
|------|------------|
| **URL de homologação/produção** | `https://sufis.pbh.gov.br/ginfi/poprua-cras/public` |
| **Navegadores recomendados** | Chrome ou Edge (versão atual) no computador; Chrome no celular Android |
| **Dispositivo móvel** | Recomendado para testes de câmera, GPS e uso em campo |
| **Login** | Usuário e senha fornecidos pelo administrador do sistema |
| **Suporte** | Registrar dúvidas e falhas no canal definido pela coordenação (e-mail/grupo) |

### Requisitos do dispositivo (testes de campo)

- Conexão à internet (Wi‑Fi ou 4G) — exceto nos itens marcados **offline**
- Permissão de **localização (GPS)** quando testar o mapa
- Permissão de **câmera** para fotos
- Para teste offline: modo avião ou rede desligada **após** abrir o formulário de zeladoria

---

## 3. Perfis de usuário

Execute os módulos conforme o perfil que você recebeu. Se tiver apenas um login, faça todos os itens aplicáveis ao **profissional de campo**.

| Perfil | O que validar |
|--------|----------------|
| **Profissional de campo** | Módulos 1 a 7, 9 e 10 |
| **Supervisor / gestor** | Módulos 1, 8 e 10 |
| **Administrador** | Módulo 11 (e demais, se desejado) |

---

## 4. Como registrar cada teste

Para cada item da seção 6, preencha:

| Coluna | Preenchimento |
|--------|----------------|
| **ID** | Código do roteiro (ex.: H-004) |
| **Resultado** | `OK` — funcionou como descrito · `Falha` — não funcionou ou comportamento diferente · `N/A` — não aplicável ao seu perfil |
| **Observações** | Descreva o que aconteceu, mensagem de erro, print (anexe separadamente) |
| **Testador** | Seu nome |
| **Data** | Dia em que executou |

**Dica:** em caso de falha, anote **o que você fez**, **o que esperava** e **o que apareceu na tela**.

---

## 5. Roteiro detalhado (referência)

### Módulo 1 — Acesso e navegação

**H-001 — Login e logout**  
1. Acesse a URL do sistema.  
2. Informe usuário e senha válidos e entre.  
3. Confirme que a tela inicial carrega (mapa ou redirecionamento esperado).  
4. Use o menu para sair (logout) e verifique retorno à tela de login.  
*Esperado:* acesso apenas com credenciais corretas; logout encerra a sessão.

**H-002 — Menu e rotas principais**  
1. Logado, acesse pelo menu: Mapa, Zeladorias, Moradores, Pontos, Minha Equipe.  
2. Em celular, use a barra inferior (se disponível).  
*Esperado:* todas as páginas abrem sem erro 404 ou tela em branco.

---

### Módulo 2 — Mapa de campo

**H-003 — Visualização do mapa**  
1. Abra **Mapa**.  
2. Navegue (arrastar, zoom in/out).  
3. Abra o menu (≡) e alterne camadas (satélite/ruas, limite municipal, pontos).  
*Esperado:* mapa responsivo; pontos aparecem como marcadores; limite de BH visível se camada ligada.

**H-004 — Busca de endereço**  
1. No campo de busca do mapa, digite um logradouro conhecido em BH.  
2. Selecione um resultado da lista.  
*Esperado:* mapa centraliza no endereço escolhido.

**H-005 — GPS (minha localização)**  
1. Toque no botão de localização (GPS).  
2. Autorize o navegador a usar a localização.  
*Esperado:* mapa aproxima da sua posição atual (pode haver pequeno desvio inicial).

**H-006 — Nova ação pelo mapa**  
1. Com zoom alto (rua visível), posicione o centro do mapa (mira) sobre um local.  
2. Toque em **Nova Ação** / **Registrar Zeladoria**.  
*Esperado:* abre o formulário de nova zeladoria com coordenadas e, se disponível, dados de endereço pré-preenchidos.

**H-007 — Filtro por resultado no mapa**  
1. No painel do mapa, filtre por um tipo de resultado (ex.: Persiste, Extinto).  
*Esperado:* apenas pontos compatíveis com o filtro permanecem visíveis (ou reduzem de forma coerente).

---

### Módulo 3 — Nova zeladoria (formulário)

**H-008 — Preenchimento mínimo obrigatório**  
1. Inicie uma nova zeladoria pelo mapa ou menu.  
2. Preencha apenas os campos obrigatórios (data, tipo de abordagem, resultado da ação, coordenadas).  
3. Salve/registre a zeladoria.  
*Esperado:* registro concluído com mensagem de sucesso; zeladoria aparece em **Minhas Zeladorias**.

**H-009 — Wizard (etapas)**  
1. No formulário, avance pelas abas/etapas (dados, pessoas, complexidade, encaminhamentos, fotos, etc.).  
2. Volte a uma etapa anterior e confira se os dados permanecem.  
*Esperado:* navegação entre etapas sem perda de dados já informados.

**H-010 — Participantes da equipe**  
1. Na etapa de participantes, marque colegas da equipe.  
2. Salve a zeladoria.  
*Esperado:* participantes salvos; na visualização da zeladoria, nomes agrupados por tipo de equipe (supervisor, agente, etc.).

**H-011 — Moradores na zeladoria**  
1. Vincule morador já cadastrado ou cadastre novo morador no fluxo da zeladoria.  
2. Marque presença de moradores existentes.  
*Esperado:* moradores associados ao ponto/vistoria conforme preenchido.

**H-012 — Comunicado e agendamento de zeladoria**  
1. Na aba Relatório, marque **houve comunicado** (se aplicável ao caso de teste).  
2. Informe data prevista e período da zeladoria.  
3. Salve e reabra a zeladoria.  
*Esperado:* data de agendamento e comunicado persistidos e visíveis na visualização/edição.

**H-013 — Indicador ao registrar**  
1. Ao clicar em **Registrar Zeladoria**, observe o botão durante o envio.  
*Esperado:* botão desabilitado e indicação de salvamento até concluir (evita duplo clique).

---

### Módulo 4 — Rascunho (salvamento parcial)

**H-014 — Salvar rascunho manual**  
1. Abra nova zeladoria e preencha parte do formulário (sem finalizar).  
2. Use **Salvar rascunho** no cabeçalho.  
*Esperado:* mensagem de rascunho salvo com horário.

**H-015 — Retomar rascunho**  
1. Feche o navegador ou volte ao mapa.  
2. Inicie novamente **Nova zeladoria** no mesmo contexto (mesmo ponto/coordenadas, se aplicável).  
*Esperado:* sistema pergunta se deseja retomar; ao confirmar, campos preenchidos reaparecem.

**H-016 — Rascunho descartado após registro**  
1. Retome ou crie rascunho, complete e **registre** a zeladoria definitivamente.  
2. Inicie outra nova zeladoria.  
*Esperado:* rascunho anterior não é mais oferecido para a mesma ação já concluída.

---

### Módulo 5 — Fotos

**H-017 — Foto pela câmera**  
1. Na etapa Fotos, use **Câmera** e tire uma foto.  
2. Confira a miniatura na grade de preview.  
*Esperado:* foto aparece na lista antes de registrar; após registrar, na visualização da zeladoria.

**H-018 — Foto pela galeria / arrastar**  
1. Adicione foto pela galeria ou arrastando imagem para a área indicada.  
2. Adicione legenda opcional.  
*Esperado:* upload aceito; legenda salva (após registro ou na edição, conforme fluxo).

**H-019 — Limite de tamanho**  
1. Tente anexar arquivo muito grande (> 30 MB).  
*Esperado:* mensagem clara de limite excedido; arquivo não é aceito.

**H-020 — Remover foto antes de salvar**  
1. Adicione foto no preview e clique em remover.  
*Esperado:* sistema pede confirmação; foto sai da lista.

**H-021 — Fotos offline (celular)**  
1. Abra nova zeladoria com rede **ativa**; tire 1–2 fotos.  
2. Desligue Wi‑Fi/dados (modo avião) **antes** de registrar a zeladoria.  
3. Complete e registre a zeladoria.  
4. Reative a rede e aguarde ou use sincronização de fotos pendentes (se exibido).  
*Esperado:* zeladoria salva; fotos enviadas quando a conexão voltar (pode levar alguns segundos).

**H-022 — Marcar foto pública (relatório)**  
1. Em zeladoria **aberta** sua, edite e na aba Fotos alterne visibilidade (cadeado/olho).  
2. Finalize a zeladoria (módulo 6).  
3. Gere relatório impresso.  
*Esperado:* apenas fotos marcadas como **públicas** aparecem no relatório A4.

---

### Módulo 6 — Ciclo de vida da zeladoria

**H-023 — Editar zeladoria aberta**  
1. Abra uma zeladoria **sua** em estado aberto.  
2. Altere um campo e salve.  
*Esperado:* alteração gravada.

**H-024 — Finalizar zeladoria**  
1. Na visualização da zeladoria aberta, use **Finalizar Zeladoria** e confirme.  
*Esperado:* status finalizado; botão editar some para o autor; data/hora de finalização registrada.

**H-025 — Relatório na tela e impressão**  
1. Em zeladoria finalizada, abra **Relatório**.  
2. Use **Imprimir** / visualização para impressão.  
*Esperado:* relatório com dados da ação, fotos públicas e cabeçalho institucional.

**H-026 — Complementar zeladoria finalizada**  
1. Em zeladoria finalizada, use **Complementar** e adicione texto.  
*Esperado:* complementação salva sem reabrir edição completa do conteúdo original.

**H-027 — Cancelar zeladoria aberta (autor)**  
1. Cancele uma zeladoria **sua** ainda aberta.  
*Esperado:* status cancelado; não é mais possível editar.

*Itens para administrador (se aplicável):*

**H-028 — Reativar zeladoria finalizada (admin)**  
*Esperado:* zeladoria volta a aberta; autor pode editar novamente.

**H-029 — Cancelar zeladoria finalizada (admin)**  
*Esperado:* conforme política do CRAS; status cancelado.

---

### Módulo 7 — Moradores

**H-030 — Cadastrar morador**  
1. Moradores → Novo; preencha **nome social** (obrigatório).  
2. Opcional: foto e vínculo com ponto.  
*Esperado:* morador criado; ficha acessível na listagem.

**H-031 — Buscar e filtrar moradores**  
1. Use busca por nome/apelido e filtros (com ponto / sem ponto).  
*Esperado:* listagem filtra corretamente.

**H-032 — Fotos do morador**  
1. Na ficha do morador, adicione e remova foto (com confirmação na edição).  
*Esperado:* fotos persistidas na galeria do morador.

**H-033 — Histórico de movimentação**  
1. Consulte histórico na ficha de morador que já mudou de ponto.  
*Esperado:* entradas/saídas/transferências visíveis com datas.

---

### Módulo 8 — Pontos

**H-034 — Listagem e detalhe de ponto**  
1. Acesse **Pontos**; abra um ponto da lista.  
*Esperado:* endereço, coordenadas, última zeladoria e moradores vinculados exibidos.

**H-035 — Editar ponto**  
1. Altere complemento ou observação; salve.  
*Esperado:* dados atualizados na ficha.

**H-036 — Informação precária**  
1. Localize ponto cuja última zeladoria seja antiga (conforme parâmetro do sistema).  
*Esperado:* indicador de informação precária visível na listagem ou ficha.

**H-037 — Nova zeladoria a partir do ponto**  
1. Na ficha do ponto, inicie nova zeladoria.  
*Esperado:* formulário abre com ponto e coordenadas já vinculados.

---

### Módulo 9 — Minha equipe

**H-038 — Configurar equipe**  
1. Acesse **Minha Equipe**.  
2. Marque/desmarque colegas e salve.  
*Esperado:* contador de selecionados atualiza; mensagem de sucesso.

**H-039 — Pré-seleção na nova zeladoria**  
1. Após salvar equipe, abra **Nova zeladoria**.  
*Esperado:* colegas da minha equipe já vêm marcados em Participantes (pode ajustar antes de salvar).

---

### Módulo 10 — Listagens, busca e exportação

**H-040 — Listagem de zeladorias**  
1. Acesse **Zeladorias**; use filtros (data, resultado, endereço, agendamento).  
*Esperado:* resultados coerentes com os filtros.

**H-041 — Minhas zeladorias**  
1. Acesse **Minhas Zeladorias**.  
*Esperado:* apenas zeladorias do usuário logado.

**H-042 — Exportar roteiro (CSV)**  
1. Na listagem de zeladorias, exporte roteiro (se disponível no menu/ação).  
*Esperado:* arquivo CSV baixado com colunas legíveis.

---

### Módulo 11 — Dashboard e administração

**H-043 — Dashboard**  
1. Acesse **Dashboard**.  
2. Confira cards (totais) e gráfico de evolução; alterne filtros do gráfico.  
*Esperado:* números carregam; gráfico reage aos filtros.

**H-044 — Parâmetros (admin)**  
1. Com perfil admin: **Admin → Parâmetros**.  
2. Altere um parâmetro de teste (ex.: dias de informação precária) e salve.  
*Esperado:* valor persistido após recarregar a página.  
*Reverta* o valor após o teste, se orientado pela coordenação.

---

## 6. Planilha de registro

Copie a tabela abaixo para Excel/Google Sheets ou imprima e preencha à mão.

| ID | Módulo | Descrição resumida | Resultado (OK/Falha/N/A) | Observações | Testador | Data |
|----|--------|-------------------|--------------------------|-------------|----------|------|
| H-001 | Acesso | Login e logout | | | | |
| H-002 | Acesso | Menu e rotas | | | | |
| H-003 | Mapa | Visualização | | | | |
| H-004 | Mapa | Busca endereço | | | | |
| H-005 | Mapa | GPS | | | | |
| H-006 | Mapa | Nova ação | | | | |
| H-007 | Mapa | Filtro resultado | | | | |
| H-008 | Zeladoria | Campos obrigatórios | | | | |
| H-009 | Zeladoria | Wizard etapas | | | | |
| H-010 | Zeladoria | Participantes | | | | |
| H-011 | Zeladoria | Moradores | | | | |
| H-012 | Zeladoria | Comunicado/agendamento | | | | |
| H-013 | Zeladoria | Indicador ao registrar | | | | |
| H-014 | Rascunho | Salvar manual | | | | |
| H-015 | Rascunho | Retomar | | | | |
| H-016 | Rascunho | Descarte pós-registro | | | | |
| H-017 | Fotos | Câmera | | | | |
| H-018 | Fotos | Galeria/arrastar | | | | |
| H-019 | Fotos | Limite 30 MB | | | | |
| H-020 | Fotos | Remover com confirmação | | | | |
| H-021 | Fotos | Offline | | | | |
| H-022 | Fotos | Pública no relatório | | | | |
| H-023 | Ciclo | Editar aberta | | | | |
| H-024 | Ciclo | Finalizar | | | | |
| H-025 | Ciclo | Relatório/impressão | | | | |
| H-026 | Ciclo | Complementar | | | | |
| H-027 | Ciclo | Cancelar (autor) | | | | |
| H-028 | Ciclo | Reativar (admin) | | | | |
| H-029 | Ciclo | Cancelar finalizada (admin) | | | | |
| H-030 | Morador | Cadastro | | | | |
| H-031 | Morador | Busca/filtros | | | | |
| H-032 | Morador | Fotos | | | | |
| H-033 | Morador | Histórico | | | | |
| H-034 | Ponto | Listagem/detalhe | | | | |
| H-035 | Ponto | Edição | | | | |
| H-036 | Ponto | Info precária | | | | |
| H-037 | Ponto | Nova zeladoria | | | | |
| H-038 | Equipe | Configurar | | | | |
| H-039 | Equipe | Pré-seleção | | | | |
| H-040 | Listagens | Filtros zeladorias | | | | |
| H-041 | Listagens | Minhas zeladorias | | | | |
| H-042 | Listagens | Exportar CSV | | | | |
| H-043 | Dashboard | Indicadores/gráfico | | | | |
| H-044 | Admin | Parâmetros | | | | |

---

## 7. Critérios de aceite

| Situação | Decisão |
|----------|---------|
| Todos os itens **obrigatórios** com OK | Homologação **aprovada** |
| Falhas apenas em itens **opcionais** (H-028, H-029, H-044) | Avaliar com gestão |
| Qualquer falha em **H-006, H-008, H-021, H-024, H-025** | Homologação **reprovada** até correção — são fluxos críticos |
| Falhas em itens offline (H-021) só em um navegador | Registrar navegador e versão; repetir em Chrome Android |

**Itens obrigatórios mínimos (campo):** H-001, H-003, H-006, H-008, H-017, H-024, H-025, H-030, H-038.

---

## 8. Termo de aceite

**Projeto:** POPRUA CRAS — Homologação funcional  
**Período dos testes:** ___/___/______ a ___/___/______  
**Ambiente:** URL testada: _________________________________

Declaramos que executamos os testes descritos neste manual:

- [ ] **Aprovado** — o sistema atende aos fluxos críticos para início/operação.  
- [ ] **Aprovado com ressalvas** — lista de ressalvas em anexo.  
- [ ] **Reprovado** — itens em anexo impedem o uso até correção.

| Nome | Função | Assinatura | Data |
|------|--------|------------|------|
| | Profissional de campo | | |
| | Supervisão CRAS | | |
| | Gestão / TI | | |

**Anexo de falhas (se houver):**

| ID | Descrição da falha | Severidade (Alta/Média/Baixa) |
|----|-------------------|-------------------------------|
| | | |

---

## 9. Limitações conhecidas (informar aos testadores)

1. **Rascunho** — salva dados do formulário no servidor; **fotos** continuam na fila local do aparelho (não vão no JSON do rascunho).  
2. **Pontos em área sem endereço de porta próximo** — o sistema pode registrar coordenadas sem vínculo com tabela de endereços da PBH (abrigo em via, terreno, etc.).  
3. **Homologação offline** — testar preferencialmente em **Chrome Android**; Safari/iOS pode ter comportamento diferente na fila de fotos.  
4. **Relatório público** — somente fotos marcadas como **públicas** antes da finalização entram na versão impressa.

---

## 10. Referências técnicas (equipe de desenvolvimento)

| Caso de uso | Documento |
|-------------|-----------|
| Minha equipe | `docs/casos-de-uso/UC-001-minha-equipe.md` |
| Finalização e relatório | `docs/casos-de-uso/UC-002-finalizacao-zeladoria.md` |
| Moradores | `docs/casos-de-uso/UC-003-gestao-morador.md` |
| Mapa e nova ação | `docs/casos-de-uso/UC-004-mapa-campo.md` |
| Pontos | `docs/casos-de-uso/UC-005-gestao-ponto.md` |
| Rascunho | `docs/casos-de-uso/UC-006-rascunho-zeladoria.md` |
| Fotos offline | `docs/casos-de-uso/UC-007-upload-offline-fotos.md` |
| Dashboard | `docs/casos-de-uso/UC-008-dashboard-gestao.md` |
| Parametrização | `docs/casos-de-uso/UC-010-parametrizacao-admin.md` |
| Regras de negócio | `docs/REGRAS_NEGOCIO.md` |

---

*Documento mantido pela equipe POPRUA CRAS. Sugestões de melhoria neste roteiro devem ser enviadas à coordenação do projeto.*
