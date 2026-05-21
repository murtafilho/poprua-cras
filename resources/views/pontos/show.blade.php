@extends('layouts.app')

@section('title', 'Detalhes do Ponto')

@section('header')
    <a href="{{ route('pontos.index') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
        <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <span class="mobile-header-title" style="flex: 1; text-align: center;">Detalhes do Ponto</span>
    <a href="{{ route('pontos.edit', $ponto->id) }}" class="btn btn-ghost btn-icon" title="Editar ponto">
        <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
        </svg>
    </a>
@endsection

@section('content')
    <div class="page-content">
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title">Informações do Ponto</span>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Endereço</span>
                        <span class="info-value">
                            @if($ponto->tipo){{ $ponto->tipo }} @endif{{ $ponto->logradouro }}, {{ $ponto->numero }}
                            @if($ponto->complemento)
                                <span class="text-muted">- {{ $ponto->complemento }}</span>
                            @endif
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Bairro / Regional</span>
                        <span class="info-value">{{ $ponto->bairro }} - {{ $ponto->regional }}</span>
                    </div>
                    @if($ponto->lat && $ponto->lng)
                    <div class="info-item">
                        <span class="info-label">Coordenadas</span>
                        <a href="{{ route('mapa.index', ['lat' => $ponto->lat, 'lng' => $ponto->lng, 'zoom' => 19]) }}" style="display: flex; align-items: center; gap: var(--space-2);">
                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            {{ number_format($ponto->lat, 6) }}, {{ number_format($ponto->lng, 6) }}
                        </a>
                    </div>
                    @endif
                    <div class="info-item">
                        <span class="info-label">Total de Vistorias</span>
                        <span class="badge badge-info">{{ $ponto->total_vistorias }}</span>
                    </div>
                    @if($ponto->resultado_acao)
                    <div class="info-item">
                        <span class="info-label">Último Resultado</span>
                        @php
                            $badgeClass = match(true) {
                                $ponto->resultado_acao_id == 1 => 'badge-danger',
                                $ponto->resultado_acao_id == 2 => 'badge-warning',
                                in_array($ponto->resultado_acao_id, [3, 4]) => 'badge-default',
                                $ponto->resultado_acao_id == 5 => 'badge-info',
                                $ponto->resultado_acao_id == 6 => 'badge-success',
                                default => 'badge-default',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $ponto->resultado_acao }}</span>
                    </div>
                    @endif
                </div>

                @if($ponto->lat && $ponto->lng)
                <div style="display: flex; flex-wrap: wrap; gap: var(--space-2); margin-top: var(--space-4);">
                    <a href="{{ route('mapa.index', ['lat' => $ponto->lat, 'lng' => $ponto->lng, 'zoom' => 19, 'ponto_id' => $ponto->id]) }}"
                       class="btn btn-primary">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        Ver no mapa
                    </a>
                    <a href="{{ route('mapa.index', ['lat' => $ponto->lat, 'lng' => $ponto->lng, 'zoom' => 19, 'ponto_id' => $ponto->id, 'ajustar' => 1]) }}"
                       class="btn btn-secondary"
                       title="Recalibrar o georreferenciamento deste ponto">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Ajustar localização
                    </a>
                </div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
                <span class="card-title">Vistorias ({{ $vistorias->total() }})</span>
                <span class="text-muted" style="font-size: var(--text-xs);">Ordenadas por data decrescente</span>
            </div>

            @if($vistorias->count() > 0)
                <div class="table-container" style="border: none; border-radius: 0;">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Data/Hora</th>
                                <th class="hide-mobile">Tipo Abordagem</th>
                                <th>Pessoas</th>
                                <th class="hide-mobile">Kg</th>
                                <th>Resultado</th>
                                <th class="hide-mobile">Usuário</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($vistorias as $vistoria)
                                <tr>
                                    <td>
                                        <div style="font-weight: var(--font-medium);">{{ \Carbon\Carbon::parse($vistoria->data_abordagem)->format('d/m/Y') }}</div>
                                        <div class="text-muted" style="font-size: var(--text-xs);">{{ \Carbon\Carbon::parse($vistoria->data_abordagem)->format('H:i') }}</div>
                                    </td>
                                    <td class="hide-mobile">{{ $vistoria->tipo_abordagem ?? '-' }}</td>
                                    <td>
                                        @if($vistoria->quantidade_pessoas)
                                            <span style="font-weight: var(--font-medium);">{{ $vistoria->quantidade_pessoas }}</span>
                                            @if($vistoria->nomes_pessoas)
                                                <div class="text-muted" style="font-size: var(--text-xs); margin-top: 2px;">{{ \Illuminate\Support\Str::limit($vistoria->nomes_pessoas, 30) }}</div>
                                            @endif
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="hide-mobile">
                                        @if($vistoria->qtd_kg)
                                            {{ $vistoria->qtd_kg }} kg
                                        @else
                                            <span class="text-muted">-</span>
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
                                                    default => 'badge-default',
                                                };
                                            @endphp
                                            <span class="badge {{ $badgeClass }}">{{ $vistoria->resultado_acao }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="hide-mobile">{{ $vistoria->usuario ?? '-' }}</td>
                                </tr>
                                @if($vistoria->observacao)
                                <tr>
                                    <td colspan="6" style="padding: var(--space-2) var(--space-4);">
                                        <span class="text-muted" style="font-size: var(--text-xs);"><strong>Observação:</strong> {{ $vistoria->observacao }}</span>
                                    </td>
                                </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card-body text-center text-muted" style="padding: var(--space-8);">
                    Nenhuma vistoria registrada para este ponto.
                </div>
            @endif

            @if($vistorias->hasPages())
                <div class="card-footer">
                    {{ $vistorias->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
