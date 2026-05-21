# Regras de Negocio — POPRUA CRAS

Audiencia: equipe de desenvolvimento e time de produto. Descreve o comportamento esperado do sistema extraido diretamente do codigo-fonte. Atualizado em 2026-05-20.

---

## 1. Dominio Central: Ponto, Vistoria e Morador

O nucleo do sistema organiza-se em tres entidades principais com uma hierarquia clara.

### Ponto

Um Ponto representa um local fisico onde pessoas em situacao de rua estao ou estiveram. Cada Ponto possui:

- Coordenadas geograficas (lat/lng) armazenadas como `decimal(17,14)` e replicadas em coluna PostGIS `geom POINT SRID 4326` com indice GIST para consultas espaciais
- Vinculo opcional com `EnderecoAtualizado` (tabela geocodificada da prefeitura)
- Numero e complemento descritivo do local
- Caracteristica do abrigo (`caracteristica_abrigo_id`)
- Observacao livre (`observacao`)
- Soft delete — exclusao logica, registro e preservado no banco

Um Ponto pode existir sem endereco georreferenciado. O sistema separa Pontos georreferenciados (`lat` e `lng` preenchidos e != 0) dos nao georreferenciados.

A complexidade de um Ponto e calculada somando os 16 flags booleanos de vulnerabilidade da sua ultima Vistoria. O calculo e exposto como atributo computado `$ponto->complexidade` e como constante SQL `Ponto::COMPLEXIDADE_SQL` para uso em queries otimizadas.

### Vistoria

Uma Vistoria representa uma abordagem ou visita de campo realizada por um agente no local do Ponto. E o registro central de cada intervencao. Uma Vistoria pertence a exatamente um Ponto e a exatamente um Usuario (o responsavel que a criou).

Cada Vistoria captura:
- Data e hora da abordagem
- Tipo de abordagem (lookup `tipo_abordagem`)
- Quantidade e nomes das pessoas encontradas
- 16 flags booleanos de complexidade/vulnerabilidade
- Dados de fiscalizacao e lavratura
- Ate 6 encaminhamentos a servicos
- Participantes da equipe de campo
- Fotos com legendas
- Resultado da acao (lookup `resultados_acoes`)
- Planejamento de zeladoria (data prevista e periodo)
- Dados de moradores presentes ou novos

### Morador

Um Morador representa uma pessoa identificada que reside ou residiu em um ou mais Pontos. Cada Morador possui:

- Nome social (obrigatorio), nome de registro e apelido
- Genero, documento e contato
- Observacoes livres
- Vinculo com o Ponto atual (`ponto_atual_id`), que e nulo quando o morador nao esta em nenhum ponto no momento
- Historico completo de movimentacao via `MoradorHistorico`
- Fotos (Spatie MediaLibrary, colecao `fotos`)
- Soft delete

---

## 2. Ciclo de Vida da Vistoria

Uma Vistoria transita por tres estados mutuamente exclusivos determinados pelos campos booleanos `finalizada` e `cancelada`.

### Estados

| Estado | Condicao | Descricao |
|---|---|---|
| Aberta | `finalizada = false` e `cancelada = false` | Estado inicial. Pode ser editada pelo dono. |
| Finalizada | `finalizada = true` e `cancelada = false` | Registro encerrado pelo dono. Nao pode ser editada, mas pode ser reativada ou cancelada por perfis privilegiados. |
| Cancelada | `cancelada = true` | Estado terminal. Nao pode ser editada nem reativada. |

### Transicoes

```
             [criacao]
                 |
                 v
            [Aberta]
           /        \
     finalizar     cancelar (dono)
         |               \
         v                v
    [Finalizada]      [Cancelada]  <-- estado terminal
     /       \
reativar   cancelar
(permissao) (permissao)
     |
     v
  [Aberta]
```

**Detalhes de cada transicao:**

- **Aberta -> Finalizada:** executada pelo dono da vistoria via `POST /vistorias/{id}/finalizar` ou pelo checkbox "Salvar e Finalizar" no formulario de edicao. Registra `finalizada_em = now()` e `finalizada_por = auth()->id()`.
- **Finalizada -> Aberta (Reativar):** requer permissao `reativar vistorias`. Limpa `finalizada_em` e `finalizada_por`.
- **Aberta -> Cancelada:** qualquer dono pode cancelar sua propria vistoria aberta. Registra `cancelada_em = now()` e `cancelada_por = auth()->id()`.
- **Finalizada -> Cancelada:** requer permissao `cancelar vistorias`. Mesmo registro de auditoria.
- **Cancelada -> qualquer estado:** nao e permitido. A policy retorna `false` para cancelar e false para update quando `cancelada = true`.

### Complementacao

Uma Vistoria Finalizada pode receber complementacoes textuais. Qualquer usuario autenticado pode acionar `POST /vistorias/{id}/complementar` informando uma justificativa (10 a 1000 caracteres). O texto e acrescentado ao campo `observacao` com cabecalho de data, hora e nome do usuario. Nao altera o estado da Vistoria.

### Exclusao (soft delete)

A exclusao via `DELETE /vistorias/{id}` realiza soft delete (preenche `deleted_at`). O dono pode excluir sua propria vistoria independentemente do estado. Usuarios com permissao `excluir vistorias` tambem podem excluir qualquer vistoria (via `before()` na policy). A restauracao (`restore`) requer permissao `excluir vistorias`. O force delete e sempre bloqueado pela policy.

---

## 3. Regras de Criacao (StoreVistoriaRequest)

Campos obrigatorios para criar uma Vistoria:

| Campo | Tipo | Restricao |
|---|---|---|
| `lat` | numeric | Obrigatorio. Entre -90 e 90. |
| `lng` | numeric | Obrigatorio. Entre -180 e 180. |
| `data_abordagem` | string | Obrigatorio. Formato `Y-m-d\TH:i` (datetime-local HTML). |
| `tipo_abordagem_id` | integer | Obrigatorio. Deve existir na tabela `tipo_abordagem`. |
| `resultado_acao_id` | integer | Obrigatorio. Deve existir na tabela `resultados_acoes`. |

Campos opcionais relevantes:

| Campo | Tipo | Restricao |
|---|---|---|
| `ponto_id` | integer | Opcional. Se informado, deve existir na tabela `pontos`. Se omitido, o sistema busca ou cria um Ponto a partir de `lat`/`lng`. |
| `complemento_ponto` | string | max:255. Complemento para o Ponto (aplicado ao Ponto novo ou existente). |
| `quantidade_pessoas` | integer | min:0 |
| `nomes_pessoas` | string | Texto livre |
| `qtd_kg` | integer | min:0 |
| `observacao` | string | Texto livre |
| `fotos.*` | file | image; mimes: jpeg, jpg, png, webp; max: 10240 KB (10 MB) por arquivo |
| `legendas_fotos.*` | string | max:500 caracteres por legenda |
| `participantes.*` | integer | Deve existir na tabela `membros_equipe` |
| `moradores_presentes.*` | integer | Deve existir na tabela `moradores` |
| `novos_moradores.*.nome_social` | string | Obrigatorio ao criar morador. max:255 |
| `novos_moradores.*.apelido` | string | Opcional. max:255 |
| `novos_moradores.*.genero` | string | Opcional. max:100 |
| `novos_moradores.*.documento` | string | Opcional. max:50 |
| `novos_moradores.*.contato` | string | Opcional. max:50 |
| `novos_moradores.*.observacoes` | string | Opcional. Texto livre |

**Logica de Ponto na criacao:** se `ponto_id` nao e informado, `PontoService::findOrCreateFromCoordinates` busca um Ponto existente no raio de 50 metros das coordenadas fornecidas (via `ST_Distance`). Se nao houver, cria um novo Ponto com as coordenadas e o complemento informado.

**Moradores na criacao:** o sistema aceita dois fluxos em paralelo:
1. `moradores_presentes` — IDs de moradores ja cadastrados que estao presentes na abordagem. O servico compara com os moradores atuais do Ponto e registra entradas e saidas automaticamente.
2. `novos_moradores` — array de dados para criar novos moradores diretamente, ja vinculando-os ao Ponto via `MoradorService::criarComEntrada`.

---

## 4. Regras de Edicao (UpdateVistoriaRequest)

A edicao usa `UpdateVistoriaRequest`, que difere da criacao nos seguintes pontos:

**Campos ausentes na edicao (comparados com criacao):**

| Campo | Motivo |
|---|---|
| `lat` / `lng` | Coordenadas nao sao alteradas na edicao. O Ponto ja esta definido. |
| `complemento_ponto` | Nao previsto no formulario de edicao. |
| `legendas_fotos` / `legendas_fotos.*` | Legendas nao editaveis apos upload inicial. |
| `moradores_presentes` / `novos_moradores` | Gestao de moradores nao esta no formulario de edicao de Vistoria. |

**Campos exclusivos da edicao:**

| Campo | Tipo | Descricao |
|---|---|---|
| `remover_fotos` | array de integers | IDs das midias (tabela `media` do Spatie) a serem excluidas. |

**Restricoes de acesso:** a policy `update` bloqueia qualquer edicao se `finalizada = true` ou `cancelada = true`. Apenas o dono (`user_id === auth()->id()`) pode editar. A rota de edicao chama `$this->authorize('update', $vistoria)` antes de processar o request.

**Sincronizacao de participantes:** a edicao sempre substitui completamente a lista de participantes via `sync()`. Enviar `participantes = []` remove todos os participantes.

---

## 5. Campos de Complexidade

Sao 16 flags booleanos que descrevem caracteristicas de vulnerabilidade e risco observadas durante a abordagem. A soma desses flags constitui o indice de complexidade do Ponto (calculado a partir da ultima Vistoria).

### Grupo: Perfil das pessoas

| Campo | Descricao |
|---|---|
| `casal` | Ha casais no grupo. Acompanhado de `qtd_casais` (integer). |
| `num_reduzido` | Numero reduzido de pessoas (unico individuo ou poucos). |
| `crianca_adolescente` | Presenca de criancas ou adolescentes. |
| `idosos` | Presenca de idosos. |
| `gestante` | Presenca de gestante. |
| `lgbtqiapn` | Presenca de pessoa LGBTQIA+. |
| `deficiente` | Presenca de pessoa com deficiencia. |

### Grupo: Comportamento e contexto

| Campo | Descricao |
|---|---|
| `resistencia` | Ha resistencia a abordagem. |
| `fixacao_antiga` | Fixacao antiga no local (historico prolongado). |
| `excesso_objetos` | Excesso de objetos acumulados. |
| `catador_reciclados` | Pessoa catadora de reciclaveis. |
| `trafico_ilicitos` | Evidencia de trafico de ilicitos. |
| `cena_uso_caracterizada` | Cena de uso de drogas caracterizada. |
| `agrupamento_quimico` | Agrupamento quimico (usuarios de substancias em grupo). |
| `saude_mental` | Indicativo de transtorno de saude mental. |
| `animais` | Presenca de animais. Acompanhado de `qtd_animais` (integer). |

Todos os 16 flags participam do calculo do indice de complexidade. A constante `Ponto::COMPLEXIDADE_SQL` contem a expressao SQL equivalente para uso em queries que precisam ordenar ou filtrar Pontos por complexidade sem N+1.

---

## 6. Encaminhamentos

Uma Vistoria pode registrar ate 6 encaminhamentos a servicos ou equipamentos externos, armazenados nos campos `e1_id` ate `e6_id`. Cada campo e uma FK opcional para a tabela `encaminhamentos` (que contem apenas `id` e `encaminhamento` — o nome do servico).

Regras:
- Todos os campos sao opcionais (`nullable`).
- Nao ha obrigatoriedade de preencher em ordem. `e1_id` pode ser nulo mesmo que `e2_id` esteja preenchido.
- Na exclusao do encaminhamento cadastral, o campo na Vistoria e anulado (`nullOnDelete`).
- Os 6 relacionamentos sao expostos como relacoes separadas (`encaminhamento1` ate `encaminhamento6`) para permitir eager loading seletivo.
- `e1_id` ate `e4_id` fazem parte do schema original. `e5_id` e `e6_id` foram adicionados em migracao posterior (2026-01-28).

---

## 7. Fotos

As fotos de Vistoria sao gerenciadas pela biblioteca Spatie MediaLibrary na colecao `fotos`.

### Upload

- Formatos aceitos: `jpeg`, `jpg`, `png`, `webp`
- Tamanho maximo por arquivo: 10 MB (10240 KB)
- Nao ha limite de quantidade de fotos por Vistoria definido no codigo (o limite e de infraestrutura)
- Cada foto aceita uma legenda opcional no momento do upload (campo `legendas_fotos[index]`, max 500 caracteres). A legenda e armazenada como custom property `legenda` no registro de media.
- Na criacao: fotos e legendas sao indexadas por posicao no array.
- Na edicao: novas fotos sao acrescentadas. Fotos existentes podem ser removidas informando seus IDs em `remover_fotos[]`.

### Conversoes automaticas (processadas em fila `media-conversions`)

| Conversao | Dimensoes | Formato | Qualidade |
|---|---|---|---|
| `thumb` | 300x300 | webp | 80% |
| `preview` | 800x600 | webp | 85% |

### Sincronizacao com Google Drive

Se `GOOGLE_DRIVE_CLIENT_ID` estiver configurado, o sistema dispara o job `UploadMediaToDriveJob` para cada foto salva, tanto na criacao quanto na edicao.

### Tabela legada `vistoria_fotos`

Existe uma tabela `vistoria_fotos` com campos `caminho`, `nome_original`, `tamanho`, `mime_type`, `ordem` e `descricao`. Essa tabela e legada do poprua-geo. O fluxo atual usa exclusivamente a MediaLibrary (`media` table). A relacao `Vistoria::fotos()` aponta para `vistoria_fotos` com ordenacao por `ordem`, mas o upload e consulta de fotos no sistema atual passam pela MediaLibrary.

---

## 8. Participantes da Equipe

### MembroEquipe

Representa um profissional que pode participar de abordagens. Nao e um usuario do sistema (sem acesso a login). Atributos:

| Campo | Tipo | Descricao |
|---|---|---|
| `nome` | string | Nome completo. Obrigatorio. |
| `matricula` | string | max:30. Opcional. |
| `email` | string | Opcional. |
| `equipe` | enum | Grupo de atuacao. Valores: `supervisores`, `coordenadores`, `gcm`, `slu`, `agentes_campo`. |
| `ativo` | boolean | default: true. Inativo nao e sugerido nos formularios. |

### Vinculo com Vistoria

A tabela pivot `vistoria_participantes` associa `vistorias.id` a `membros_equipe.id`. Restricoes:

- Par `(vistoria_id, membro_equipe_id)` e unico (sem duplicatas).
- `cascadeOnDelete` — ao excluir uma Vistoria, seus participantes sao removidos da pivot automaticamente.
- A sincronizacao e feita via `$vistoria->participantes()->sync(array_of_ids)`. Na edicao, a lista e sempre substituida por completo.
- Scopes disponiveis em `MembroEquipe`: `scopeAtivos()` e `scopeEquipe(string $equipe)`.

---

## 9. Morador e Historico de Movimentacao

### Campos do Morador

| Campo | Obrigatorio | Descricao |
|---|---|---|
| `nome_social` | Sim | Nome pelo qual a pessoa e conhecida. |
| `nome_registro` | Nao | Nome em documentos oficiais. |
| `apelido` | Nao | max:255. |
| `genero` | Nao | max:100. Texto livre. |
| `observacoes` | Nao | Texto livre. |
| `documento` | Nao | max:50 (CPF, RG etc.). |
| `contato` | Nao | max:50. |
| `ponto_atual_id` | Nao | FK para `pontos`. Nulo se o morador nao esta em nenhum ponto. |

O campo `fotografia` (varchar, caminho de arquivo) foi removido em migracao de 2026-05-18. Fotos de moradores passaram a ser gerenciadas exclusivamente pela MediaLibrary (colecao `fotos`).

### MoradorHistorico

Cada linha registra a estada de um Morador em um Ponto. Campos:

| Campo | Descricao |
|---|---|
| `morador_id` | FK para `moradores` |
| `ponto_id` | FK para `pontos` |
| `vistoria_entrada_id` | FK opcional para a Vistoria que registrou a chegada |
| `vistoria_saida_id` | FK opcional para a Vistoria que registrou a saida |
| `data_entrada` | Data (date) de entrada. Obrigatorio. |
| `data_saida` | Data (date) de saida. Nulo enquanto morador ainda esta no ponto. |

Um historico "aberto" e aquele com `data_saida = null`. Um Morador nao pode ter mais de um historico aberto simultaneamente (o `MoradorService` fecha o historico anterior antes de criar um novo).

### Operacoes do MoradorService

| Operacao | Descricao |
|---|---|
| `registrarEntrada` | Fecha historico aberto anterior (se houver), atualiza `ponto_atual_id` do morador e cria novo `MoradorHistorico`. |
| `registrarSaida` | Fecha historico aberto e anula `ponto_atual_id`. |
| `transferir` | Fecha historico aberto (registrando vistoria de saida) e cria historico no novo ponto (registrando vistoria de entrada). Funciona como uma saida + entrada atomica. |
| `criarComEntrada` | Cria o `Morador` e chama `registrarEntrada` em transacao unica. |
| `atualizarPresencaVistoria` | Compara lista de IDs informados com moradores atuais do ponto: os que sumiram recebem saida, os novos recebem entrada. Aceita tambem criacao de novos moradores inline. |

Todas as operacoes que alteram estado de moradores rodam dentro de `DB::transaction`.

---

## 10. Rotas Disponiveis

Todas as rotas de Vistoria requerem autenticacao (middleware `auth`).

| Metodo | URI | Nome | Descricao |
|---|---|---|---|
| GET | `/vistorias` | `vistorias.index` | Listagem com filtros (logradouro, bairro, regional, resultado, datas, supervisor, data_prevista) |
| GET | `/vistorias/create` | `vistorias.create` | Formulario de criacao (aceita `lat`, `lng` e dados de endereco por query string) |
| POST | `/vistorias` | `vistorias.store` | Persistencia de nova Vistoria |
| GET | `/vistorias/{id}` | `vistorias.show` | Detalhe da Vistoria |
| GET | `/vistorias/{id}/relatorio` | `vistorias.report` | View de relatorio imprimivel |
| GET | `/vistorias/{id}/edit` | `vistorias.edit` | Formulario de edicao (bloqueado por policy se finalizada ou cancelada) |
| PUT | `/vistorias/{id}` | `vistorias.update` | Persistencia de edicao |
| DELETE | `/vistorias/{id}` | `vistorias.destroy` | Soft delete |
| POST | `/vistorias/{id}/finalizar` | `vistorias.finalizar` | Transicao Aberta -> Finalizada |
| POST | `/vistorias/{id}/reativar` | `vistorias.reativar` | Transicao Finalizada -> Aberta (requer permissao) |
| POST | `/vistorias/{id}/cancelar` | `vistorias.cancelar` | Transicao -> Cancelada |
| POST | `/vistorias/{id}/complementar` | `vistorias.complementar` | Adiciona texto a observacao de Vistoria Finalizada |
| GET | `/minhas-vistorias` | `vistorias.minhas` | Listagem das vistorias do usuario autenticado |
| GET | `/vistorias/roteiro` | `vistorias.roteiro` | Exportacao de roteiro por data prevista e regional |
| GET | `/pontos/{ponto}/vistorias/create` | `pontos.vistorias.create` | Redireciona para criacao pre-populada com coordenadas do Ponto |

---

## 11. Tabela Resumo de Permissoes

### Permissoes Spatie (atribuidas a roles via admin)

| Permissao | Descricao |
|---|---|
| `ver vistorias` | Listar e visualizar vistorias |
| `criar vistorias` | Criar novas vistorias |
| `editar vistorias proprias` | Editar proprias vistorias abertas |
| `editar qualquer vistoria` | Editar vistorias de qualquer usuario |
| `excluir vistorias` | Excluir (soft delete) e restaurar vistorias |
| `reativar vistorias` | Reativar vistorias finalizadas |
| `cancelar vistorias` | Cancelar vistorias finalizadas |

### Regras de negocio da VistoriaPolicy (independentes de role)

| Acao | Estado necessario | Regra |
|---|---|---|
| Editar | Aberta | Apenas o dono (`user_id`) |
| Finalizar | Aberta | Apenas o dono |
| Salvar e Finalizar | Aberta | Apenas o dono |
| Cancelar | Aberta | Apenas o dono |
| Cancelar | Finalizada | Requer permissao `cancelar vistorias` |
| Reativar | Finalizada (nao cancelada) | Requer permissao `reativar vistorias` |
| Complementar | Finalizada | Qualquer usuario autenticado |
| Excluir (soft) | Qualquer estado | Dono, ou permissao `excluir vistorias` |
| Restaurar | Soft-deleted | Requer permissao `excluir vistorias` |
| Force delete | Qualquer | Bloqueado para todos |

### Acesso administrativo

Rotas sob `/admin` requerem o middleware `role:admin`. Isso inclui gerenciamento de roles, permissoes, usuarios, matriz de permissoes e infraestrutura. O admin pode excluir/restaurar vistorias via policy `before()` (hook que retorna `true` quando o usuario tem `excluir vistorias`, antes de avaliar qualquer outra regra de delete).

---

## 12. Tabelas de Lookup

O sistema possui tabelas de referencia que nao possuem regras de negocio proprias mas sao referenciadas como FK obrigatoria ou opcional nas vistorias:

| Tabela | Campo FK em Vistoria | Descricao |
|---|---|---|
| `tipo_abordagem` | `tipo_abordagem_id` | Tipo da abordagem realizada (obrigatorio) |
| `resultados_acoes` | `resultado_acao_id` | Resultado da acao (obrigatorio) |
| `encaminhamentos` | `e1_id` ate `e6_id` | Servicos de encaminhamento (opcional, ate 6) |
| `tipo_abrigo_desmontado` | `tipo_abrigo_desmontado_id` | Tipo do abrigo desmontado (opcional) |
| `caracteristica_abrigo` | `caracteristica_abrigo_id` (no Ponto) | Caracteristica do abrigo do Ponto (opcional) |

---

## 13. Zeladoria

Zeladoria e o termo usado na interface para denominar uma Vistoria. Os campos especificos de planejamento de zeladoria sao:

| Campo | Tipo | Descricao |
|---|---|---|
| `data_prevista_zeladoria` | date | Data prevista para a proxima acao de zeladoria no local |
| `periodo_zeladoria` | string | Periodo do dia: `manha` ou `tarde` |

A exportacao de roteiro (`GET /vistorias/roteiro`) filtra vistorias por `data_prevista_zeladoria` dentro de um intervalo, opcionalmente por supervisor e regional, para planejamento de campo.
