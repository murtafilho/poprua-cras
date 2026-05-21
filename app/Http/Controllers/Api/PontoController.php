<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ponto;
use App\Services\EnderecoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PontoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'north' => 'required|numeric',
            'south' => 'required|numeric',
            'east' => 'required|numeric',
            'west' => 'required|numeric',
        ]);

        // Query com resultado da última vistoria e contagem de vistorias
        $pontos = DB::table('pontos as p')
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
            ->whereRaw('p.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)', [
                $validated['west'],
                $validated['south'],
                $validated['east'],
                $validated['north'],
            ])
            ->limit(5000)
            ->get();

        return response()->json($pontos);
    }

    public function buscarPontos(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:3|max:100']);

        $pontos = DB::table('pontos as p')
            ->leftJoin('endereco_atualizados as ea', 'ea.id', '=', 'p.endereco_atualizado_id')
            ->where('ea.NOME_LOGRADOURO', 'ilike', '%'.$request->input('q').'%')
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

        return response()->json($pontos);
    }

    public function show(int $id): JsonResponse
    {
        $ponto = \App\Models\Ponto::with(['enderecoAtualizado', 'ultimaVistoria', 'caracteristicaAbrigo'])
            ->withCount('vistorias as contador')
            ->withSum('vistorias', 'qtd_kg')
            ->find($id);

        if (! $ponto) {
            return response()->json(['error' => 'Ponto não encontrado'], 404);
        }

        /** @var \App\Models\EnderecoAtualizado|null $endereco */
        $endereco = $ponto->enderecoAtualizado;
        /** @var \App\Models\CaracteristicaAbrigo|null $caracteristica */
        $caracteristica = $ponto->caracteristicaAbrigo;
        /** @var \App\Models\Vistoria|null $ultimaVistoria */
        $ultimaVistoria = $ponto->ultimaVistoria;

        return response()->json([
            'id' => $ponto->id,
            'numero' => $endereco->NUMERO_IMOVEL ?? $ponto->numero,
            'complemento' => $ponto->complemento,
            'logradouro' => $endereco?->NOME_LOGRADOURO,
            'bairro' => $endereco?->NOME_BAIRRO_POPULAR,
            'regional' => $endereco?->NOME_REGIONAL,
            'tipo' => $endereco?->SIGLA_TIPO_LOGRADOURO,
            'lat' => $ponto->lat,
            'lng' => $ponto->lng,
            'caracteristica_abrigo' => $caracteristica?->nome,
            'contador' => $ponto->contador ?? 0,
            'soma_kg' => $ponto->vistorias_sum_qtd_kg ?? 0,
            'complexidade' => $ponto->complexidade,
            'resultado' => $ultimaVistoria?->resultadoAcao?->nome,
            'resultado_acao_id' => $ultimaVistoria?->resultado_acao_id,
        ]);
    }

    /**
     * Pesquisa endereços individuais por texto livre (logradouro + numero opcional).
     * Retorna id, tipo, logradouro, numero, bairro, regional, lat, lng.
     */
    public function pesquisarEndereco(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:3|max:100',
        ]);

        $termo = $validated['q'];

        // Separar numero do texto: "AFONSO PENA 1234" ou "AFONSO PENA, 1234"
        $numero = null;
        if (preg_match('/^(.+?)[,\s]+(\d+)\s*$/', $termo, $matches)) {
            $termo = trim($matches[1]);
            $numero = (int) $matches[2];
        }

        // Remover tipo de logradouro
        $termo = preg_replace('/^(RUA|AVE|AVENIDA|AV|PCA|PRACA|TV|TRV|TRAVESSA|AL|ALAMEDA|VIA|ROD|RODOVIA)\s+/i', '', $termo);

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

        $resultados = $query
            ->orderByRaw('CASE WHEN "NOME_LOGRADOURO" ILIKE ? THEN 0 ELSE 1 END', ["{$termo}%"])
            ->orderBy('NOME_LOGRADOURO')
            ->orderByRaw('NULLIF(regexp_replace("NUMERO_IMOVEL", \'[^0-9]\', \'\', \'g\'), \'\')::int NULLS LAST')
            ->limit(20)
            ->get();

        return response()->json($resultados);
    }

    /**
     * Busca logradouros distintos para autocomplete.
     * Retorna: TIPO LOGRADOURO - REGIONAL
     */
    public function buscarLogradouros(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:100',
            'numero' => 'nullable|integer|min:1',
        ]);

        $termo = $validated['q'];
        $numero = $validated['numero'] ?? null;

        if ($numero !== null) {
            $resultados = DB::select("
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

            return response()->json($resultados);
        }

        $subquery = DB::table('endereco_atualizados')
            ->selectRaw('DISTINCT "SIGLA_TIPO_LOGRADOURO" as tipo, "NOME_LOGRADOURO" as logradouro, "NOME_REGIONAL" as regional')
            ->where('NOME_LOGRADOURO', 'ilike', '%'.$termo.'%');

        $logradouros = DB::query()
            ->fromSub($subquery, 'sub')
            ->select(['tipo', 'logradouro', 'regional'])
            ->orderByRaw('CASE WHEN logradouro ILIKE ? THEN 0 ELSE 1 END', [$termo.'%'])
            ->orderBy('logradouro')
            ->orderBy('regional')
            ->limit(10)
            ->get();

        return response()->json($logradouros);
    }

    /**
     * Busca endereço por logradouro e número.
     * Se não encontrar número exato, retorna o mais próximo.
     * Se não informar número, centraliza no meio da numeração do logradouro.
     */
    public function buscarEndereco(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'logradouro' => 'required|string|min:2|max:100',
            'numero' => 'nullable|integer|min:1',
            'regional' => 'nullable|string|max:50',
        ]);

        $logradouro = $validated['logradouro'];
        $numeroInformado = array_key_exists('numero', $validated) && $validated['numero'] !== null;
        $numeroBuscado = $validated['numero'] ?? null;
        $regional = $validated['regional'] ?? null;

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
                return response()->json([
                    'encontrado' => false,
                    'message' => 'Logradouro não encontrado',
                ]);
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
            return response()->json([
                'encontrado' => true,
                'exato' => true,
                'numero_informado' => $numeroInformado,
                'endereco' => $exato,
            ]);
        }

        // Se não encontrou exato, busca o mais próximo
        $maisProximo = $query
            ->whereRaw("\"NUMERO_IMOVEL\" ~ '^[0-9]+$'")
            ->orderByRaw('ABS(CAST("NUMERO_IMOVEL" AS INTEGER) - ?)', [$numeroBuscado])
            ->first();

        if ($maisProximo) {
            return response()->json([
                'encontrado' => true,
                'exato' => false,
                'numero_informado' => $numeroInformado,
                'numero_buscado' => $numeroBuscado,
                'endereco' => $maisProximo,
            ]);
        }

        return response()->json([
            'encontrado' => false,
            'message' => 'Endereço não encontrado',
        ]);
    }

    /**
     * Busca o endereço de porta mais próximo de uma coordenada.
     */
    public function buscarEnderecoPorCoordenadas(Request $request, EnderecoService $enderecoService): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $endereco = $enderecoService->buscarEnderecoMaisProximo(
            (float) $validated['lat'],
            (float) $validated['lng']
        );

        if (! $endereco) {
            return response()->json([
                'encontrado' => false,
                'message' => 'Nenhum endereço encontrado nas proximidades',
            ]);
        }

        return response()->json([
            'encontrado' => true,
            'endereco' => $endereco,
            'distancia_metros' => round($endereco->distancia),
        ]);
    }

    /**
     * Atualiza as coordenadas de um ponto (geocodificação manual).
     */
    public function updateCoordenadas(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        // Buscar ponto com endereço
        $ponto = DB::table('pontos as p')
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

        if (! $ponto) {
            return response()->json(['error' => 'Ponto não encontrado'], 404);
        }

        $pontoModel = Ponto::findOrFail($id);
        $pontoModel->update([
            'lat' => $validated['lat'],
            'lng' => $validated['lng'],
        ]);

        $enderecoService = app(EnderecoService::class);
        $enderecoService->vincularEnderecoAoPonto(
            $pontoModel->id,
            (float) $validated['lat'],
            (float) $validated['lng']
        );

        $pontoModel->refresh();
        $pontoModel->load('enderecoAtualizado');

        $enderecoPonto = $pontoModel->endereco_completo;
        $bairro = $pontoModel->enderecoAtualizado?->NOME_BAIRRO_POPULAR;

        return response()->json([
            'success' => true,
            'message' => 'Coordenadas atualizadas com sucesso',
            'ponto_id' => $id,
            'lat' => $validated['lat'],
            'lng' => $validated['lng'],
            'endereco_ponto' => $enderecoPonto,
            'bairro' => $bairro,
        ]);
    }
}
