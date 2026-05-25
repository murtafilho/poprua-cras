<?php

use App\Http\Controllers\Api\ClientLogController;
use App\Http\Controllers\Api\GeocodingController;
use App\Http\Controllers\Api\GeoController;
use App\Http\Controllers\Api\MoradorController;
use App\Http\Controllers\Api\MoradorFotoController;
use App\Http\Controllers\Api\PontoController;
use App\Http\Controllers\Api\VistoriaFotoController;
use App\Http\Controllers\VistoriaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['web', 'auth', 'throttle:60,1'])->group(function () {
    Route::get('/pontos', [PontoController::class, 'index']);
    Route::get('/pontos/{id}', [PontoController::class, 'show']);
    Route::patch('/pontos/{id}/coordenadas', [PontoController::class, 'updateCoordenadas']);
    Route::get('/pontos/busca', [PontoController::class, 'buscarPontos']);
    Route::get('/pontos/{ponto}/moradores', [MoradorController::class, 'porPonto']);

    Route::get('/enderecos/logradouros', [PontoController::class, 'buscarLogradouros']);
    Route::get('/enderecos/buscar', [PontoController::class, 'buscarEndereco']);
    Route::get('/enderecos/pesquisar', [PontoController::class, 'pesquisarEndereco']);
    Route::get('/enderecos/por-coordenadas', [PontoController::class, 'buscarEnderecoPorCoordenadas']);

    Route::post('/geocode', [GeocodingController::class, 'geocode']);
});

Route::prefix('geo')->group(function () {
    Route::get('/bairros', [GeoController::class, 'bairros']);
    Route::get('/regionais', [GeoController::class, 'regionais']);
    Route::get('/limite-municipio', [GeoController::class, 'limiteMunicipio']);
});

// Vistorias - autocomplete de logradouros
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/vistorias/logradouros', [VistoriaController::class, 'buscarLogradouros']);
});

// Fotos de Vistorias (upload offline-first via Service Worker)
Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/vistorias/fotos', [VistoriaFotoController::class, 'store']);
    Route::get('/vistorias/{vistoria}/fotos/status', [VistoriaFotoController::class, 'status']);
    Route::post('/vistorias/{vistoria}/fotos/{mediaId}/toggle-publica', [VistoriaFotoController::class, 'togglePublica']);
});

// Client logs (debug mobile)
Route::middleware(['web', 'auth'])->post('/client-logs', [ClientLogController::class, 'store']);

// Moradores (requer autenticação — dados PII)
Route::middleware(['web', 'auth'])->prefix('moradores')->group(function () {
    Route::get('/', [MoradorController::class, 'index']);
    Route::get('/buscar', [MoradorController::class, 'buscar']);
    Route::get('/arquivados', [MoradorController::class, 'arquivados']);
    Route::post('/', [MoradorController::class, 'store']);
    Route::get('/{morador}', [MoradorController::class, 'show']);
    Route::put('/{morador}', [MoradorController::class, 'update']);
    Route::delete('/{morador}', [MoradorController::class, 'destroy']);
    Route::post('/{id}/restaurar', [MoradorController::class, 'restore']);
    Route::get('/{morador}/historico', [MoradorController::class, 'historico']);
    Route::post('/{morador}/entrada', [MoradorController::class, 'entrada']);
    Route::post('/{morador}/saida', [MoradorController::class, 'saida']);
    Route::post('/{morador}/transferir', [MoradorController::class, 'transferir']);
    Route::get('/{morador}/fotos', [MoradorFotoController::class, 'index']);
    Route::post('/{morador}/fotos', [MoradorFotoController::class, 'store']);
    Route::delete('/{morador}/fotos/{media}', [MoradorFotoController::class, 'destroy']);

    // Compat: singular antigo (aceita 1 foto; DELETE limpa toda a coleção)
    Route::post('/{morador}/foto', [MoradorFotoController::class, 'store']);
    Route::delete('/{morador}/foto', [MoradorFotoController::class, 'destroy']);
});
