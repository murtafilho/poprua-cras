# Levantamento de Requisitos do Modelo de Dados

**Sistema de Gestão de Zeladoria Urbana Aplicado à População em Situação de Rua**

---

**Função:** Analista de Modelo de Dados — GINFI/DIMPFI/SMPU  
**Fase:** Levantamento de requisitos — corte de dados para coleta em vistoria  
**Data:** 24 de maio de 2026  
**Sistema:** POPRUA CRAS v2  
**Contexto regulatório:** Portaria Conjunta SMGO/SMPU/SMASDH/SMSA/SMSP/SLU nº 009/2026

---

## NOTA PRELIMINAR

O presente levantamento **não constitui atribuição direta da GINFI**, cuja competência é o desenvolvimento e a manutenção de sistemas de informação. A definição de quais dados coletar sobre o fenômeno da população em situação de rua é responsabilidade das áreas de política pública — notadamente SMASDH, SMSA e SMPU — às quais cabe a expertise de domínio.

Contudo, na qualidade de analista de modelo de dados responsável pela arquitetura do sistema que entrará em produção, consideramos **necessário e urgente** apontar as lacunas identificadas no modelo de dados atual. A omissão deste levantamento resultaria em **passivo informacional a curto e médio prazo**: dados que deixam de ser coletados desde o primeiro dia de operação não podem ser recuperados retroativamente. Cada vistoria realizada sem os campos adequados é uma oportunidade de registro perdida de forma irreversível.

O sistema está tecnicamente preparado para incorporar os campos aqui propostos antes da entrada em produção. O que falta é a **validação de negócio** pelas áreas competentes. Este documento visa provocar essa validação, não substituí-la.

---

## 1. ESCOPO DO LEVANTAMENTO

Este documento registra o levantamento de requisitos de dados para o sistema POPRUA CRAS, identificando quais atributos devem ser coletados durante a vistoria de campo para atender às necessidades de gestão da zeladoria urbana aplicada à população em situação de rua no município de Belo Horizonte.

O levantamento parte de três fontes:

- **Modelo de dados em produção** — 16 atributos de complexidade, 6 encaminhamentos, dados de localização e resultado
- **Requisitos normativos** — Portaria Conjunta nº 009/2026, ADPF 976/STF, Decreto Federal nº 7.053/2009
- **Referências técnicas** — VI-SPDAT (Vulnerability Index — Service Prioritization Decision Assistance Tool), Normas Técnicas SMADS/SP nº 11 e 12/2024, literatura sobre índices de vulnerabilidade para populações em situação de rua

---

## 2. ENTIDADES DO MODELO ATUAL

O sistema opera com três entidades centrais:

```
PONTO (local físico)
  ├── endereço georreferenciado (lat/lng, PostGIS)
  ├── vínculo com endereço oficial (PRODABEL)
  └── status herdado da última vistoria

VISTORIA (registro de visita ao ponto)
  ├── data/hora da abordagem
  ├── tipo de abordagem (orientativa, comunicação, zeladoria, fiscal, monitoramento)
  ├── 16 fatores de complexidade (booleanos)
  ├── resultado da ação (persiste, impactado, extinto, ausente, não constatado, conformidade)
  ├── dados quantitativos (pessoas, kg material, abrigos)
  ├── 6 encaminhamentos
  ├── relatório descritivo
  ├── fotos
  └── workflow (comunicado → retorno agendado → finalização)

MORADOR (pessoa identificada no ponto)
  ├── nome social, apelido, gênero
  ├── documento, contato
  ├── histórico de movimentação entre pontos
  └── fotos
```

---

## 3. ANÁLISE DE GAPS

### 3.1 Atributos exigidos pela Portaria nº 009/2026 não presentes no modelo

| Artigo da Portaria | Obrigação | Atributo necessário | Entidade | Presente |
|---------------------|-----------|---------------------|----------|----------|
| Art. 6º, V | Identificar situações de risco e vulnerabilidades por escuta qualificada | Avaliação de vulnerabilidade individual | Morador | Não |
| Art. 6º, IV | Verificar se pessoa já é acompanhada pela rede | Vínculo com serviço (CRAS, CREAS, CAPS, UBS, Centro POP) | Morador | Não |
| Art. 6º, VIII | Protocolos especiais em eventos climáticos | Condição climática vigente na abordagem | Vistoria | Não |
| Art. 7º, I | Condicionar ação fiscal a esgotamento registrado de mediação | Contagem de abordagens orientativas prévias | Derivado | Não |
| Art. 6º, II, g | Produção de dados sobre o público | Perfil temporal (tempo em situação de rua) | Morador | Não |
| Art. 6º, V | Encaminhamentos levando em consideração vínculos e demandas | Demandas identificadas além dos encaminhamentos | Morador | Não |
| Art. 3º, VII | Recicláveis como meios de trabalho | Diferenciação entre pertences e material de trabalho | Vistoria | Parcial |
| Art. 6º, II, b | Identificação de territórios com conflitos | Classificação do território (tipo de uso do solo) | Ponto | Não |

### 3.2 Atributos recomendados pela literatura técnica não presentes no modelo

| Fonte | Atributo | Justificativa | Entidade | Presente |
|-------|----------|---------------|----------|----------|
| VI-SPDAT, domínio 1 | Tempo em situação de rua (faixas) | Principal preditor de cronificação. Diferencia abordagem emergencial de acompanhamento de longo prazo | Morador | Não |
| VI-SPDAT, domínio 2 | Histórico de violência ou exploração | Fator de risco de morte. Mortalidade 5x maior em pessoas que relatam violência recorrente (Fazel et al., 2014) | Morador/Vistoria | Não |
| VI-SPDAT, wellness | Risco de autolesão ou tentativa de suicídio | Indicador de urgência máxima. Peso superior a qualquer outro fator isolado no VI-SPDAT | Morador | Não |
| VI-SPDAT, wellness | Condição de saúde crônica | Tri-morbidade (saúde mental + dependência + crônica) triplica mortalidade (Aldridge et al., 2018) | Morador | Não |
| VI-SPDAT, wellness | Ponderação diferenciada dos fatores | Saúde e risco pesam mais que fatores operacionais. Escala uniforme subestima vulnerabilidade real | Cálculo | Parcial — pesos configuráveis mas não validados |
| NT 11/SMADS/2024 | Complexidade territorial | O território modifica a natureza da intervenção independentemente do perfil individual | Ponto | Não |
| NT 12/SMADS/2024 | Faixa etária de crianças | Diferenciar 0-6, 7-11, 12-17 — cada faixa exige abordagem e encaminhamento distintos | Morador | Não |
| ADPF 976/STF | Recusa formalizada de acolhimento | Registro obrigatório para demonstrar que oferta foi feita antes de escalar intervenção | Vistoria | Não |

---

## 4. PROPOSTA DE NOVOS ATRIBUTOS

### 4.1 Entidade VISTORIA — novos campos

Dados coletados por **observação direta** do agente no ponto. Não dependem de interação verbal com o morador.

| Campo | Tipo | Valores | Obrigatório | Justificativa |
|-------|------|---------|-------------|---------------|
| `condicao_climatica` | enum | `normal`, `protocolo_chuva`, `protocolo_frio`, `calor_extremo` | Sim | Art. 6º, VIII — rastrear protocolos especiais. Pode ser preenchido automaticamente via API meteorológica |
| `evidencia_violencia` | boolean | sim/não | Não | Art. 6º, V — observação de marcas, relatos espontâneos, contexto de exploração |
| `recusa_acolhimento` | boolean | sim/não | Não | Art. 7º, I — formalizar oferta e recusa para rastreabilidade |
| `recusa_acolhimento_motivo` | text (255) | livre | Não | Complemento qualitativo da recusa — subsidia análise de barreiras de acesso |
| `classificacao_territorial` | enum | `centro_comercial`, `residencial`, `via_arterial`, `area_verde`, `viaduto`, `praca`, `outro` | Sim (1ª vistoria do ponto) | Art. 6º, II, b — vinculado ao ponto, preenchido uma vez e herdado |

**Impacto no formulário:** 3 campos adicionais na aba "Dados" (condição climática) e na aba "Relatório" (recusa de acolhimento). A classificação territorial vai no cadastro do ponto, não na vistoria.

### 4.2 Entidade MORADOR — novos campos

Dados coletados por **escuta qualificada**. São atributos da pessoa, persistem entre vistorias. Preenchidos no modal de cadastro/edição de morador.

| Campo | Tipo | Valores | Obrigatório | Justificativa |
|-------|------|---------|-------------|---------------|
| `tempo_situacao_rua` | enum | `menos_6m`, `6m_2a`, `2a_5a`, `mais_5a`, `nao_informado` | Não | VI-SPDAT domínio 1 — diferencia cronificação de situação recente |
| `vinculo_servico` | set (multi-seleção) | `cras`, `creas`, `centro_pop`, `caps`, `ubs`, `consultorio_rua`, `nenhum` | Não | Art. 6º, IV e V — verificar acompanhamento existente e evitar duplicidade |
| `condicao_cronica` | set (multi-seleção) | `diabetes`, `hiv`, `tuberculose`, `hipertensao`, `outra`, `nenhuma` | Não | Tri-morbidade: coocorrência com saúde mental e dependência triplica risco |
| `risco_autolesao` | boolean | sim/não | Não (sensível) | VI-SPDAT — indicador de urgência máxima. Encaminhamento imediato ao CAPS |
| `faixa_etaria_crianca` | enum | `0_6`, `7_11`, `12_17` | Condicional (se criança/adolescente) | NT 12/SMADS/2024 — cada faixa exige abordagem e rede de proteção distintas |

**Nota sobre privacidade:** campos de saúde (`condicao_cronica`, `risco_autolesao`) devem ser tratados como dados sensíveis conforme LGPD (Art. 11). O sistema deve registrar que a coleta é voluntária e não condiciona atendimento (Decreto 7.053/2009, Art. 7º).

### 4.3 Atributos derivados (calculados pelo sistema)

Não requerem input do agente. O sistema calcula com base nos dados existentes.

| Indicador | Regra de cálculo | Uso |
|-----------|------------------|-----|
| `info_precaria` | Última vistoria do ponto > 60 dias (configurável) | Priorizar pontos sem acompanhamento recente |
| `mediacao_esgotada` | ≥ 3 vistorias do tipo "Orientativa" ou "Comunicação" no ponto sem mudança de resultado | Condição para escalar a ação fiscal (Art. 7º, I) |
| `trimorbidade` | Morador com `saude_mental` + `agrupamento_quimico` + `condicao_cronica` ≠ nenhuma | Bônus automático de +3 na complexidade do ponto |
| `complexidade_ponderada` | Soma dos 16 fatores × pesos configuráveis + bônus tri-morbidade | Score de priorização 0-28+ |

---

## 5. MODELO DE CAPTURA EM CAMPO

O formulário de vistoria opera em **5 etapas** (stepper mobile-first). A proposta posiciona cada novo campo na etapa mais natural para o fluxo de trabalho do agente:

```
ETAPA 1 — DADOS
├── Localização (existente)
├── Data/Hora da abordagem (existente)
├── Tipo de abordagem (existente)
├── Condição climática (NOVO — select, preenchido junto com data/hora)
├── Data prevista zeladoria (existente, condicional)
└── Participantes (existente)

ETAPA 2 — PERFIL DA OCORRÊNCIA
├── Quantidade de pessoas, kg material (existente)
├── Abrigos (existente)
├── Fatores de complexidade — 16 checkboxes (existente)
│   └── + Evidência de violência/exploração (NOVO — checkbox junto aos demais)
└── Nomes das pessoas (existente)

ETAPA 3 — RELATÓRIO
├── Resultado da ação (existente)
├── Ações realizadas (existente)
├── Recusa de acolhimento formalizada (NOVO — sim/não + motivo)
├── Comunicado, lavratura, protocolo (existente)
├── Encaminhamentos (existente)
└── Relatório descritivo (existente)

ETAPA 4 — MORADORES E FOTOS
├── Moradores do ponto (existente)
│   Modal de cadastro:
│   ├── Nome social, apelido, gênero (existente)
│   ├── Documento, contato (existente)
│   ├── Tempo em situação de rua (NOVO — select de faixas)
│   ├── Vínculo com serviço da rede (NOVO — multi-seleção)
│   ├── Condição crônica (NOVO — multi-seleção, opcional)
│   └── Risco de autolesão (NOVO — sim/não, opcional e sensível)
└── Fotos da vistoria (existente)

ETAPA 5 — REVISÃO
├── Checklist de validação (existente)
├── Salvar (existente)
└── Salvar e Finalizar (existente)
```

**Princípio de design:** nenhum campo novo impede o salvamento da vistoria. Todos os campos propostos são **opcionais ou condicionais**, preservando a velocidade de preenchimento em campo. A informação é enriquecida progressivamente conforme o vínculo com o morador se aprofunda.

---

## 6. VOLUMETRIA E IMPACTO

| Métrica | Valor atual | Projeção com novos campos |
|---------|-------------|---------------------------|
| Campos na vistoria | 42 | 45 (+3) |
| Campos no morador | 6 | 11 (+5) |
| Tempo médio de preenchimento por vistoria | ~7 min | ~8-9 min (+1-2 min) |
| Tamanho do registro no banco (bytes) | ~800 | ~950 (+19%) |
| Atributos derivados (calculados) | 1 (info_precaria) | 4 (+3) |

O acréscimo de 1-2 minutos por vistoria é justificável pelo ganho em qualidade de dados. Os campos mais demorados (dados do morador) só são preenchidos uma vez por pessoa — nas vistorias subsequentes, o morador já está cadastrado.

---

## 7. DEPENDÊNCIAS E RISCOS

| Risco | Mitigação |
|-------|-----------|
| Agentes não preenchem campos opcionais | Treinamento focado no valor do dado. Relatórios de completude por equipe |
| Dados de saúde coletados sem consentimento | Campo marcado como opcional. Tela exibe aviso de voluntariedade antes da seção de saúde |
| Sobrecarga do formulário em campo (celular, sol, chuva) | Todos os novos campos são select/checkbox — nenhum campo de texto livre adicionado na vistoria |
| Inconsistência entre dados do morador em vistorias diferentes | Dados do morador são da entidade Morador, não da Vistoria — atualizados uma vez, consultados em todas |
| Integração com CadÚnico/e-SUS | Fora do escopo deste levantamento. Campos foram nomeados com compatibilidade futura em mente |

---

## 8. ITENS PENDENTES DE DEFINIÇÃO

Os seguintes pontos requerem decisão das áreas de negócio antes da implementação:

| # | Item | Área responsável |
|---|------|------------------|
| 1 | Validar se os 5 campos de morador são adequados ao contexto CRAS | SMASDH |
| 2 | Definir se condição climática será preenchida manualmente ou via API meteorológica | SMPU / TI |
| 3 | Aprovar pesos de complexidade propostos na Nota Técnica | Comissão Art. 11 |
| 4 | Definir se "esgotamento de mediação" (≥ 3 abordagens) é o threshold correto | SMPU |
| 5 | Avaliar necessidade de campo adicional para tipo de pertence (pessoal vs. trabalho) | SMPU / SLU |
| 6 | Definir política de acesso aos dados sensíveis (saúde) por perfil de usuário | SMASDH / SMSA |
| 7 | Confirmar intervalo de Informação Precária (60 dias) | Comissão Art. 11 |

---

## 9. REFERÊNCIAS TÉCNICAS

1. OrgCode Consulting. *Service Prioritization Decision Assistance Tool (SPDAT) — Manual.* Hamilton, Canadá, 2015.
2. Brown, M. et al. *Reliability and validity of the VI-SPDAT in real-world implementation.* Journal of Social Distress and the Homeless, v. 27, n. 2, 2018.
3. Fazel, S. et al. *The health of homeless people in high-income countries.* The Lancet, v. 384, p. 1529-1540, 2014.
4. Aldridge, R. W. et al. *Morbidity and mortality in homeless individuals.* The Lancet, v. 391, p. 241-250, 2018.
5. National Alliance to End Homelessness. *Looking Back at the VI-SPDAT Before Moving Forward.* Washington, 2022.
6. The Markup. *How We Investigated L.A.'s Homelessness Scoring System.* 28/02/2023.
7. SMADS/SP. *Norma Técnica nº 11/SMADS/2024 — SEAS Adulto e Misto.* São Paulo, 2024.
8. SMADS/SP. *Norma Técnica nº 12/SMADS/2024 — SEAS Crianças e Adolescentes.* São Paulo, 2024.
9. CNAS/CONANDA. *Resolução Conjunta nº 1/2016.*
10. IPEA. *A população em situação de rua nos números do Cadastro Único.* Nota Técnica nº 2944, 2024.
11. Portaria Conjunta SMGO/SMPU/SMASDH/SMSA/SMSP/SLU nº 009/2026. DOM-BH, Ed. 7504, 22/05/2026.
12. STF. Medida Cautelar na ADPF nº 976. Rel. Min. Alexandre de Moraes, 25/07/2023.
13. Brasil. Decreto Federal nº 7.053/2009.

---

*Levantamento elaborado pela GINFI/DIMPFI/SMPU na qualidade de analista de modelo de dados do sistema POPRUA CRAS.*  
*A definição dos campos a serem coletados é competência das áreas de política pública (SMASDH, SMSA, SMPU).*  
*Este documento não substitui essa definição — provoca-a, sob o fundamento de que a ausência de deliberação antes da entrada em produção resultará em passivo informacional cuja recuperação será custosa ou impossível.*  
*Belo Horizonte, 24 de maio de 2026.*
