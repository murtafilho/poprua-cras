<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ponto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PontoService
{
    public function __construct(private EnderecoService $enderecoService) {}

    /** @return array{id: int, created: bool} */
    public function findOrCreateFromCoordinates(float $lat, float $lng, ?string $complemento = null): array
    {
        $pontoProximo = Ponto::query()
            ->whereRaw('geom && ST_Expand(ST_SetSRID(ST_MakePoint(?, ?), 4326)::geometry, 0.001)', [$lng, $lat])
            ->whereRaw('ST_Distance(geom::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography) < 50', [$lng, $lat])
            ->orderByRaw('geom <-> ST_SetSRID(ST_MakePoint(?, ?), 4326)', [$lng, $lat])
            ->first();

        if ($pontoProximo) {
            return ['id' => $pontoProximo->id, 'created' => false];
        }

        $ponto = Ponto::create([
            'lat' => $lat,
            'lng' => $lng,
            'numero' => 'S/N',
        ]);

        $this->enderecoService->vincularEnderecoAoPonto($ponto->id, $lat, $lng, $complemento);

        return ['id' => $ponto->id, 'created' => true];
    }

    /**
     * Busca pontos dentro de uma bounding box para exibição no mapa.
     *
     * @return Collection<int, \stdClass>
     */
    public function listarParaMapa(float $north, float $south, float $east, float $west): Collection
    {
        return DB::table('pontos as p')
            ->leftJoin('endereco_atualizados as ea', 'ea.id', '=', 'p.endereco_atualizado_id')
            ->leftJoin(DB::raw('(SELECT ponto_id, MAX(id) as ultima_vistoria_id, COUNT(*) as total_vistorias FROM vistorias WHERE deleted_at IS NULL GROUP BY ponto_id) as uv'), 'uv.ponto_id', '=', 'p.id')
            ->leftJoin('vistorias as v', 'v.id', '=', 'uv.ultima_vistoria_id')
            ->select([
                'p.id',
                DB::raw('COALESCE(ea."NUMERO_IMOVEL", p.numero) as numero'),
                'p.complemento',
                'p.lat',
                'p.lng',
                DB::raw('ea."NOME_LOGRADOURO" as logradouro'),
                DB::raw('ea."NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('ea."NOME_REGIONAL" as regional'),
                DB::raw('ea."SIGLA_TIPO_LOGRADOURO" as tipo'),
                'v.resultado_acao_id',
                DB::raw('COALESCE(uv.total_vistorias, 0) as total_vistorias'),
                'uv.ultima_vistoria_id',
                DB::raw(Ponto::COMPLEXIDADE_SQL.' as complexidade'),
            ])
            ->whereNotNull('p.lat')
            ->whereNotNull('p.lng')
            ->whereRaw('p.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)', [$west, $south, $east, $north])
            ->limit(5000)
            ->get();
    }

    /**
     * Lista pontos com vistorias para a view index (paginado, com filtros).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function listarPontosComVistorias(array $filters, int $perPage): LengthAwarePaginator
    {
        $query = DB::table('pontos as p')
            ->leftJoin('endereco_atualizados as ea', 'ea.id', '=', 'p.endereco_atualizado_id')
            ->leftJoin(DB::raw('(SELECT ponto_id, MAX(id) as ultima_vistoria_id, COUNT(*) as total_vistorias FROM vistorias WHERE deleted_at IS NULL GROUP BY ponto_id) as uv'), 'uv.ponto_id', '=', 'p.id')
            ->leftJoin('vistorias as v', 'v.id', '=', 'uv.ultima_vistoria_id')
            ->leftJoin('resultados_acoes as ra', 'ra.id', '=', 'v.resultado_acao_id')
            ->select([
                'p.id',
                DB::raw('COALESCE(ea."NUMERO_IMOVEL", p.numero) as numero'),
                'p.complemento',
                'p.lat',
                'p.lng',
                DB::raw('ea."NOME_LOGRADOURO" as logradouro'),
                DB::raw('ea."NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('ea."NOME_REGIONAL" as regional'),
                DB::raw('ea."SIGLA_TIPO_LOGRADOURO" as tipo'),
                'v.resultado_acao_id',
                'ra.resultado as resultado_acao',
                DB::raw('COALESCE(uv.total_vistorias, 0) as total_vistorias'),
                DB::raw(Ponto::COMPLEXIDADE_SQL.' as complexidade'),
                'v.quantidade_pessoas',
            ])
            ->whereNotNull('p.lat')
            ->whereNotNull('p.lng')
            ->whereNotNull('p.endereco_atualizado_id');

        if (! empty($filters['logradouro'])) {
            $query->where('ea.NOME_LOGRADOURO', 'ilike', '%'.$filters['logradouro'].'%');
        }
        if (! empty($filters['regional'])) {
            $query->where('ea.NOME_REGIONAL', $filters['regional']);
        }
        if (! empty($filters['numero'])) {
            $query->where('ea.NUMERO_IMOVEL', $filters['numero']);
        }
        if (! empty($filters['bairro'])) {
            $query->where('ea.NOME_BAIRRO_POPULAR', 'ilike', '%'.$filters['bairro'].'%');
        }
        if (! empty($filters['resultado'])) {
            $query->where('v.resultado_acao_id', $filters['resultado']);
        }

        return $query->orderBy('logradouro')
            ->orderByRaw('NULLIF(regexp_replace(numero, \'[^0-9]\', \'\', \'g\'), \'\')::int NULLS LAST')
            ->paginate($perPage);
    }

    /**
     * Busca dados completos de um ponto por ID (para view show).
     */
    public function buscarPontoPorId(int $id): ?\stdClass
    {
        return DB::table('pontos as p')
            ->leftJoin('endereco_atualizados as ea', 'ea.id', '=', 'p.endereco_atualizado_id')
            ->leftJoin(DB::raw('(SELECT ponto_id, MAX(id) as ultima_vistoria_id, COUNT(*) as total_vistorias FROM vistorias WHERE deleted_at IS NULL GROUP BY ponto_id) as uv'), 'uv.ponto_id', '=', 'p.id')
            ->leftJoin('vistorias as v', 'v.id', '=', 'uv.ultima_vistoria_id')
            ->leftJoin('resultados_acoes as ra', 'ra.id', '=', 'v.resultado_acao_id')
            ->select([
                'p.id',
                DB::raw('COALESCE(ea."NUMERO_IMOVEL", p.numero) as numero'),
                'p.complemento',
                'p.lat',
                'p.lng',
                DB::raw('ea."NOME_LOGRADOURO" as logradouro'),
                DB::raw('ea."NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('ea."NOME_REGIONAL" as regional'),
                DB::raw('ea."SIGLA_TIPO_LOGRADOURO" as tipo'),
                'v.resultado_acao_id',
                'ra.resultado as resultado_acao',
                DB::raw('COALESCE(uv.total_vistorias, 0) as total_vistorias'),
            ])
            ->where('p.id', $id)
            ->first();
    }

    /**
     * Busca vistorias de um ponto ordenadas por data decrescente (paginado).
     *
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function buscarVistoriasDoPonto(int $pontoId, int $perPage = 50): LengthAwarePaginator
    {
        return DB::table('vistorias as v')
            ->leftJoin('tipo_abordagem as ta', 'ta.id', '=', 'v.tipo_abordagem_id')
            ->leftJoin('resultados_acoes as ra', 'ra.id', '=', 'v.resultado_acao_id')
            ->leftJoin('users as u', 'u.id', '=', 'v.user_id')
            ->select([
                'v.id',
                'v.data_abordagem',
                'v.quantidade_pessoas',
                'v.qtd_kg',
                'v.observacao',
                'v.nomes_pessoas',
                'ta.tipo as tipo_abordagem',
                'ra.resultado as resultado_acao',
                'u.name as usuario',
            ])
            ->where('v.ponto_id', $pontoId)
            ->whereNull('v.deleted_at')
            ->orderBy('v.data_abordagem', 'desc')
            ->paginate($perPage);
    }

    /**
     * Retorna dados de filtros (bairros, regionais, resultados) com cache.
     *
     * @return array<string, mixed>
     */
    public function getFilterData(): array
    {
        return [
            'bairros' => Cache::remember('filtro:bairros', 3600, fn () => DB::table('endereco_atualizados')
                ->select('NOME_BAIRRO_POPULAR as bairro')->distinct()
                ->whereNotNull('NOME_BAIRRO_POPULAR')->orderBy('NOME_BAIRRO_POPULAR')->pluck('bairro')),
            'regionais' => Cache::remember('filtro:regionais', 3600, fn () => DB::table('endereco_atualizados')
                ->select('NOME_REGIONAL as regional')->distinct()
                ->whereNotNull('NOME_REGIONAL')->orderBy('NOME_REGIONAL')->pluck('regional')),
            'resultados' => Cache::remember('filtro:resultados', 3600, fn () => DB::table('resultados_acoes')
                ->orderBy('id')->get()),
        ];
    }

    /**
     * Pesquisa pontos por texto (logradouro) para autocomplete.
     *
     * @return Collection<int, \stdClass>
     */
    public function buscarPontosPorTexto(string $termo): Collection
    {
        return DB::table('pontos as p')
            ->leftJoin('endereco_atualizados as ea', 'ea.id', '=', 'p.endereco_atualizado_id')
            ->where('ea.NOME_LOGRADOURO', 'ilike', '%'.$termo.'%')
            ->whereNotNull('p.lat')
            ->select([
                'p.id',
                DB::raw('ea."SIGLA_TIPO_LOGRADOURO" as tipo'),
                DB::raw('ea."NOME_LOGRADOURO" as logradouro'),
                DB::raw('COALESCE(ea."NUMERO_IMOVEL", p.numero) as numero'),
                DB::raw('ea."NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('ea."NOME_REGIONAL" as regional'),
            ])
            ->orderBy('ea.NOME_LOGRADOURO')
            ->orderByRaw('CAST(NULLIF(ea."NUMERO_IMOVEL", \'\') AS INTEGER) NULLS LAST')
            ->limit(20)
            ->get();
    }

    /**
     * Pesquisa endereços individuais por texto livre (logradouro + número opcional).
     *
     * @return Collection<int, \stdClass>
     */
    public function pesquisarEnderecos(string $termo, ?int $numero = null): Collection
    {
        $query = DB::table('endereco_atualizados')
            ->select([
                'id',
                DB::raw('"SIGLA_TIPO_LOGRADOURO" as tipo'),
                DB::raw('"NOME_LOGRADOURO" as logradouro'),
                DB::raw('"NUMERO_IMOVEL" as numero'),
                DB::raw('"NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('"NOME_REGIONAL" as regional'),
                'lat',
                'lng',
            ])
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->where('NOME_LOGRADOURO', 'ilike', "%{$termo}%");

        if ($numero) {
            $query->where('NUMERO_IMOVEL', (string) $numero);
        }

        return $query
            ->orderByRaw('CASE WHEN "NOME_LOGRADOURO" ILIKE ? THEN 0 ELSE 1 END', ["{$termo}%"])
            ->orderBy('NOME_LOGRADOURO')
            ->orderByRaw('NULLIF(regexp_replace("NUMERO_IMOVEL", \'[^0-9]\', \'\', \'g\'), \'\')::int NULLS LAST')
            ->limit(20)
            ->get();
    }

    /**
     * Busca logradouros distintos com números mais próximos (para autocomplete com número).
     *
     * @return array<int, \stdClass>
     */
    public function buscarLogradourosComNumero(string $termo, int $numero): array
    {
        return DB::select("
            WITH logradouros AS (
                SELECT DISTINCT \"SIGLA_TIPO_LOGRADOURO\" as tipo, \"NOME_LOGRADOURO\" as logradouro, \"NOME_REGIONAL\" as regional
                FROM endereco_atualizados
                WHERE \"NOME_LOGRADOURO\" ILIKE ?
                ORDER BY CASE WHEN \"NOME_LOGRADOURO\" ILIKE ? THEN 0 ELSE 1 END, logradouro, regional
                LIMIT 10
            ),
            numeros AS (
                SELECT l.tipo, l.logradouro, l.regional,
                       CAST(ea.\"NUMERO_IMOVEL\" AS INTEGER) as numero,
                       ROW_NUMBER() OVER (PARTITION BY l.logradouro, l.regional ORDER BY ABS(CAST(ea.\"NUMERO_IMOVEL\" AS INTEGER) - ?)) as rn
                FROM logradouros l
                JOIN endereco_atualizados ea ON ea.\"NOME_LOGRADOURO\" = l.logradouro AND ea.\"NOME_REGIONAL\" = l.regional
                WHERE ea.lat IS NOT NULL AND ea.lng IS NOT NULL AND ea.\"NUMERO_IMOVEL\" ~ '^[0-9]+$'
            )
            SELECT tipo, logradouro, regional, numero
            FROM numeros WHERE rn <= 3
            ORDER BY logradouro, regional, numero
        ", ['%'.$termo.'%', $termo.'%', $numero]);
    }

    /**
     * Busca logradouros distintos para autocomplete (sem número).
     *
     * @return Collection<int, \stdClass>
     */
    public function buscarLogradourosSemNumero(string $termo): Collection
    {
        $subquery = DB::table('endereco_atualizados')
            ->selectRaw('DISTINCT "SIGLA_TIPO_LOGRADOURO" as tipo, "NOME_LOGRADOURO" as logradouro, "NOME_REGIONAL" as regional')
            ->where('NOME_LOGRADOURO', 'ilike', '%'.$termo.'%');

        return DB::query()
            ->fromSub($subquery, 'sub')
            ->select(['tipo', 'logradouro', 'regional'])
            ->orderByRaw('CASE WHEN logradouro ILIKE ? THEN 0 ELSE 1 END', [$termo.'%'])
            ->orderBy('logradouro')
            ->orderBy('regional')
            ->limit(10)
            ->get();
    }

    /**
     * Busca endereço por logradouro e número (exato ou mais próximo).
     *
     * @return array<string, mixed>
     */
    public function buscarEnderecoPorLogradouro(string $logradouro, ?int $numeroBuscado, ?string $regional, bool $numeroInformado): array
    {
        // Query base
        $baseQuery = DB::table('endereco_atualizados')
            ->where('NOME_LOGRADOURO', $logradouro)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereRaw("\"NUMERO_IMOVEL\" ~ '^[0-9]+$'");

        if ($regional) {
            $baseQuery->where('NOME_REGIONAL', $regional);
        }

        // Se número não foi informado, calcular o ponto médio da numeração
        if ($numeroBuscado === null) {
            $faixa = (clone $baseQuery)
                ->selectRaw('MIN(CAST("NUMERO_IMOVEL" AS INTEGER)) as numero_min, MAX(CAST("NUMERO_IMOVEL" AS INTEGER)) as numero_max')
                ->first();

            if (! $faixa || $faixa->numero_min === null) {
                return [
                    'encontrado' => false,
                    'message' => 'Logradouro não encontrado',
                ];
            }

            // Calcula o número médio
            $numeroBuscado = (int) round(($faixa->numero_min + $faixa->numero_max) / 2);
        }

        // Busca na tabela endereco_atualizado
        $query = DB::table('endereco_atualizados')
            ->select([
                'id',
                DB::raw('"SIGLA_TIPO_LOGRADOURO" as tipo'),
                DB::raw('"NOME_LOGRADOURO" as logradouro'),
                DB::raw('"NUMERO_IMOVEL" as numero'),
                DB::raw('"NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('"NOME_REGIONAL" as regional'),
                'lat',
                'lng',
            ])
            ->where('NOME_LOGRADOURO', $logradouro)
            ->whereNotNull('lat')
            ->whereNotNull('lng');

        if ($regional) {
            $query->where('NOME_REGIONAL', $regional);
        }

        // Primeiro tenta número exato
        $exato = (clone $query)->where('NUMERO_IMOVEL', $numeroBuscado)->first();

        if ($exato) {
            return [
                'encontrado' => true,
                'exato' => true,
                'numero_informado' => $numeroInformado,
                'endereco' => $exato,
            ];
        }

        // Se não encontrou exato, busca o mais próximo
        $maisProximo = $query
            ->whereRaw("\"NUMERO_IMOVEL\" ~ '^[0-9]+$'")
            ->orderByRaw('ABS(CAST("NUMERO_IMOVEL" AS INTEGER) - ?)', [$numeroBuscado])
            ->first();

        if ($maisProximo) {
            return [
                'encontrado' => true,
                'exato' => false,
                'numero_informado' => $numeroInformado,
                'numero_buscado' => $numeroBuscado,
                'endereco' => $maisProximo,
            ];
        }

        return [
            'encontrado' => false,
            'message' => 'Endereço não encontrado',
        ];
    }

    /**
     * Busca dados de um ponto com endereço para atualização de coordenadas.
     */
    public function buscarPontoComEndereco(int $id): ?\stdClass
    {
        return DB::table('pontos as p')
            ->leftJoin('endereco_atualizados as ea', 'ea.id', '=', 'p.endereco_atualizado_id')
            ->select([
                'p.id',
                DB::raw('COALESCE(ea."NUMERO_IMOVEL", p.numero) as numero'),
                'p.complemento',
                DB::raw('ea."SIGLA_TIPO_LOGRADOURO" as tipo'),
                DB::raw('ea."NOME_LOGRADOURO" as logradouro'),
                DB::raw('ea."NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('ea."NOME_REGIONAL" as regional'),
            ])
            ->where('p.id', $id)
            ->first();
    }
}
