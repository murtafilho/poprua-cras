# NOTA TÉCNICA — Detalhamento de Dados do Fenômeno para o Sistema POPRUA CRAS

**Oportunidade de Parametrização Diante da Portaria Conjunta nº 009/2026 e da Reformulação do Sistema**

---

**Tipo:** Nota Técnica para Discussão Gerencial  
**Data:** 24 de maio de 2026  
**Autor:** Coordenação Técnica POPRUA CRAS  
**Destinatários:** Comissão Especial de Operação e Monitoramento (Art. 11 da Portaria Conjunta nº 009/2026), Secretarias de Política Urbana, Assistência Social e Direitos Humanos, Saúde, Segurança e Prevenção, e SLU  
**Referência legal:** Portaria Conjunta SMGO/SMPU/SMASDH/SMSA/SMSP/SLU nº 009/2026, publicada em 22/05/2026, Edição 7504 do DOM-BH

---

## 1. CONTEXTO E OPORTUNIDADE

A publicação da Portaria Conjunta nº 009/2026, que revoga a Portaria nº 001/2017, representa um marco na reformulação das diretrizes de atuação com a população em situação de rua em Belo Horizonte. Simultaneamente, o sistema POPRUA — originalmente desenvolvido pela Subsecretaria de Fiscalização (SUFIS) para apoio à zeladoria urbana — encontra-se em processo de migração para o CRAS, assumindo papel central na gestão de informações sobre os pontos de concentração do fenômeno.

**Este é o momento decisivo para discutir quais dados o sistema deve coletar.** Uma vez em produção, a inclusão retroativa de campos é tecnicamente possível, mas operacionalmente custosa: exige retreinamento de equipes, reprocessamento de dados legados e perda de série histórica. Os dados que não forem mapeados agora simplesmente **não existirão** para análise futura.

A presente nota técnica identifica lacunas no modelo de dados atual do POPRUA frente às exigências da nova Portaria e à literatura internacional sobre índices de vulnerabilidade, e propõe campos adicionais para discussão intersetorial antes da entrada em produção.

---

## 2. O QUE A PORTARIA Nº 009/2026 EXIGE EM TERMOS DE DADOS

A Portaria estabelece obrigações que dependem diretamente de dados estruturados:

| Artigo | Obrigação | Dado necessário | Situação no POPRUA |
|--------|-----------|-----------------|---------------------|
| Art. 6º, II, g | Mapeamento e monitoramento dos espaços com concentração | Georreferenciamento de pontos, contagem temporal | ✅ Implementado |
| Art. 6º, V | Identificação de situações de risco e vulnerabilidades | **Índice de complexidade/vulnerabilidade** | ⚠️ Parcial — 16 fatores binários sem ponderação adequada |
| Art. 6º, V | Encaminhamentos à rede socioassistencial ou de saúde | Registro de encaminhamentos por tipo | ✅ Implementado (6 slots) |
| Art. 6º, V | Verificar se já acompanhadas pela rede | **Vínculo com serviço CRAS/CREAS/Centro POP** | ❌ Não implementado |
| Art. 6º, IV | Esgotamento e registro das ações de zeladoria | Histórico de abordagens por ponto com workflow | ✅ Implementado |
| Art. 7º, I | Prévio esgotamento e registro antes da ação fiscal | Rastreabilidade: comunicado → zeladoria → fiscalização | ⚠️ Parcial — conceito de Informação Precária implementado, mas sem registro de esgotamento formal |
| Art. 9º | Relatórios sobre atividades realizadas | Dados tabuláveis por período, regional, tipo | ✅ Implementado |
| Art. 11 | Comissão de monitoramento avaliar ações | Indicadores de efetividade por ponto | ⚠️ Parcial — sem métricas de resultado longitudinal |
| Art. 6º, VIII | Protocolos especiais em chuva/frio | **Condição climática no momento da abordagem** | ❌ Não implementado |

---

## 3. O QUE A LITERATURA INTERNACIONAL RECOMENDA

### 3.1 VI-SPDAT (Vulnerability Index — Service Prioritization Decision Assistance Tool)

O VI-SPDAT é o instrumento mais validado mundialmente para avaliação de vulnerabilidade de pessoas em situação de rua, utilizado em mais de 7.000 comunidades nos Estados Unidos e adotado na Austrália, Canadá e partes da Europa. Avalia **4 domínios** com pesos diferenciados:

| Domínio | Fatores avaliados | Peso relativo |
|---------|-------------------|---------------|
| **Histórico habitacional** | Tempo em situação de rua, episódios anteriores, primeira ocorrência | Base |
| **Riscos** | Violência sofrida, exploração, ameaças, atividades de alto risco | Elevado |
| **Socialização** | Atividades diárias, gestão financeira, interação social, rede de apoio | Moderado |
| **Saúde (Wellness)** | Saúde mental, dependência química, condições crônicas, deficiência, tentativa de suicídio | **Mais elevado** |

**Fundamento da ponderação:** a saúde recebe maior peso porque a mortalidade de pessoas em situação de rua é 3 a 10 vezes superior à da população geral, e condições de saúde não tratadas são o principal preditor de óbito em situação de rua (Fazel et al., 2014; Aldridge et al., 2018).

> **Referências:**
> - Brown, M. et al. (2018). *Reliability and validity of the VI-SPDAT in real-world implementation.* Journal of Social Distress and the Homeless.
> - Cronley, C. (2020). *Invisible homelessness: Challenges and opportunities for data collection.* American Journal of Public Health.
> - OrgCode Consulting (2015). *Service Prioritization Decision Assistance Tool (SPDAT) — Manual.*

### 3.2 Norma Técnica SMADS/SP nº 11/2024

São Paulo publicou em dezembro de 2024 a NT 11/SMADS/2024 que regulamenta o Serviço Especializado de Abordagem Social (SEAS) e introduz critérios de **complexidade territorial** — não apenas individual — reconhecendo que o território onde o ponto se localiza modifica a natureza da intervenção necessária.

### 3.3 Decreto Federal nº 7.053/2009 e ADPF 976/STF

A Política Nacional para População em Situação de Rua e a decisão do STF na ADPF 976 determinam que os municípios devem realizar **diagnóstico pormenorizado** da situação. Dados genéricos não atendem a essa exigência — é necessário detalhamento suficiente para subsidiar políticas públicas diferenciadas.

---

## 4. LACUNAS IDENTIFICADAS NO MODELO DE DADOS ATUAL

### 4.1 Dados ausentes sobre a pessoa (morador)

| Campo ausente | Justificativa | Referência |
|---------------|---------------|------------|
| **Tempo em situação de rua** (faixas: < 6 meses, 6m-2 anos, 2-5 anos, > 5 anos) | Principal preditor de cronificação. Diferencia intervenção de emergência vs. acompanhamento de longo prazo. Domínio primário do VI-SPDAT | VI-SPDAT domínio 1; ADPF 976 |
| **Histórico de violência/exploração** | Fator de risco de morte. Determina necessidade de acolhimento protegido vs. comum | VI-SPDAT domínio 2; Art. 6º, V da Portaria 009/2026 |
| **Tentativa de suicídio ou autolesão** | Indicador de urgência máxima. No VI-SPDAT, peso superior a qualquer outro fator isolado | VI-SPDAT wellness; Protocolo MS de atenção psicossocial |
| **Recusa de acolhimento** | Diferente de "resistência" — registra oferta formal de vaga recusada. Obrigatório para demonstrar esgotamento de alternativas (Art. 7º, I da Portaria) | NT 11/SMADS/2024; Art. 7º Portaria 009/2026 |
| **Vínculo com serviço da rede** (CRAS, CREAS, Centro POP, UBS, CAPS) | Art. 6º, IV da Portaria exige verificar se a pessoa já é acompanhada. Sem este campo, não há como cruzar | Art. 6º, IV Portaria 009/2026; CadÚnico |
| **Condição de saúde crônica** (diabetes, HIV, tuberculose, etc.) | Tri-morbidade (saúde mental + dependência + crônica) triplica mortalidade. Hoje capturamos separados sem bônus por coocorrência | VI-SPDAT wellness; MS Linha de Cuidado PSR |

### 4.2 Dados ausentes sobre o ponto

| Campo ausente | Justificativa | Referência |
|---------------|---------------|------------|
| **Classificação territorial** (centro comercial, residencial, via arterial, área verde, viaduto) | Complexidade territorial modifica a intervenção. NT SMADS/SP reconhece isso formalmente | NT 11/SMADS/2024 |
| **Condição climática** (protocolo chuva/frio ativo) | Art. 6º, VIII da Portaria exige protocolos especiais. Sem registro, não há evidência de cumprimento | Art. 6º, VIII Portaria 009/2026 |
| **Registro de esgotamento de mediação** | Art. 7º, I condiciona ação fiscal ao prévio esgotamento das ações de zeladoria. O sistema precisa registrar formalmente cada tentativa de mediação antes de permitir escalar para fiscal | Art. 7º, I Portaria 009/2026 |

### 4.3 Dados ausentes sobre o workflow

| Campo ausente | Justificativa | Referência |
|---------------|---------------|------------|
| **Tipo de intervenção escalonada** (orientativa → mediação → comunicado → fiscal) | A Portaria estabelece escalonamento obrigatório (Arts. 6º e 7º). O sistema precisa rastrear em qual fase cada ponto se encontra | Arts. 6º e 7º Portaria 009/2026 |
| **Equipe intersetorial presente** (quais secretarias participaram da ação) | Art. 11 cria Comissão com representantes de 5 secretarias. Necessário registrar participação por ação | Art. 11 Portaria 009/2026 |
| **Canal de denúncia originário** | Art. 6º, II, h: tratativa de reclamações/denúncias. Sem campo de origem, impossível tabular | Art. 6º, II, h Portaria 009/2026 |

---

## 5. PONDERAÇÃO DOS CRITÉRIOS DE COMPLEXIDADE

O sistema atual utiliza 16 fatores booleanos (sim/não) com peso uniforme de 1 ponto cada, totalizando uma escala de 0 a 16. A literatura recomenda ponderação diferenciada:

| Fator | Peso atual | Peso sugerido | Fundamentação |
|-------|-----------|---------------|---------------|
| Presença de crianças/adolescentes | 1 → 3 | **3** | Prioridade absoluta em todas as normativas (ECA, Resolução CNAS/CONANDA nº 1/2016, Art. 2º §único da Portaria 009/2026) |
| Gestante | 1 → 3 | **3** | Alto risco de morbimortalidade materno-fetal em situação de rua |
| Saúde mental | 1 → 2 | **3** | Maior preditor de mortalidade na rua (VI-SPDAT). Deveria ser **3** |
| Deficiência | 1 → 2 | **3** | Mobilidade comprometida + vulnerabilidade extrema |
| Tráfico de ilícitos | 1 → 2 | **2** | Fator de violência e exploração |
| Cena de uso caracterizada | 1 → 2 | **2** | Dependência química é critério primário |
| Agrupamento com dependência química | 1 → 2 | **2** | Correlação com mortalidade |
| Idosos | 1 → 2 | **2** | VI-SPDAT: +1 ponto para ≥ 60 anos |
| Fixação antiga | 1 | **2** | Cronificação é preditor forte (VI-SPDAT domínio 1). Hoje subvalorizado |
| LGBTQIAPN+ | 1 | **2** | Índices elevados de violência e rejeição familiar (NT SMADS/SP) |
| Resistência à abordagem | 1 | 1 | Peso base adequado |
| Excesso de objetos | 1 | 1 | Indicador de fixação |
| Catadores de recicláveis | 1 | 1 | Art. 3º, VII: recicláveis como meios de trabalho |
| Casais | 1 | 1 | Peso base adequado |
| Número reduzido | 1 | 1 | Peso base adequado |
| Animais | 1 | 1 | Barreira para acolhimento |

**Com a ponderação sugerida, a escala passa de 0-16 para 0-28**, permitindo maior granularidade na priorização. Os thresholds de classificação devem ser recalibrados:

| Nível | Escala atual (0-16) | Escala proposta (0-28) | Cor |
|-------|---------------------|------------------------|-----|
| Crítico | ≥ 8 | ≥ 14 | Vermelho |
| Alto | ≥ 5 | ≥ 9 | Amarelo |
| Médio | ≥ 3 | ≥ 5 | Azul |
| Baixo | ≥ 1 | ≥ 1 | Verde |

**Nota importante:** estes pesos já estão configurados como parâmetros editáveis no sistema (`/admin/parametros`, grupo "complexidade"). A Comissão pode ajustá-los sem necessidade de alteração no código.

---

## 6. CONCEITO DE "INFORMAÇÃO PRECÁRIA"

O sistema implementou o conceito de **Informação Precária** — status calculado dinamicamente que identifica pontos cuja última vistoria ocorreu há mais de 60 dias (configurável via parâmetro `info_precaria_dias`).

Este conceito atende diretamente ao Art. 6º, II, g da Portaria ("mapeamento e monitoramento dos espaços"), sinalizando pontos que perderam acompanhamento e cuja informação está degradada. Na listagem do sistema, estes pontos recebem tratamento visual diferenciado e a ação prioritária "Vistoria de Atualização" é evidenciada ao agente de campo.

**Recomendação para a Comissão:** definir se 60 dias é o intervalo adequado para o contexto do CRAS, ou se outro valor (45, 90 dias) seria mais apropriado à capacidade operacional das equipes.

---

## 7. PROPOSTA DE AÇÃO

### 7.1 Discussão imediata (antes da entrada em produção)

1. **Validar os campos adicionais** listados na seção 4 com os representantes de cada secretaria na Comissão (Art. 11)
2. **Definir quais campos são obrigatórios vs. opcionais** — equilibrando riqueza de dados com viabilidade de coleta em campo
3. **Aprovar a ponderação de complexidade** (seção 5) ou propor ajustes
4. **Definir o intervalo de Informação Precária** adequado à capacidade operacional

### 7.2 Implementação técnica (pré-produção)

5. Adicionar os campos aprovados ao formulário de vistoria do sistema
6. Configurar os pesos aprovados na parametrização do sistema
7. Capacitar as equipes de campo no preenchimento dos novos campos

### 7.3 Acompanhamento (pós-produção)

8. Monitorar a qualidade do preenchimento dos novos campos nos primeiros 90 dias
9. Gerar relatório para a Comissão com os primeiros indicadores
10. Ajustar pesos e thresholds com base nos dados reais coletados

---

## 8. CONCLUSÃO

A reformulação do sistema POPRUA para o CRAS, coincidindo com a publicação da Portaria Conjunta nº 009/2026, representa uma **janela única de oportunidade** para estruturar a coleta de dados que atenda simultaneamente:

- Às exigências legais da Portaria e da ADPF 976/STF
- Às boas práticas internacionais de avaliação de vulnerabilidade (VI-SPDAT)
- Às necessidades operacionais das equipes de campo
- À produção de indicadores para a Comissão de Monitoramento

**Adiar esta discussão significa produzir dados insuficientes desde o primeiro dia de operação.** O custo de coletar dados incompletos por meses e depois ter que complementar retroativamente é ordens de magnitude superior ao custo de definir os campos corretos agora.

Solicita-se agendamento de reunião com os representantes da Comissão Especial de Operação e Monitoramento para validação dos campos propostos.

---

## REFERÊNCIAS BIBLIOGRÁFICAS

1. BROWN, M. et al. *Reliability and validity of the Vulnerability Index-Service Prioritization Decision Assistance Tool (VI-SPDAT) in real-world implementation.* Journal of Social Distress and the Homeless, v. 27, n. 2, 2018.

2. FAZEL, S. et al. *The health of homeless people in high-income countries: descriptive epidemiology, health consequences, and clinical and policy recommendations.* The Lancet, v. 384, n. 9953, p. 1529-1540, 2014.

3. ALDRIDGE, R. W. et al. *Morbidity and mortality in homeless individuals, prisoners, sex workers, and individuals with substance use disorders in high-income countries: a systematic review and meta-analysis.* The Lancet, v. 391, n. 10117, p. 241-250, 2018.

4. ORGCODE CONSULTING. *Service Prioritization Decision Assistance Tool (SPDAT) — Manual.* Hamilton, Canadá, 2015.

5. NATIONAL ALLIANCE TO END HOMELESSNESS. *Looking Back at the VI-SPDAT Before Moving Forward.* Washington, DC, 2022.

6. CRONLEY, C. *Invisible homelessness: Challenges and opportunities for data collection.* American Journal of Public Health, v. 110, n. 1, 2020.

7. SECRETARIA MUNICIPAL DE ASSISTÊNCIA E DESENVOLVIMENTO SOCIAL — SMADS/SP. *Norma Técnica nº 11/SMADS/2024 — Serviço Especializado de Abordagem Social às Pessoas em Situação de Rua (SEAS).* São Paulo, 2024.

8. SECRETARIA MUNICIPAL DE ASSISTÊNCIA E DESENVOLVIMENTO SOCIAL — SMADS/SP. *Norma Técnica nº 12/SMADS/2024 — SEAS Modalidade Crianças e Adolescentes.* São Paulo, 2024.

9. BRASIL. *Decreto Federal nº 7.053/2009 — Política Nacional para a População em Situação de Rua.*

10. SUPREMO TRIBUNAL FEDERAL. *Medida Cautelar na ADPF nº 976.* Relator Min. Alexandre de Moraes, 25/07/2023.

11. BELO HORIZONTE. *Portaria Conjunta SMGO/SMPU/SMASDH/SMSA/SMSP/SLU nº 009/2026.* DOM-BH, Edição 7504, 22/05/2026.

12. BELO HORIZONTE. *Decreto Municipal nº 18.690/2024 — Comitê Intersetorial de Acompanhamento e Monitoramento da Política Municipal para População em Situação de Rua.*

13. BELO HORIZONTE. *Decreto Municipal nº 16.730/2017 — Política Municipal Intersetorial para Atendimento à População em Situação de Rua.*

14. CONSELHO NACIONAL DE ASSISTÊNCIA SOCIAL; CONSELHO NACIONAL DOS DIREITOS DA CRIANÇA E DO ADOLESCENTE. *Resolução Conjunta CNAS/CONANDA nº 1/2016.*

15. INSTITUTO DE PESQUISA ECONÔMICA APLICADA — IPEA. *A população em situação de rua nos números do Cadastro Único.* Nota Técnica nº 2944, 2024.

16. THE MARKUP. *How We Investigated L.A.'s Homelessness Scoring System.* 28/02/2023.

---

*Documento gerado pela Coordenação Técnica POPRUA CRAS em 24/05/2026. Texto integral da Portaria Conjunta nº 009/2026 disponível em `docs/portaria-conjunta-009-2026-texto-integral.txt`.*
