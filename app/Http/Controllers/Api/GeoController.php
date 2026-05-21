<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GeoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GeoController extends Controller
{
    private const TTL = 86400;

    public function __construct(private GeoService $geoService) {}

    public function bairros(Request $request): JsonResponse
    {
        $data = Cache::remember('geo:bairros', self::TTL, fn () => $this->geoService->loadBairros());

        return $this->geoJsonResponse($request, $data, 'geo:bairros');
    }

    public function regionais(Request $request): JsonResponse
    {
        $data = Cache::remember('geo:regionais', self::TTL, fn () => $this->geoService->loadRegionais());

        return $this->geoJsonResponse($request, $data, 'geo:regionais');
    }

    public function limiteMunicipio(Request $request): JsonResponse
    {
        $data = Cache::remember('geo:limite-municipio', self::TTL, fn () => $this->geoService->loadLimite());

        if (! $data) {
            return response()->json(['error' => 'Limite não encontrado'], 404);
        }

        return $this->geoJsonResponse($request, $data, 'geo:limite');
    }

    private function geoJsonResponse(Request $request, array $data, string $cacheKey): JsonResponse
    {
        $etag = '"'.md5($cacheKey.serialize($data)).'"';

        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304);
        }

        return response()->json(['type' => 'FeatureCollection', 'features' => $data])
            ->header('ETag', $etag)
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
