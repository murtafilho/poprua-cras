<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EnderecoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodingController extends Controller
{
    public function __construct(
        private EnderecoService $enderecoService
    ) {}

    /**
     * Geocodifica um endereço.
     * Primeiro busca na tabela endereco_atualizados, depois usa Nominatim (OpenStreetMap) como fallback.
     */
    public function geocode(Request $request): JsonResponse
    {
        $request->validate([
            'logradouro' => 'required|string',
            'numero' => 'nullable|string',
            'bairro' => 'nullable|string',
            'cidade' => 'nullable|string',
        ]);

        $logradouro = $request->input('logradouro');
        $numero = $request->input('numero');
        $bairro = $request->input('bairro', '');

        // 1. Tenta buscar na tabela endereco_atualizado primeiro
        $endereco = $this->enderecoService->geocodificarEndereco($logradouro, $numero, $bairro);

        if ($endereco && $endereco->lat && $endereco->lng) {
            $enderecoEncontrado = $endereco->tipo.' '.$endereco->logradouro.', '.intval($endereco->numero);
            if ($endereco->bairro) {
                $enderecoEncontrado .= ' - '.$endereco->bairro;
            }

            return response()->json([
                'success' => true,
                'lat' => (float) $endereco->lat,
                'lng' => (float) $endereco->lng,
                'source' => 'endereco_atualizado',
                'display_name' => $enderecoEncontrado,
                'address' => [
                    'tipo' => $endereco->tipo,
                    'logradouro' => $endereco->logradouro,
                    'numero' => intval($endereco->numero),
                    'bairro' => $endereco->bairro,
                    'regional' => $endereco->regional,
                ],
            ]);
        }

        // 2. Fallback para Nominatim (OpenStreetMap)
        return $this->geocodeViaNominatim($logradouro, $numero, $bairro, $request->input('cidade', 'Belo Horizonte'));
    }

    /**
     * Geocodifica usando Nominatim (OpenStreetMap)
     */
    private function geocodeViaNominatim(string $logradouro, ?string $numero, ?string $bairro, string $cidade): JsonResponse
    {
        $endereco = trim($logradouro);
        if ($numero) {
            $endereco .= ', '.$numero;
        }
        if ($bairro) {
            $endereco .= ', '.$bairro;
        }
        $endereco .= ', '.$cidade.', MG, Brasil';

        $cacheKey = 'nominatim:'.md5($endereco);
        $cached = Cache::get($cacheKey);

        if ($cached) {
            return response()->json($cached);
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'POPRUA-v2/1.0 (murtafilho@gmail.com)'])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $endereco,
                    'format' => 'json',
                    'limit' => 1,
                    'addressdetails' => 1,
                    'countrycodes' => 'br',
                    'accept-language' => 'pt-BR',
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (! empty($data) && isset($data[0]['lat']) && isset($data[0]['lon'])) {
                    $result = [
                        'success' => true,
                        'lat' => (float) $data[0]['lat'],
                        'lng' => (float) $data[0]['lon'],
                        'source' => 'nominatim',
                        'display_name' => $data[0]['display_name'] ?? $endereco,
                        'address' => $data[0]['address'] ?? null,
                    ];

                    Cache::put($cacheKey, $result, 604800);

                    return response()->json($result);
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Endereço não encontrado na base local nem no OpenStreetMap.',
            ], 404);

        } catch (\Exception $e) {
            Log::error('Erro na geocodificação via Nominatim', [
                'endereco' => $endereco,
                'erro' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar coordenadas. Tente novamente.',
            ], 500);
        }
    }
}
