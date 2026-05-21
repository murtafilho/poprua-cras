@extends('layouts.app')

@section('title', 'Sprint 11 — Zeladoria')

@section('header')
    <div class="mobile-header-content">
        <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title">Sprint 11 — Zeladoria</span>
        <div style="width: 44px;"></div>
    </div>
@endsection

@php
    $statusLabel = [
        'implementado' => 'Implementado',
        'corrigido'    => 'Corrigido nesta sprint',
        'parcial'      => 'Parcial',
        'pendente'     => 'Pendente',
    ];
    $fmtH = fn (float $h) => rtrim(rtrim(number_format($h, 2, ',', '.'), '0'), ',') . 'h';
@endphp

@section('content')
    <div class="page-content">

        <div class="card mb-4">
            <div class="card-body">
                <h2 style="margin-top: 0;">Sprint 11 — Levantamento de Alteracoes do Sistema de Zeladoria</h2>
                <p class="text-muted">
                    Fonte: Levantamento_alteracao_sistema_zeladoria.pdf — emitido por <strong>{{ $emissor }}</strong> em {{ $dataLevantamento }}.
                    Confronto detalhado em <code>docs/AUDITORIA_Zeladoria.md</code>.
                </p>

                <h3>Resumo</h3>
                <ul style="margin: 0;">
                    <li>Andamento geral: <strong>{{ $totalAndamento }}%</strong></li>
                    <li>Fases totais: <strong>{{ $totalFases }}</strong></li>
                    <li>Esforco restante: <strong>{{ $fmtH($totalEsforco) }}</strong></li>
                    <li>Implementadas/Corrigidas: <strong>{{ $statusCount['implementado'] + $statusCount['corrigido'] }} de {{ $totalFases }}</strong></li>
                </ul>

                <h3>Distribuicao por status</h3>
                <ul style="margin: 0;">
                    @foreach ($statusLabel as $key => $label)
                        <li>{{ $label }}: <strong>{{ $statusCount[$key] }}</strong></li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h3 style="margin-top: 0;">Fases</h3>
                <div class="table-container">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th style="width: 60px;">Codigo</th>
                                <th>Fase</th>
                                <th style="width: 180px;">Status</th>
                                <th style="width: 100px;">Andamento</th>
                                <th style="width: 100px;">Resta</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($fases as $fase)
                                <tr>
                                    <td><code>{{ $fase['codigo'] }}</code></td>
                                    <td>
                                        <strong>{{ $fase['titulo'] }}</strong><br>
                                        <span class="text-muted">{{ $fase['descricao'] }}</span><br>
                                        <span style="font-size: var(--text-sm);">{{ $fase['notas'] }}</span>
                                    </td>
                                    <td>{{ $statusLabel[$fase['status']] ?? '-' }}</td>
                                    <td>{{ $fase['andamento'] }}%</td>
                                    <td>{{ $fase['esforco_restante'] > 0 ? $fmtH($fase['esforco_restante']) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h3 style="margin-top: 0;">Plano residual ({{ $fmtH($totalEsforco) }})</h3>
                <ol>
                    <li><strong>1.2</strong> — Salvamento parcial / autosave (7h)</li>
                    <li><strong>1.8</strong> — Complementacao com justificativa pos-finalizada (4h)</li>
                    <li><strong>1.1</strong> — Seeder de membros + lookup <code>tipos_equipe</code> (2h)</li>
                    <li><strong>1.3</strong> — Condicional UI + export Excel do roteiro (2h)</li>
                    <li><strong>1.6</strong> — PDF nativo do roteiro via Laravel-DomPDF (1h)</li>
                    <li><strong>1.9</strong> — Campo <code>houve_lavacao</code> distinto de <code>houve_lavratura</code> (0,5h)</li>
                    <li><strong>1.7</strong> — Link "Ajustar localizacao" em <code>vistorias/show</code> (0,25h)</li>
                </ol>
            </div>
        </div>

    </div>
@endsection
