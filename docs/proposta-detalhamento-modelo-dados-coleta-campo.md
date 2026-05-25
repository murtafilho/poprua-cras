# Proposta de Detalhamento do Modelo de Dados para Coleta em Campo

**Subsídio à Comissão Especial de Operação e Monitoramento (Art. 11, Portaria Conjunta nº 009/2026)**

---

**Tipo:** Proposta técnica para discussão intersetorial  
**Data:** 24 de maio de 2026  
**Elaboração:** Coordenação Técnica POPRUA CRAS  
**Destinatários:** Comissão Especial de Operação e Monitoramento — representantes regionais de SMPU, SMASDH, SMSA, SMSP e SLU  
**Referência:** Portaria Conjunta SMGO/SMPU/SMASDH/SMSA/SMSP/SLU nº 009/2026 (DOM-BH Ed. 7504, 22/05/2026)

---

## 1. OBJETO

A presente proposta submete à Comissão Especial de Operação e Monitoramento, instituída pelo Art. 11 da Portaria Conjunta nº 009/2026, um conjunto de **campos adicionais para o sistema POPRUA CRAS**, a serem discutidos e validados antes da entrada do sistema em produção.

O objetivo é garantir que o instrumento de coleta em campo capture os dados necessários para:

- Cumprir as obrigações documentais da Portaria nº 009/2026
- Produzir os relatórios exigidos pelo Art. 9º
- Subsidiar o diagnóstico pormenorizado determinado pela ADPF 976/STF
- Permitir à Comissão avaliar permanentemente as ações (Art. 11, IV)

---

## 2. JUSTIFICATIVA

O sistema POPRUA encontra-se em transição da Subsecretaria de Fiscalização (SUFIS) para o CRAS, com entrada em produção prevista para a próxima semana. Este momento é a **última oportunidade técnica de baixo custo** para incluir novos campos no formulário de coleta.

Após a entrada em produção:
- Campos adicionados retroativamente não terão dados históricos
- O retreinamento das equipes de campo gerará custo operacional
- A série temporal ficará comprometida por lacuna inicial

A Portaria nº 009/2026, publicada em 22/05/2026, revogou a Portaria nº 001/2017 e estabeleceu novas exigências que o modelo de dados atual não contempla integralmente. O quadro a seguir identifica cada lacuna.

---

## 3. MAPEAMENTO: EXIGÊNCIAS DA PORTARIA × DADOS DISPONÍVEIS

| Artigo | Exigência | Dado que o sistema precisa registrar | Situação atual |
|--------|-----------|--------------------------------------|----------------|
| Art. 6º, II, g | Mapeamento e monitoramento dos espaços com concentração | Georreferenciamento, contagem temporal, status do ponto | ✅ Disponível |
| Art. 6º, V | Identificar situações de risco e vulnerabilidades | Índice de complexidade com fatores ponderados | ⚠️ Parcial — 16 fatores sem ponderação diferenciada |
| Art. 6º, V | Verificar se já acompanhadas pela rede socioassistencial e de saúde | Vínculo do morador com CRAS/CREAS/Centro POP/CAPS/UBS | ❌ Não disponível |
| Art. 6º, IV | Esgotamento das ações de zeladoria registrado | Histórico de abordagens orientativas por ponto | ⚠️ Parcial — sem contagem formal de esgotamento |
| Art. 6º, VIII | Protocolos especiais em chuva/frio | Condição climática no momento da abordagem | ❌ Não disponível |
| Art. 7º, I | Ação fiscal condicionada a prévio esgotamento e registro | Rastreabilidade: orientação → comunicado → fiscal | ⚠️ Parcial |
| Art. 6º, V | Encaminhamentos à rede | Tipo de encaminhamento realizado | ✅ Disponível (6 slots) |
| Art. 9º | Relatórios sobre atividades | Dados tabuláveis por período, regional, tipo | ✅ Disponível |

---

## 4. CAMPOS PROPOSTOS PARA DISCUSSÃO

Os campos estão organizados por **categoria de captura** — como o agente obtém a informação em campo:

### 4.1 Observação direta pelo agente (registrados na vistoria)

Dados que o agente **constata** ao chegar no ponto, sem necessidade de perguntar ao morador:

| Campo proposto | Tipo de input | Local no formulário | Vinculação com a Portaria |
|----------------|---------------|---------------------|---------------------------|
| **Evidência de violência ou exploração** | Checkbox sim/não | Aba "Perfil da Ocorrência", junto aos fatores de complexidade | Art. 6º, V — identificar situações de risco |
| **Recusa de acolhimento formalizada** | Sim/não + motivo (texto) | Aba "Relatório", após resultado da ação | Art. 7º, I — demonstrar esgotamento de alternativas |
| **Condição climática** | Select: normal / protocolo chuva / protocolo frio / calor extremo | Aba "Dados", junto à data/hora | Art. 6º, VIII — protocolos especiais |
| **Classificação territorial** | Select: centro comercial / residencial / via arterial / área verde / viaduto / outro | Cadastro do ponto (preenchido uma vez) | Art. 6º, II, g — monitoramento dos espaços |

### 4.2 Escuta qualificada do morador (registrados no cadastro do morador)

Dados que dependem de **diálogo** com a pessoa em situação de rua. São atributos da pessoa, não da visita — persistem entre vistorias:

| Campo proposto | Tipo de input | Justificativa técnica |
|----------------|---------------|----------------------|
| **Tempo em situação de rua** | Faixas: menos de 6 meses / 6 meses a 2 anos / 2 a 5 anos / mais de 5 anos | Principal preditor de cronificação. Utilizado no VI-SPDAT (domínio 1) como fator de priorização. Diferencia intervenção emergencial de acompanhamento longitudinal |
| **Vínculo com serviço da rede** | Multi-seleção: CRAS / CREAS / Centro POP / CAPS / UBS / nenhum | Art. 6º, IV da Portaria exige verificar se a pessoa já é acompanhada. Sem este dado, não há como cumprir a exigência |
| **Condição de saúde crônica** | Multi-seleção: diabetes / HIV / tuberculose / outra / nenhuma | A coocorrência de saúde mental + dependência química + doença crônica (tri-morbidade) triplica a mortalidade em situação de rua (Aldridge et al., 2018) |
| **Risco de autolesão** | Sim/não (campo sensível, preenchimento opcional) | Indicador de urgência máxima no VI-SPDAT. Determina encaminhamento imediato ao CAPS |

**Nota sobre dados sensíveis:** os campos de saúde e autolesão devem ser marcados como opcionais e tratados com sigilo reforçado, em conformidade com a LGPD e os princípios éticos elencados no Decreto Federal nº 7.053/2009.

### 4.3 Dados calculados automaticamente pelo sistema (sem input do agente)

| Indicador | Como é calculado | Para que serve |
|-----------|------------------|----------------|
| **Informação Precária** | Ponto sem vistoria há mais de 60 dias (configurável) | Priorizar pontos que perderam acompanhamento |
| **Esgotamento de mediação** | 3 ou mais abordagens orientativas no ponto sem mudança de resultado | Atender Art. 7º, I — condição para escalar a ação fiscal |
| **Bônus de tri-morbidade** | Morador com saúde mental + dependência química + doença crônica simultaneamente | Elevar automaticamente a complexidade do ponto |
| **Complexidade ponderada** | Soma dos fatores com pesos diferenciados por gravidade | Priorização de pontos para alocação de equipes |

---

## 5. PONDERAÇÃO DOS FATORES DE COMPLEXIDADE

O sistema atual permite configurar individualmente o peso de cada fator de complexidade (tela de Parametrização, acessível ao administrador). A tabela abaixo apresenta os pesos propostos para validação pela Comissão:

### 5.1 Fatores de vulnerabilidade prioritária (peso 3)

| Fator | Peso proposto | Fundamentação |
|-------|---------------|---------------|
| Presença de crianças/adolescentes | **3** | Prioridade absoluta: ECA, Resolução CNAS/CONANDA nº 1/2016, Art. 2º parágrafo único da Portaria 009/2026 |
| Gestante | **3** | Alto risco de morbimortalidade materno-fetal em situação de rua |
| Saúde mental | **3** | Maior preditor de mortalidade na rua (VI-SPDAT, domínio wellness) |
| Deficiência | **3** | Mobilidade comprometida + vulnerabilidade extrema à violência |

### 5.2 Fatores de risco elevado (peso 2)

| Fator | Peso proposto | Fundamentação |
|-------|---------------|---------------|
| Tráfico de ilícitos | **2** | Fator de violência, exploração e risco de morte |
| Cena de uso caracterizada | **2** | Dependência química é critério primário de priorização |
| Agrupamento com dependência química | **2** | Correlação direta com mortalidade |
| Idosos (60+) | **2** | VI-SPDAT: ponto adicional para idade avançada |
| Fixação antiga | **2** | Cronificação é o principal preditor de dificuldade de intervenção |
| LGBTQIAPN+ | **2** | Índices elevados de violência e rejeição familiar |

### 5.3 Fatores de complexidade padrão (peso 1)

| Fator | Peso proposto |
|-------|---------------|
| Resistência à abordagem | 1 |
| Número reduzido de pessoas | 1 |
| Casais | 1 |
| Catadores de recicláveis | 1 |
| Excesso de objetos | 1 |
| Animais | 1 |

### 5.4 Escala resultante

Com a ponderação proposta, a escala de complexidade passa de 0–16 (uniforme) para **0–28** (ponderada), permitindo maior granularidade:

| Classificação | Pontuação | Significado operacional |
|---------------|-----------|-------------------------|
| **Crítico** | ≥ 14 | Requer intervenção multisecretaria imediata |
| **Alto** | 9–13 | Prioridade para acompanhamento continuado |
| **Médio** | 5–8 | Acompanhamento regular |
| **Baixo** | 1–4 | Monitoramento periódico |

---

## 6. QUESTÕES PARA DELIBERAÇÃO DA COMISSÃO

Solicita-se que a Comissão se manifeste sobre os seguintes pontos:

### 6.1 Sobre os campos de coleta

1. Os campos propostos na seção 4.1 (observação direta) devem ser **obrigatórios ou opcionais** no formulário?
2. O campo "Risco de autolesão" (seção 4.2) deve ser incluído, considerando a sensibilidade do dado e a capacitação necessária dos agentes?
3. Há algum campo adicional que a experiência territorial das equipes indica como necessário e que não foi contemplado?

### 6.2 Sobre os pesos de complexidade

4. Os pesos propostos na seção 5 refletem adequadamente a realidade operacional de Belo Horizonte?
5. A SMASDH, SMSA e SMSP concordam com a hierarquização de vulnerabilidades apresentada?
6. Os thresholds de classificação (crítico ≥ 14, alto ≥ 9) são compatíveis com a capacidade de resposta das equipes?

### 6.3 Sobre o conceito de Informação Precária

7. O intervalo de **60 dias** sem vistoria para classificar um ponto como "Informação Precária" é adequado à capacidade operacional das equipes do CRAS?
8. Pontos com Informação Precária devem gerar alerta automático para a coordenação?

### 6.4 Sobre a interoperabilidade

9. Os dados coletados pelo POPRUA CRAS devem alimentar algum outro sistema (CadÚnico, e-SUS, prontuário SUAS)?
10. A SMSA necessita de campos adicionais para integração com o e-SUS/SISAB?

---

## 7. CRONOGRAMA PROPOSTO

| Etapa | Prazo | Responsável |
|-------|-------|-------------|
| Distribuição desta proposta aos membros da Comissão | 26/05/2026 | Coordenação POPRUA |
| Reunião de discussão e deliberação | até 30/05/2026 | Comissão (Art. 11) |
| Incorporação dos campos aprovados ao sistema | até 02/06/2026 | Equipe técnica POPRUA |
| Capacitação das equipes de campo | até 06/06/2026 | SMPU + SMASDH |
| Entrada em produção com modelo de dados validado | 09/06/2026 | Coordenação POPRUA |

---

## 8. CONSIDERAÇÃO FINAL

A definição do modelo de dados não é uma decisão exclusivamente técnica — é uma decisão de **política pública**. Os campos que escolhermos coletar determinam quais perguntas poderemos responder no futuro. Os campos que deixarmos de fora representam perguntas que **nunca poderão ser respondidas** com os dados do período inicial de operação.

A Portaria Conjunta nº 009/2026, ao criar a Comissão Especial de Operação e Monitoramento com representação das cinco secretarias, oferece o fórum institucional adequado para esta deliberação. A presente proposta visa subsidiar essa discussão com fundamentação técnica e bibliográfica, respeitando a competência de cada órgão na definição dos parâmetros que lhe dizem respeito.

---

## REFERÊNCIAS

1. Portaria Conjunta SMGO/SMPU/SMASDH/SMSA/SMSP/SLU nº 009/2026. DOM-BH, Edição 7504, 22/05/2026.
2. Supremo Tribunal Federal. Medida Cautelar na ADPF nº 976. Rel. Min. Alexandre de Moraes, 25/07/2023.
3. Brasil. Decreto Federal nº 7.053/2009 — Política Nacional para a População em Situação de Rua.
4. OrgCode Consulting. Service Prioritization Decision Assistance Tool (SPDAT) — Manual. Hamilton, 2015.
5. Brown, M. et al. Reliability and validity of the VI-SPDAT in real-world implementation. Journal of Social Distress and the Homeless, v. 27, n. 2, 2018.
6. Aldridge, R. W. et al. Morbidity and mortality in homeless individuals, prisoners, sex workers, and individuals with substance use disorders. The Lancet, v. 391, n. 10117, 2018.
7. Fazel, S. et al. The health of homeless people in high-income countries. The Lancet, v. 384, n. 9953, 2014.
8. SMADS/SP. Norma Técnica nº 11/SMADS/2024 — SEAS Modalidade Adulto e Misto. São Paulo, 2024.
9. SMADS/SP. Norma Técnica nº 12/SMADS/2024 — SEAS Modalidade Crianças e Adolescentes. São Paulo, 2024.
10. CNAS/CONANDA. Resolução Conjunta nº 1/2016 — Crianças e adolescentes em situação de rua.
11. Belo Horizonte. Decreto Municipal nº 18.690/2024 — Comitê Intersetorial Pop Rua.
12. IPEA. A população em situação de rua nos números do Cadastro Único. Nota Técnica nº 2944, 2024.
13. National Alliance to End Homelessness. Looking Back at the VI-SPDAT Before Moving Forward. Washington, 2022.

---

*Coordenação Técnica POPRUA CRAS — Belo Horizonte, 24 de maio de 2026*
