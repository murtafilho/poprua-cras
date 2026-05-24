@extends('layouts.app')

@section('title', 'Minhas Vistorias')

@section('header')
    <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
        <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <span class="mobile-header-title" style="flex: 1; text-align: center;">Minhas Vistorias</span>
    <a href="{{ route('mapa.index', ['nova_vistoria' => 1]) }}" class="btn btn-ghost btn-icon" title="Nova vistoria">
        <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
    </a>
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

        {{-- Filtros --}}
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('vistorias.minhas') }}" style="display: flex; flex-direction: column; gap: var(--space-3);">
                    <div class="form-row form-row-3">
                        <div class="form-group">
                            <label class="form-label">Data Inicio</label>
                            <input type="date" name="data_inicio" value="{{ request('data_inicio') }}" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" value="{{ request('data_fim') }}" class="form-input">
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
                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            Filtrar
                        </button>
                        <a href="{{ route('vistorias.minhas') }}" class="btn btn-secondary">Limpar</a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Tabela --}}
        <div class="table-container">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Endereco</th>
                        <th class="hide-mobile">Descricao</th>
                        <th class="hide-mobile">Bairro</th>
                        <th class="hide-mobile">Regional</th>
                        <th class="hide-mobile text-center">Pessoas</th>
                        <th class="hide-mobile text-center">Kg</th>
                        <th class="text-center">Status</th>
                        <th>Resultado</th>
                        <th class="text-center">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vistorias as $vistoria)
                        @php
                            $dataAbordagem = \Carbon\Carbon::parse($vistoria->data_abordagem);
                            $horaAbordagem = $dataAbordagem->format('H:i');
                        @endphp
                        <tr>
                            <td>
                                <div style="white-space: nowrap;">
                                    <div style="font-weight: var(--font-medium);">{{ $dataAbordagem->format('d/m/Y') }}</div>
                                    @if ($horaAbordagem !== '00:00')
                                        <div class="text-muted" style="font-size: var(--text-xs);">{{ $horaAbordagem }}</div>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($vistoria->lat && $vistoria->lng)
                                    <a href="{{ route('mapa.index', ['lat' => $vistoria->lat, 'lng' => $vistoria->lng, 'zoom' => 19, 'ponto_id' => $vistoria->ponto_id, 'ajustar' => 1]) }}"
                                       style="display: flex; align-items: center; gap: var(--space-2); min-height: 44px; padding: var(--space-1) 0;">
                                        <svg style="width: 16px; height: 16px; color: var(--accent-primary); flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        </svg>
                                        <span>
                                            @if($vistoria->tipo){{ $vistoria->tipo }} @endif{{ $vistoria->logradouro }}, {{ $vistoria->numero }}
                                        </span>
                                    </a>
                                @else
                                    <span>
                                        @if($vistoria->tipo){{ $vistoria->tipo }} @endif{{ $vistoria->logradouro }}, {{ $vistoria->numero }}
                                    </span>
                                @endif
                                <div class="mobile-only text-muted" style="font-size: var(--text-xs); margin-top: var(--space-1);">
                                    @if($vistoria->complemento)
                                        <div style="margin-bottom: 2px;">{{ $vistoria->complemento }}</div>
                                    @endif
                                    {{ $vistoria->bairro }} - {{ $vistoria->regional ?? 'N/A' }}
                                    @if($vistoria->quantidade_pessoas)
                                        <span class="badge badge-info" style="margin-left: var(--space-2);">{{ $vistoria->quantidade_pessoas }} pessoas</span>
                                    @endif
                                </div>
                            </td>
                            <td class="hide-mobile">
                                @if($vistoria->complemento)
                                    {{ $vistoria->complemento }}
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td class="hide-mobile">{{ $vistoria->bairro }}</td>
                            <td class="hide-mobile">{{ $vistoria->regional ?? 'N/A' }}</td>
                            <td class="hide-mobile text-center">
                                @if($vistoria->quantidade_pessoas)
                                    <span class="badge badge-info">{{ $vistoria->quantidade_pessoas }}</span>
                                @else
                                    <span class="badge badge-default">0</span>
                                @endif
                            </td>
                            <td class="hide-mobile text-center">
                                @if($vistoria->qtd_kg)
                                    <span class="badge badge-accent">{{ $vistoria->qtd_kg }}</span>
                                @else
                                    <span class="badge badge-default">0</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($vistoria->cancelada)
                                    <span class="badge badge-danger" title="Cancelada" style="font-weight:700;">X</span>
                                @elseif($vistoria->finalizada)
                                    <span class="badge badge-success" title="Finalizada" style="font-weight:700;">F</span>
                                @else
                                    <span class="badge badge-info" title="Aberta" style="font-weight:700;">A</span>
                                @endif
                            </td>
                            <td>
                                @if($vistoria->resultado_acao)
                                    @php
                                        $badgeClass = match(true) {
                                            str_contains($vistoria->resultado_acao, 'persiste') => 'badge-danger',
                                            str_contains($vistoria->resultado_acao, 'parcialmente') => 'badge-warning',
                                            str_contains($vistoria->resultado_acao, 'ausente') => 'badge-default',
                                            str_contains($vistoria->resultado_acao, 'constatado') => 'badge-info',
                                            str_contains($vistoria->resultado_acao, 'Conformidade') => 'badge-success',
                                            default => 'badge-secondary',
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeClass }}">{{ $vistoria->resultado_acao }}</span>
                                @else
                                    <span class="badge badge-accent">Sem resultado</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <div style="display: flex; gap: var(--space-1); justify-content: center; align-items: center;">
                                    @if($vistoria->lat && $vistoria->lng)
                                        <a href="{{ route('mapa.index', ['lat' => $vistoria->lat, 'lng' => $vistoria->lng, 'zoom' => 19, 'ponto_id' => $vistoria->ponto_id, 'ajustar' => 1]) }}"
                                           class="btn btn-ghost btn-sm" title="Ver no mapa">
                                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                                            </svg>
                                        </a>
                                    @endif
                                    <a href="{{ route('vistorias.show', $vistoria->id) }}" class="btn btn-ghost btn-sm" title="Ver detalhes">
                                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                        </svg>
                                    </a>
                                    <a href="{{ route('vistorias.report', $vistoria->id) }}" class="btn btn-ghost btn-sm" title="Relatorio">
                                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                        </svg>
                                    </a>
                                    @if(!$vistoria->finalizada && !$vistoria->cancelada)
                                        <a href="{{ route('vistorias.edit', $vistoria->id) }}" class="btn btn-ghost btn-sm" title="Editar">
                                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center text-muted" style="padding: var(--space-6);">
                                Voce ainda nao realizou nenhuma vistoria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination-bar :paginator="$vistorias->withQueryString()" label="vistorias" />
    </div>
@endsection
