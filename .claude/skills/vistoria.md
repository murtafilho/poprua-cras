# Skill: Vistoria - Workflow Completo

Use esta skill quando o usuário quiser criar, editar ou gerenciar vistorias no sistema POPRUA v2.

## Contexto do Sistema

O sistema de vistorias é o coração do POPRUA v2, registrando abordagens a pontos onde há população em situação de rua em Belo Horizonte.

### Estrutura Principal

- **Model**: `app/Models/Vistoria.php`
- **Controller**: `app/Http/Controllers/VistoriaController.php`
- **Views**: `resources/views/vistorias/`
- **Services**: `EnderecoService`, `MoradorService`

### Relacionamentos da Vistoria

| Relacionamento | Descrição |
|----------------|-----------|
| `ponto` | Local georreferenciado da vistoria |
| `user` | Usuário que cadastrou |
| `tipoAbordagem` | Tipo da abordagem realizada |
| `resultadoAcao` | Resultado da ação |
| `encaminhamento1-4` | Até 4 encaminhamentos |
| `fotos` | Fotos via Spatie Media Library |
| `moradoresEntrada/Saida` | Histórico de moradores |

## Workflow: Criar Nova Vistoria

### Passo 1: Preparação

1. Obter coordenadas (lat, lng) do local da vistoria
2. Buscar ponto existente próximo (50m de raio)
3. Se não houver ponto próximo, um novo será criado

### Passo 2: Dados Obrigatórios

```php
$dados = [
    'data_abordagem' => 'Y-m-d\TH:i',     // Data e hora
    'tipo_abordagem_id' => 'integer',      // FK tipo_abordagem
    'resultado_acao_id' => 'integer',      // FK resultados_acoes
    'quantidade_pessoas' => 'integer',     // Quantidade de pessoas
];
```

### Passo 3: Dados Opcionais por Categoria

**Perfil das Pessoas:**
- `nomes_pessoas` - Nomes identificados
- `casal`, `qtd_casais` - Casais presentes
- `classificacao` - Classificação do grupo
- `num_reduzido` - Número reduzido
- `catador_reciclados` - Se são catadores

**Vulnerabilidades (booleanos):**
- `crianca_adolescente`, `idosos`, `gestante`
- `lgbtqiapn`, `deficiente`, `saude_mental`
- `agrupamento_quimico`, `trafico_ilicitos`

**Situação do Local:**
- `resistencia` - Houve resistência
- `fixacao_antiga` - Fixação antiga
- `excesso_objetos`, `qtd_kg` - Materiais
- `abrigos_tipos`, `qtd_abrigos_provisorios` - Abrigos
- `cena_uso_caracterizada` - Cena de uso

**Fiscalização:**
- `conducao_forcas_seguranca` - Condução forças
- `apreensao_fiscal` - Apreensão realizada
- `auto_fiscalizacao_aplicado`, `auto_fiscalizacao_numero`

**Encaminhamentos:**
- `e1_id`, `e2_id`, `e3_id`, `e4_id` - Até 4 encaminhamentos

### Passo 4: Upload de Fotos

```php
// Fotos via Spatie Media Library
$vistoria->addMediaFromRequest('fotos')
    ->toMediaCollection('fotos');

// Conversões automáticas: thumb (300x300), preview (800x600)
// MIME aceitos: jpeg, png, webp
// Max: 10MB por foto
```

### Passo 5: Registro de Moradores

```php
// Usar MoradorService para:
// 1. Criar novos moradores com entrada no ponto
$moradorService->criarComEntrada($dados, $ponto, $vistoria);

// 2. Atualizar presença de moradores existentes
$moradorService->atualizarPresencaVistoria($vistoria, $moradorIds);
```

## Workflow: Editar Vistoria

### Restrições na Edição

- Localização (lat/lng) **NÃO pode ser alterada**
- Moradores **NÃO são editados** (apenas na criação)
- Fotos podem ser removidas e novas adicionadas

### Processo

1. Carregar vistoria com relacionamentos
2. Atualizar campos permitidos
3. Processar fotos (remover selecionadas + upload novas)
4. Redirect para lista com sucesso

## Validação de Dados

```php
// Regras principais
'data_abordagem' => 'required|date_format:Y-m-d\TH:i',
'tipo_abordagem_id' => 'required|exists:tipo_abordagem,id',
'resultado_acao_id' => 'required|exists:resultados_acoes,id',
'quantidade_pessoas' => 'nullable|integer|min:0',
'qtd_kg' => 'nullable|numeric|min:0',
'fotos.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
'abrigos_tipos' => 'nullable|array',
'abrigos_tipos.*' => 'exists:caracteristica_abrigo,id',
```

## Tabelas de Referência

Para popular selects, usar:

```php
DB::table('tipo_abordagem')->orderBy('id')->get();
DB::table('tipo_abrigo_desmontado')->orderBy('id')->get();
DB::table('resultados_acoes')->orderBy('id')->get();
DB::table('encaminhamentos')->orderBy('id')->get();
DB::table('caracteristica_abrigo')->orderBy('id')->get();
```

## Rotas Disponíveis

| Rota | Método | Descrição |
|------|--------|-----------|
| `vistorias.index` | GET | Lista com filtros |
| `vistorias.create` | GET | Formulário criação |
| `vistorias.store` | POST | Salvar nova |
| `vistorias.show` | GET | Detalhes |
| `vistorias.edit` | GET | Formulário edição |
| `vistorias.update` | PUT | Atualizar |
| `pontos.vistorias.create` | GET | Criar para ponto específico |

## Services Utilizados

### EnderecoService

```php
// Vincular endereço ao ponto
$enderecoService->vincularEnderecoAoPonto($pontoId, $lat, $lng, $complemento);

// Buscar endereço próximo (300m)
$enderecoService->buscarEnderecoMaisProximo($lat, $lng);
```

### MoradorService

```php
// Criar morador com entrada
$moradorService->criarComEntrada($dados, $ponto, $vistoria);

// Registrar entrada em ponto
$moradorService->registrarEntrada($morador, $ponto, $vistoria);

// Registrar saída
$moradorService->registrarSaida($morador, $vistoria);

// Transferir entre pontos
$moradorService->transferir($morador, $pontoDestino, $vistoriaEntrada);
```

## Instruções para o Agente

Ao receber solicitação de vistoria:

1. **Perguntar o objetivo**: criar, editar, listar ou debugar
2. **Para criar**: solicitar coordenadas ou nome do local
3. **Para editar**: solicitar ID da vistoria
4. **Validar dados**: usar regras acima antes de salvar
5. **Testar**: rodar testes relacionados após alterações

### Comandos Úteis

```bash
# Rodar testes de vistoria
php artisan test --filter=Vistoria

# Verificar rotas
php artisan route:list --name=vistoria

# Tinker para debug
php artisan tinker
>>> Vistoria::with(['ponto', 'tipoAbordagem'])->latest()->first()
```

## Checklist de Implementação

- [ ] Validar coordenadas (lat entre -90 e 90, lng entre -180 e 180)
- [ ] Buscar/criar ponto antes de salvar vistoria
- [ ] Vincular endereço via EnderecoService
- [ ] Processar fotos com Spatie Media Library
- [ ] Registrar moradores via MoradorService
- [ ] Usar transações para operações complexas
- [ ] Rodar pint após alterações de código
- [ ] Testar criação e edição
