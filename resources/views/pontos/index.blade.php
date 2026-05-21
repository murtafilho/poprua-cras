@extends('layouts.app')

@section('title', 'Pontos')

@section('header')
    <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
        <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <span class="mobile-header-title" style="flex: 1; text-align: center;">{{ __('Pontos') }}</span>
    <div style="width: 44px;"></div>
@endsection

@section('content')
    <div class="page-content">
        {{-- Mensagem de sucesso do ajuste --}}
        @if(request('ajuste_sucesso'))
            <div class="alert alert-success mb-4">
                <div class="alert-content">
                    <p class="alert-message">Localização atualizada com sucesso{{ request('ponto_endereco') ? ': ' . request('ponto_endereco') : '' }}.</p>
                </div>
            </div>
        @endif

        {{-- Filtros --}}
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('pontos.index') }}" style="display: flex; flex-direction: column; gap: var(--space-3);">
                    {{-- Busca por endereco --}}
                    <div class="form-row form-row-2">
                        <div class="form-group" style="flex: 3;">
                            <label class="form-label">Logradouro</label>
                            <input type="text" name="logradouro" value="{{ request('logradouro') }}"
                                placeholder="Ex: AFONSO PENA"
                                class="form-input">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Numero</label>
                            <input type="text" name="numero" value="{{ request('numero') }}"
                                placeholder="Ex: 1000"
                                class="form-input">
                        </div>
                    </div>
                    <div class="form-row form-row-3">
                        <div class="form-group">
                            <label class="form-label">Regional</label>
                            <select name="regional" class="form-input form-select">
                                <option value="">Todas</option>
                                @foreach($regionais as $regional)
                                    <option value="{{ $regional }}" {{ request('regional') == $regional ? 'selected' : '' }}>
                                        {{ $regional }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Bairro</label>
                            <select name="bairro" class="form-input form-select">
                                <option value="">Todos</option>
                                @foreach($bairros as $bairro)
                                    <option value="{{ $bairro }}" {{ request('bairro') == $bairro ? 'selected' : '' }}>
                                        {{ $bairro }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Resultado</label>
                            <select name="resultado" class="form-input form-select">
                                <option value="">Todos</option>
                                @foreach($resultados as $resultado)
                                    <option value="{{ $resultado->id }}" {{ request('resultado') == $resultado->id ? 'selected' : '' }}>
                                        {{ $resultado->resultado }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            Buscar
                        </button>
                        <a href="{{ route('pontos.index') }}" class="btn btn-secondary">
                            Limpar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Tabela --}}
        <div class="table-container">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Endereço</th>
                        <th class="hide-mobile">Bairro</th>
                        <th class="hide-mobile">Regional</th>
                        <th class="hide-mobile text-center">Vistorias</th>
                        <th class="hide-mobile text-center">Pessoas</th>
                        <th class="hide-mobile text-center">Complexidade</th>
                        <th>Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($pontos as $ponto)
                        <tr class="clickable-row" data-href="{{ route('mapa.index', ['lat' => $ponto->lat, 'lng' => $ponto->lng, 'zoom' => 19, 'ponto_id' => $ponto->id, 'ajustar' => 1]) }}">
                            <td>
                                <a href="{{ route('mapa.index', ['lat' => $ponto->lat, 'lng' => $ponto->lng, 'zoom' => 19, 'ponto_id' => $ponto->id, 'ajustar' => 1]) }}"
                                   style="display: flex; align-items: center; gap: var(--space-2);">
                                    <svg style="width: 16px; height: 16px; color: var(--accent-primary); flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span>
                                        {{ $ponto->tipo }} {{ $ponto->logradouro }}, {{ $ponto->numero }}
                                        @if($ponto->complemento)
                                            <span class="text-muted">- {{ $ponto->complemento }}</span>
                                        @endif
                                    </span>
                                </a>
                                {{-- Mobile info --}}
                                <div class="mobile-only text-muted mt-1" style="font-size: var(--text-xs);">
                                    {{ $ponto->bairro }} - {{ $ponto->regional }}
                                    @if($ponto->total_vistorias > 0)
                                        <span class="badge badge-info" style="margin-left: var(--space-2);">
                                            {{ $ponto->total_vistorias }} vistoria(s)
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="hide-mobile">{{ $ponto->bairro }}</td>
                            <td class="hide-mobile">{{ $ponto->regional }}</td>
                            <td class="hide-mobile text-center">
                                @if($ponto->total_vistorias > 0)
                                    <a href="{{ route('pontos.show', $ponto->id) }}">
                                        <span class="badge badge-info">{{ $ponto->total_vistorias }}</span>
                                    </a>
                                @else
                                    <span class="badge badge-default">0</span>
                                @endif
                            </td>
                            <td class="hide-mobile text-center">
                                <span class="badge badge-accent">{{ $ponto->quantidade_pessoas ?? 0 }}</span>
                            </td>
                            <td class="hide-mobile text-center">
                                @php
                                    $complexidade = $ponto->complexidade ?? 0;
                                    $badgeClass = match(true) {
                                        $complexidade >= 8 => 'badge-danger',
                                        $complexidade >= 5 => 'badge-warning',
                                        $complexidade >= 3 => 'badge-info',
                                        $complexidade >= 1 => 'badge-success',
                                        default => 'badge-default',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ $complexidade }}</span>
                            </td>
                            <td>
                                @if($ponto->resultado_acao)
                                    @php
                                        $resultadoBadge = match($ponto->resultado_acao_id) {
                                            1 => 'badge-danger',
                                            2 => 'badge-warning',
                                            3, 4 => 'badge-default',
                                            5 => 'badge-info',
                                            6 => 'badge-success',
                                            default => 'badge-accent',
                                        };
                                    @endphp
                                    <span class="badge {{ $resultadoBadge }}">{{ $ponto->resultado_acao }}</span>
                                @else
                                    <span class="badge badge-accent">Sem vistoria</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted" style="padding: var(--space-6);">
                                Nenhum ponto encontrado.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginacao --}}
        <x-pagination-bar :paginator="$pontos->withQueryString()" label="pontos" />
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.clickable-row').forEach(function(row) {
                row.addEventListener('click', function(e) {
                    if (e.target.tagName === 'A' || e.target.closest('a')) {
                        return;
                    }
                    window.location.href = this.dataset.href;
                });
            });
        });
    </script>
@endsection
