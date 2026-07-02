@extends('layouts.app')

@section('title', 'Mapa')

@push('styles')
<style>
    .bairro-label,
    .regional-label {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
    }
    .bairro-label span,
    .regional-label span {
        display: inline-block;
        transform: translate(-50%, -50%);
        pointer-events: none;
    }
    .map-search-field-icon {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        pointer-events: none;
        z-index: 2;
    }

</style>
@endpush

@section('header')
    <div style="flex: 1; display: flex; justify-content: center;">
        <div class="map-search-bar" style="width: 100%; max-width: 480px;">
            <div class="map-search-field">
                <svg class="map-search-field-icon" style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input
                    type="text"
                    id="search-endereco"
                    placeholder="Buscar endereco..."
                    autocomplete="off"
                    class="form-input form-input-sm"
                    style="padding-left: 32px;"
                >
                <div id="search-results" class="map-search-results hidden"></div>
            </div>
        </div>
    </div>
    <button id="btn-menu" class="btn btn-ghost btn-icon" aria-label="Abrir painel de camadas" aria-expanded="false" aria-controls="layers-panel">
        <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
    </button>
@endsection

@section('content')
    <!-- Map Container -->
    <div id="map" role="application" aria-label="Mapa interativo de pontos de vistoria">
        <div class="map-crosshair" aria-hidden="true">
            <div class="map-crosshair-h"></div>
            <div class="map-crosshair-v"></div>
        </div>
    </div>

    <button id="btn-nova-acao" class="map-btn-nova-acao" aria-label="Registrar nova vistoria neste local">
        <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nova Ação
    </button>

    <button id="btn-my-location" class="map-fab" title="Minha localizacao" aria-label="Centralizar na minha localizacao">
        <svg id="location-icon" style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="3" stroke-width="2"/>
            <circle cx="12" cy="12" r="7" stroke-width="2"/>
            <path stroke-linecap="round" stroke-width="2" d="M12 2v3M12 19v3M2 12h3M19 12h3"/>
        </svg>
        <svg id="location-loader" style="width: 24px; height: 24px;" class="hidden spinner" fill="none" viewBox="0 0 24 24">
            <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
    </button>


    <!-- Layers Panel (abre pelo menu) -->
    <div id="layers-panel" class="map-layers-panel hidden">
        <div class="layers-panel-header">
            <h4 class="layers-panel-title">Camadas</h4>
            <button type="button" id="layers-panel-close" class="btn btn-ghost btn-icon" style="margin: -8px -8px -8px 0;">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <h4 class="layers-panel-title layers-panel-divider">Mapa Base</h4>
        <div class="layers-panel-group">
            <label class="layers-panel-option">
                <input type="radio" name="base-layer" id="base-street" class="form-checkbox">
                <span>Ruas</span>
            </label>
            <label class="layers-panel-option">
                <input type="radio" name="base-layer" id="base-satellite" class="form-checkbox" checked>
                <span>Satelite</span>
            </label>
        </div>

        <h4 class="layers-panel-title layers-panel-divider">Camadas</h4>
        <div class="layers-panel-group">
            <label class="layers-panel-option">
                <input type="checkbox" id="layer-regionais" class="form-checkbox">
                <span>Regionais</span>
            </label>
            <label class="layers-panel-option">
                <input type="checkbox" id="layer-bairros" class="form-checkbox">
                <span>Bairros</span>
            </label>
            <label class="layers-panel-option">
                <input type="checkbox" id="layer-limite" class="form-checkbox" checked>
                <span>Limite Municipal</span>
            </label>
            <label class="layers-panel-option">
                <input type="checkbox" id="layer-pontos" class="form-checkbox" checked>
                <span>Pontos</span>
            </label>
        </div>

        <h4 class="layers-panel-title layers-panel-divider">Filtrar por Resultado</h4>
        <div class="layers-panel-filters">
            <label class="layers-panel-filter">
                <input type="checkbox" data-resultado="1" class="filter-resultado form-checkbox" checked>
                <span class="filter-color" style="background-color: #dc2626;"></span>
                <span>Fenomeno persiste</span>
            </label>
            <label class="layers-panel-filter">
                <input type="checkbox" data-resultado="2" class="filter-resultado form-checkbox" checked>
                <span class="filter-color" style="background-color: #f97316;"></span>
                <span>Impactado parcialmente</span>
            </label>
            <label class="layers-panel-filter">
                <input type="checkbox" data-resultado="3" class="filter-resultado form-checkbox">
                <span class="filter-color" style="background-color: #1f2937;"></span>
                <span>Deixou de Ocorrer</span>
            </label>
            <label class="layers-panel-filter">
                <input type="checkbox" data-resultado="4" class="filter-resultado form-checkbox" checked>
                <span class="filter-color" style="background-color: #6b7280;"></span>
                <span>PSR ausente</span>
            </label>
            <label class="layers-panel-filter">
                <input type="checkbox" data-resultado="5" class="filter-resultado form-checkbox">
                <span class="filter-color" style="background-color: #3b82f6;"></span>
                <span>Nao constatado</span>
            </label>
            <label class="layers-panel-filter">
                <input type="checkbox" data-resultado="6" class="filter-resultado form-checkbox" checked>
                <span class="filter-color" style="background-color: #10b981;"></span>
                <span>Em Conformidade</span>
            </label>
            <label class="layers-panel-filter">
                <input type="checkbox" data-resultado="null" class="filter-resultado form-checkbox" checked>
                <span class="filter-color" style="background-color: #a855f7;"></span>
                <span>Sem vistoria</span>
            </label>
        </div>
    </div>

    <!-- Modal Relatorio -->
    <div id="relatorio-modal" class="relatorio-modal hidden">
        <div class="relatorio-modal-overlay" x-on:click="fecharRelatorio()"></div>
        <div class="relatorio-modal-content">
            <div class="relatorio-modal-header">
                <span class="relatorio-modal-title">Relatorio da Vistoria</span>
                <button type="button" class="relatorio-modal-close" x-on:click="fecharRelatorio()">
                    <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="relatorio-modal-body">
                <div id="relatorio-loader" class="relatorio-loader">
                    <div class="loading-spinner"></div>
                </div>
                <iframe id="relatorio-iframe" src="" frameborder="0"></iframe>
            </div>
        </div>
    </div>

@endsection


@push('scripts')
<script>window.APP_BASE = "{{ rtrim(url('/'), '/') }}";</script>
<script>window.MAPA_CONFIG = @json($mapaConfig);</script>
@vite('resources/js/mapa.js')
@endpush
