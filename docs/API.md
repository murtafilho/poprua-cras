# API Reference — POPRUA v2

Todas as rotas API requerem autenticacao via session cookie (middleware `web` + `auth`), exceto as rotas GeoJSON que sao publicas.

Base URL: `/api`

## Pontos

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | `/pontos?north=&south=&east=&west=` | Pontos na bounding box (mapa) |
| GET | `/pontos/{id}` | Detalhes de um ponto |
| PATCH | `/pontos/{id}/coordenadas` | Atualizar lat/lng de um ponto |
| GET | `/pontos/{ponto}/moradores` | Moradores vinculados a um ponto |
| GET | `/pontos/nao-georreferenciados/logradouros?q=` | Autocomplete de logradouros sem geo |

## Enderecos

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | `/enderecos/logradouros?q=&numero=` | Autocomplete de logradouros |
| GET | `/enderecos/buscar?logradouro=&numero=&regional=` | Busca endereco por logradouro/numero |
| GET | `/enderecos/pesquisar?q=` | Pesquisa textual de enderecos |
| GET | `/enderecos/por-coordenadas?lat=&lng=` | Reverse geocoding (endereco mais proximo) |

## Geocoding

| Metodo | Rota | Descricao |
|--------|------|-----------|
| POST | `/geocode` | Geocodifica endereco via Nominatim (body: `endereco`, `cidade`, `estado`) |

## GeoJSON (publico)

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | `/geo/bairros` | FeatureCollection de bairros (cache 24h) |
| GET | `/geo/regionais` | FeatureCollection de regionais (cache 24h) |
| GET | `/geo/limite-municipio` | FeatureCollection do limite municipal |

## Vistorias

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | `/vistorias/logradouros?q=&numero=` | Autocomplete de logradouros com vistorias |
| POST | `/vistorias/fotos` | Upload de foto (body: `vistoria_id`, `foto`) |
| GET | `/vistorias/{vistoria}/fotos/status` | Status das fotos (urls, cloud_status) |

### Upload de fotos

```
POST /api/vistorias/fotos
Content-Type: multipart/form-data

vistoria_id: integer (required)
foto: file (required, image, jpeg/jpg/png/webp, max 10MB)
```

Resposta 201:
```json
{
  "id": 42,
  "url": "/storage/42/foto.jpg",
  "thumb": "/storage/42/conversions/foto-thumb.webp"
}
```

## Moradores

Todos os endpoints requerem autenticacao. Dados PII protegidos.

| Metodo | Rota | Descricao |
|--------|------|-----------|
| GET | `/moradores?page=&per_page=&search=&genero=&ponto_id=&situacao=` | Lista paginada |
| GET | `/moradores/buscar?termo=` | Busca por nome/apelido (min 2 chars) |
| GET | `/moradores/arquivados?search=` | Lista de moradores soft-deleted |
| POST | `/moradores` | Criar morador |
| GET | `/moradores/{id}` | Detalhes do morador |
| PUT | `/moradores/{id}` | Atualizar morador |
| DELETE | `/moradores/{id}` | Soft-delete morador |
| POST | `/moradores/{id}/restaurar` | Restaurar morador deletado |
| GET | `/moradores/{id}/historico` | Historico de movimentacao |
| POST | `/moradores/{id}/entrada` | Registrar entrada em ponto (body: `ponto_id`, `vistoria_id`) |
| POST | `/moradores/{id}/saida` | Registrar saida do ponto (body: `vistoria_id`) |
| POST | `/moradores/{id}/transferir` | Transferir entre pontos (body: `ponto_id`, `vistoria_id`) |

### Criar morador

```
POST /api/moradores
Content-Type: application/json

{
  "nome_social": "string (required)",
  "apelido": "string (optional)",
  "genero": "string (optional)",
  "data_nascimento": "date (optional)",
  "cpf": "string (optional)",
  "ponto_id": "integer (optional, vincula ao ponto)"
}
```

### Entrada / Saida / Transferencia

```
POST /api/moradores/{id}/entrada
{ "ponto_id": 123, "vistoria_id": 456 }

POST /api/moradores/{id}/saida
{ "vistoria_id": 456 }

POST /api/moradores/{id}/transferir
{ "ponto_id": 789, "vistoria_id": 456 }
```

## Client Logs

| Metodo | Rota | Descricao |
|--------|------|-----------|
| POST | `/client-logs` | Log de debug do mobile (body: `level`, `message`, `context`) |
