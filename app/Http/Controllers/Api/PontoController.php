<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaracteristicaAbrigo;
use App\Models\EnderecoAtualizado;
use App\Models\Ponto;
use App\Models\Vistoria;
use App\Services\EnderecoService;
use App\Services\PontoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PontoController extends Controller
{
    public function __construct(private PontoService $pontoService) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'north' => 'required|numeric',
            'south' => 'required|numeric',
            'east' => 'required|numeric',
            'west' => 'required|numeric',
        ]);

        $pontos = $this->pontoService->listarParaMapa(
            (float) $validated['north'],
            (float) $validated['south'],
            (float) $validated['east'],
            (float) $validated['west'],
        );

        return response()->json($pontos);
    }

    public function buscarPontos(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:3|max:100']);

        $pontos = $this->pontoService->buscarPontosPorTexto($request->input('q'));

        return response()->json($pontos);
    }

    public function show(int $id): JsonResponse
    {
        $ponto = Ponto::with(['enderecoAtualizado', 'ultimaVistoria', 'caracteristicaAbrigo'])
            ->withCount('vistorias as contador')
            ->withSum('vistorias', 'qtd_kg')
            ->find($id);

        if (! $ponto) {
            return response()->json(['error' => 'Ponto não encontrado'], 404);
        }

        /** @var EnderecoAtualizado|null $endereco */
        $endereco = $ponto->enderecoAtualizado;
        /** @var CaracteristicaAbrigo|null $caracteristica */
        $caracteristica = $ponto->caracteristicaAbrigo;
        /** @var Vistoria|null $ultimaVistoria */
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

        $resultados = $this->pontoService->pesquisarEnderecos($termo, $numero);

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
            $resultados = $this->pontoService->buscarLogradourosComNumero($termo, $numero);

            return response()->json($resultados);
        }

        $logradouros = $this->pontoService->buscarLogradourosSemNumero($termo);

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

        $resultado = $this->pontoService->buscarEnderecoPorLogradouro(
            $logradouro,
            $numeroBuscado,
            $regional,
            $numeroInformado
        );

        return response()->json($resultado);
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

        $ponto = $this->pontoService->buscarPontoComEndereco($id);

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
