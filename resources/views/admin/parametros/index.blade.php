@extends('layouts.app')

@section('title', 'Parametrização')

@push('styles')
<style>
    .param-tabs {
        display: flex;
        gap: 0;
        background: var(--pbh-azul);
        border-radius: var(--card-radius) var(--card-radius) 0 0;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .param-tabs::-webkit-scrollbar { display: none; }
    .param-tab {
        padding: 12px 18px;
        font-size: var(--text-sm);
        font-weight: var(--font-semibold);
        color: rgba(255,255,255,0.6);
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.15s;
        font-family: var(--font-body);
    }
    .param-tab:hover { color: #FFFFFF; background: rgba(255,255,255,0.06); }
    .param-tab.active {
        color: var(--pbh-amarelo);
        border-bottom-color: var(--pbh-amarelo);
        background: rgba(255,255,255,0.08);
    }
    .param-tab .tab-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 18px;
        height: 18px;
        font-size: 10px;
        background: rgba(255,255,255,0.15);
        color: rgba(255,255,255,0.7);
        border-radius: 9px;
        margin-left: 4px;
        padding: 0 5px;
    }
    .param-tab.active .tab-count {
        background: rgba(255,215,0,0.2);
        color: var(--pbh-amarelo);
    }
    .param-panel { display: none; }
    .param-panel.active { display: block; }
    .param-grupo-desc {
        padding: 12px 16px;
        background: var(--bg-tertiary);
        border-bottom: 1px solid var(--border-primary);
        font-size: var(--text-xs);
        color: var(--text-secondary);
        line-height: 1.5;
    }
</style>
@endpush

@section('header')
    <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
        <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <span class="mobile-header-title" style="flex: 1; text-align: center;">Parametrização</span>
    <div style="width: 44px;"></div>
@endsection

@section('content')
    <div class="page-content">
        @if(session('success'))
            <div class="alert alert-success mb-4">
                <div class="alert-content">
                    <p class="alert-message">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @php
            $gruposInfo = $gruposInfo ?? config('parametros.grupos', []);
            $contextos = $contextos ?? config('parametros.contextos', []);
        @endphp

        <form method="POST" action="{{ route('admin.parametros.update') }}">
            @csrf
            @method('PUT')

            {{-- Barra fixa: abas + salvar --}}
            <div style="position: sticky; top: var(--header-height); z-index: 10; background: var(--bg-base);">
                <div class="param-tabs" id="param-tabs">
                    @foreach($parametros as $grupo => $params)
                        <button type="button" class="param-tab {{ $loop->first ? 'active' : '' }}" data-target="panel-{{ $grupo }}">
                            {{ $gruposInfo[$grupo]['label'] ?? ucfirst($grupo) }}
                            <span class="tab-count">{{ $params->count() }}</span>
                        </button>
                    @endforeach
                    <div style="margin-left: auto; padding: 6px 8px;">
                        <button type="submit" class="btn btn-primary btn-sm" style="min-height: 32px;">
                            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Salvar
                        </button>
                    </div>
                </div>
            </div>

            {{-- Painéis --}}
            <div class="card mb-4">

                @foreach($parametros as $grupo => $params)
                    <div class="param-panel {{ $loop->first ? 'active' : '' }}" id="panel-{{ $grupo }}">
                        <div class="param-grupo-desc">
                            {{ $gruposInfo[$grupo]['desc'] ?? '' }}
                        </div>
                        {{-- Header de colunas --}}
                        <div style="display: flex; align-items: center; gap: var(--space-3); padding: 8px 16px; margin-top: var(--space-4); border-bottom: 1px solid var(--border-primary); background: var(--bg-tertiary);">
                            <span style="flex: 2; min-width: 150px; font-size: 10px; font-weight: var(--font-bold); color: var(--text-primary); text-transform: uppercase; letter-spacing: 0.06em;">Parâmetro</span>
                            <span class="hide-mobile" style="flex: 3; font-size: 10px; font-weight: var(--font-bold); color: var(--text-primary); text-transform: uppercase; letter-spacing: 0.06em;">Contexto de aplicação</span>
                            <span style="flex: 0 0 320px; font-size: 10px; font-weight: var(--font-bold); color: var(--text-primary); text-transform: uppercase; letter-spacing: 0.06em;">Valor</span>
                            <span style="width: 36px;"></span>
                        </div>
                        @foreach($params as $param)
                            <div class="vistoria-card-item {{ $loop->even ? 'even' : '' }}" style="border-left: none;">
                                <div style="display: flex; align-items: flex-start; gap: var(--space-3); width: 100%; flex-wrap: wrap;">
                                    <div style="flex: 2; min-width: 150px;">
                                        <label for="param-{{ $param->chave }}" style="font-weight: var(--font-semibold); font-size: var(--text-sm); display: block;">
                                            {{ $param->descricao ?: ucfirst(str_replace('_', ' ', $param->chave)) }}
                                        </label>
                                        <span class="text-muted" style="font-size: 10px; font-family: var(--font-mono);">{{ $param->chave }}</span>
                                    </div>
                                    <div class="hide-mobile" style="flex: 3;">
                                        <span style="font-size: var(--text-xs); color: var(--text-secondary); line-height: 1.4;">{{ $contextos[$param->chave] ?? '' }}</span>
                                    </div>
                                    <div style="flex: 0 0 320px; display: flex; align-items: center; gap: var(--space-2);">
                                        @if($param->tipo === 'boolean')
                                            <select name="parametros[{{ $param->chave }}]" id="param-{{ $param->chave }}" class="form-input form-select" style="max-width: 140px;">
                                                <option value="1" {{ $param->valor ? 'selected' : '' }}>Sim</option>
                                                <option value="0" {{ !$param->valor ? 'selected' : '' }}>Não</option>
                                            </select>
                                        @else
                                            <input type="{{ $param->tipo === 'integer' ? 'number' : 'text' }}"
                                                   name="parametros[{{ $param->chave }}]"
                                                   id="param-{{ $param->chave }}"
                                                   value="{{ $param->valor }}"
                                                   class="form-input"
                                                   {{ $param->tipo === 'integer' ? 'step=1' : '' }}
                                                   {{ $param->tipo === 'float' ? 'step=0.000001' : '' }}>
                                        @endif
                                        <span class="badge badge-default" style="font-size: 9px;">{{ $param->tipo }}</span>
                                    </div>
                                    <button type="button" class="btn btn-ghost btn-sm" title="Remover" style="color: var(--color-danger);"
                                            data-delete-url="{{ route('admin.parametros.destroy', $param->chave) }}"
                                            data-delete-chave="{{ $param->chave }}">
                                        <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

        </form>

        {{-- Form de delete isolado (fora do form PUT) --}}
        <form id="form-delete-param" method="POST" style="display: none;">
            @csrf
            @method('DELETE')
        </form>

        {{-- Adicionar novo --}}
        <details class="card mb-4">
            <summary class="card-header" style="cursor: pointer; display: flex; align-items: center; justify-content: space-between; user-select: none; list-style: none;">
                <span class="card-title">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Novo Parâmetro
                </span>
            </summary>
            <div class="card-body">
                <form method="POST" action="{{ route('admin.parametros.store') }}" style="display: flex; flex-direction: column; gap: var(--space-3);">
                    @csrf
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label class="form-label required">Chave</label>
                            <input type="text" name="chave" required placeholder="ex: minha_config" class="form-input" pattern="[a-z0-9_]+" title="Apenas letras minúsculas, números e underscores">
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Grupo</label>
                            <input type="text" name="grupo" required placeholder="ex: workflow" class="form-input" list="grupos-existentes">
                            <datalist id="grupos-existentes">
                                @foreach($parametros->keys() as $g)
                                    <option value="{{ $g }}">
                                @endforeach
                            </datalist>
                        </div>
                    </div>
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label class="form-label">Valor</label>
                            <input type="text" name="valor" placeholder="Valor inicial" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label required">Tipo</label>
                            <select name="tipo" required class="form-input form-select">
                                <option value="string">Texto</option>
                                <option value="integer">Inteiro</option>
                                <option value="float">Decimal</option>
                                <option value="boolean">Sim/Não</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descrição</label>
                        <input type="text" name="descricao" placeholder="Descrição do parâmetro" class="form-input">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Adicionar
                    </button>
                </form>
            </div>
        </details>
    </div>
@endsection

@push('scripts')
@vite('resources/js/admin-parametros.js')
@endpush
