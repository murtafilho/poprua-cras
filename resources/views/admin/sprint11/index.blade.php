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
    $statusMeta = [
        'implementado' => ['label' => 'Implementado',            'badge' => 'badge-success'],
        'corrigido'    => ['label' => 'Corrigido nesta sprint',  'badge' => 'badge-info'],
        'parcial'      => ['label' => 'Parcial',                 'badge' => 'badge-warning'],
        'pendente'     => ['label' => 'Pendente',                'badge' => 'badge-danger'],
    ];
    $fmtH = fn (float $h) => rtrim(rtrim(number_format($h, 2, ',', '.'), '0'), ',') . 'h';
    $barColor = function (int $p): string {
        if ($p >= 100) return 'var(--color-success)';
        if ($p >= 80)  return 'var(--accent-primary)';
        if ($p >= 50)  return 'var(--color-warning)';
        return 'var(--color-danger)';
    };
    $implCorr = $statusCount['implementado'] + $statusCount['corrigido'];
@endphp

@section('content')
    <style>
        .sprint-kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-3); }
        .sprint-kpi-value { font-size: var(--text-2xl); font-weight: var(--font-bold); line-height: 1.1; }
        .sprint-kpi-label { font-size: var(--text-xs); color: var(--text-muted); margin-bottom: var(--space-1); }
        .sprint-bar { flex: 1; height: 8px; background: var(--bg-tertiary); border-radius: 999px; overflow: hidden; }
        .sprint-bar > span { display: block; height: 100%; border-radius: 999px; transition: width .3s ease; }
        .sprint-notas { margin: var(--space-3) 0 0; font-size: var(--text-sm); color: var(--text-secondary);
                        border-left: 3px solid var(--border-secondary); padding-left: var(--space-3); }
        @media (max-width: 767px) { .sprint-kpis { grid-template-columns: repeat(2, 1fr); } }
    </style>

    <div class="page-content">

        {{-- Cabecalho / fonte --}}
        <div class="card mb-4">
            <div class="card-body">
                <h2 style="margin-top: 0; margin-bottom: var(--space-2);">Sprint 11 — Alterações do Sistema de Zeladoria</h2>
                <p class="text-muted" style="margin-bottom: 0;">
                    Fonte: <em>Levantamento_alteracao_sistema_zeladoria.pdf</em> — emitido por
                    <strong>{{ $emissor }}</strong> em {{ $dataLevantamento }}.
                    Confronto detalhado em <code>docs/AUDITORIA_Zeladoria.md</code>.
                </p>
            </div>
        </div>

        {{-- KPIs --}}
        <div class="sprint-kpis mb-4">
            <div class="card" style="text-align: center;">
                <div class="card-body">
                    <p class="sprint-kpi-label">Andamento geral</p>
                    <p class="sprint-kpi-value" style="color: {{ $barColor($totalAndamento) }};">{{ $totalAndamento }}%</p>
                </div>
            </div>
            <div class="card" style="text-align: center;">
                <div class="card-body">
                    <p class="sprint-kpi-label">Fases totais</p>
                    <p class="sprint-kpi-value" style="color: var(--accent-primary);">{{ $totalFases }}</p>
                </div>
            </div>
            <div class="card" style="text-align: center;">
                <div class="card-body">
                    <p class="sprint-kpi-label">Implementadas / Corrigidas</p>
                    <p class="sprint-kpi-value" style="color: var(--color-success);">{{ $implCorr }}<span style="font-size: var(--text-base); color: var(--text-muted); font-weight: var(--font-medium);">/{{ $totalFases }}</span></p>
                </div>
            </div>
            <div class="card" style="text-align: center;">
                <div class="card-body">
                    <p class="sprint-kpi-label">Esforço restante</p>
                    <p class="sprint-kpi-value" style="color: {{ $totalEsforco > 0 ? 'var(--color-warning)' : 'var(--color-success)' }};">{{ $fmtH($totalEsforco) }}</p>
                </div>
            </div>
        </div>

        {{-- Barra de andamento geral --}}
        <div class="card mb-4">
            <div class="card-body">
                <div style="display: flex; align-items: center; gap: var(--space-3);">
                    <span style="font-size: var(--text-sm); font-weight: var(--font-semibold); white-space: nowrap;">Progresso geral</span>
                    <div class="sprint-bar">
                        <span style="width: {{ $totalAndamento }}%; background: {{ $barColor($totalAndamento) }};"></span>
                    </div>
                    <span style="font-size: var(--text-sm); font-weight: var(--font-semibold); min-width: 40px; text-align: right; color: {{ $barColor($totalAndamento) }};">{{ $totalAndamento }}%</span>
                </div>

                {{-- Distribuicao por status --}}
                <div style="display: flex; flex-wrap: wrap; gap: var(--space-2); margin-top: var(--space-4);">
                    @foreach ($statusMeta as $key => $meta)
                        <span class="badge {{ $meta['badge'] }}" style="display: inline-flex; align-items: center; gap: 6px; font-size: var(--text-sm); padding: var(--space-1) var(--space-3);">
                            {{ $meta['label'] }} <strong>{{ $statusCount[$key] }}</strong>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Fases (cards) --}}
        <h3 style="margin: var(--space-2) 0 var(--space-3);">Fases ({{ $totalFases }})</h3>
        <div class="flex flex-col gap-3 mb-4">
            @foreach ($fases as $fase)
                @php
                    $meta = $statusMeta[$fase['status']] ?? ['label' => '—', 'badge' => 'badge-default'];
                    $p = (int) $fase['andamento'];
                @endphp
                <div class="card">
                    <div class="card-body">
                        {{-- Linha titulo --}}
                        <div style="display: flex; align-items: flex-start; gap: var(--space-3); flex-wrap: wrap;">
                            <span class="badge badge-dark" style="font-family: monospace; font-size: var(--text-sm);">{{ $fase['codigo'] }}</span>
                            <div style="flex: 1; min-width: 200px;">
                                <strong style="display: block;">{{ $fase['titulo'] }}</strong>
                                <span class="text-muted" style="font-size: var(--text-sm);">{{ $fase['descricao'] }}</span>
                            </div>
                            <span class="badge {{ $meta['badge'] }}" style="white-space: nowrap;">{{ $meta['label'] }}</span>
                        </div>

                        {{-- Linha progresso --}}
                        <div style="display: flex; align-items: center; gap: var(--space-3); margin-top: var(--space-3);">
                            <div class="sprint-bar">
                                <span style="width: {{ $p }}%; background: {{ $barColor($p) }};"></span>
                            </div>
                            <span style="font-size: var(--text-sm); font-weight: var(--font-semibold); min-width: 40px; text-align: right; color: {{ $barColor($p) }};">{{ $p }}%</span>
                            <span class="badge badge-default" style="white-space: nowrap;" title="Esforço restante">
                                {{ $fase['esforco_restante'] > 0 ? 'resta ' . $fmtH($fase['esforco_restante']) : 'concluído' }}
                            </span>
                        </div>

                        {{-- Notas --}}
                        <p class="sprint-notas">{{ $fase['notas'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Plano residual --}}
        <div class="card mb-4">
            <div class="card-body">
                <h3 style="margin-top: 0;">Plano residual ({{ $fmtH($totalEsforco) }})</h3>
                <ol style="margin: 0; padding-left: var(--space-5); line-height: 1.9;">
                    <li><strong>1.2</strong> — Salvamento parcial / autosave (7h)</li>
                    <li><strong>1.8</strong> — Complementação com justificativa pós-finalizada (4h)</li>
                    <li><strong>1.1</strong> — Seeder de membros + lookup <code>tipos_equipe</code> (2h)</li>
                    <li><strong>1.3</strong> — Condicional UI + export Excel do roteiro (2h)</li>
                    <li><strong>1.6</strong> — PDF nativo do roteiro via Laravel-DomPDF (1h)</li>
                    <li><strong>1.9</strong> — Campo <code>houve_lavacao</code> distinto de <code>houve_lavratura</code> (0,5h)</li>
                    <li><strong>1.7</strong> — Link "Ajustar localização" em <code>vistorias/show</code> (0,25h)</li>
                </ol>
            </div>
        </div>

    </div>
@endsection
