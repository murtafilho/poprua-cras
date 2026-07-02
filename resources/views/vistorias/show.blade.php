@extends('layouts.app')

@section('title', 'Detalhes da Zeladoria')

@push('styles')
<style>
    .historico-lista { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; }
    .historico-lista li { display: flex; justify-content: space-between; gap: var(--space-2); padding: var(--space-2) 0; border-bottom: 1px solid var(--border-subtle, rgba(255,255,255,.08)); }
    .historico-lista li:last-child { border-bottom: 0; }
    .historico-lista .data { font-weight: var(--font-semibold); }
    .historico-lista .meta { font-size: var(--text-xs); color: var(--text-muted); }

    @@media print {
        .mobile-header, .bottom-nav, #sidebar, .sidebar, .no-print, .btn, form, button { display: none !important; }
        .page, .page-content { padding: 0 !important; margin: 0 !important; }
        body { background: #fff !important; color: #000 !important; }
        .card, .section-card { box-shadow: none !important; border: 1px solid #ccc !important; break-inside: avoid; }
    }
</style>
@endpush

@section('header')
    <a href="{{ route('vistorias.index') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
        <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <span class="mobile-header-title" style="flex: 1; text-align: center;">Detalhes da Zeladoria</span>
    <div style="display: flex; gap: var(--space-1); align-items: center;">
        @if($vistoria->ponto && $vistoria->ponto->lat && $vistoria->ponto->lng)
            <a href="{{ route('mapa.index', ['lat' => $vistoria->ponto->lat, 'lng' => $vistoria->ponto->lng, 'zoom' => 19]) }}" class="btn btn-ghost btn-icon" title="Ver no mapa">
                <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
            </a>
            <a href="{{ route('mapa.index', ['lat' => $vistoria->ponto->lat, 'lng' => $vistoria->ponto->lng, 'zoom' => 19, 'ponto_id' => $vistoria->ponto_id, 'ajustar' => 1]) }}" class="btn btn-ghost btn-icon" title="Ajustar localização">
                <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </a>
        @endif
        <button type="button" id="btn-print-vistoria" class="btn btn-ghost btn-icon no-print" title="Imprimir">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
        </button>
        @can('update', $vistoria)
            <a href="{{ route('vistorias.edit', $vistoria) }}" class="btn btn-ghost btn-icon" title="Editar">
                <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </a>
        @endcan
    </div>
@endsection

@section('content')
    @php
        use App\Enums\TipoEquipe;
        use App\Support\FormatoData;
    @endphp
    <div class="page-content">
        {{-- Cabecalho --}}
        <div class="card mb-4">
            <div class="card-body">
                <div style="display: flex; align-items: flex-start; gap: var(--space-4);">
                    <div style="flex: 1;">
                        <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2);">
                            <svg style="width: 24px; height: 24px; color: var(--accent-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                            @php
                                $dataAbordagem = \Carbon\Carbon::parse($vistoria->data_abordagem);
                            @endphp
                            <h2 style="font-size: var(--text-lg); font-weight: var(--font-semibold);">
                                {{ FormatoData::exibir($dataAbordagem) }}
                            </h2>
                        </div>

                        @if($vistoria->ponto && $vistoria->ponto->enderecoAtualizado)
                            <p style="font-size: var(--text-sm); color: var(--text-secondary);">
                                @if($vistoria->ponto->enderecoAtualizado->SIGLA_TIPO_LOGRADOURO)
                                    {{ $vistoria->ponto->enderecoAtualizado->SIGLA_TIPO_LOGRADOURO }}
                                @endif
                                {{ $vistoria->ponto->enderecoAtualizado->NOME_LOGRADOURO }},
                                {{ $vistoria->ponto->enderecoAtualizado->NUMERO_IMOVEL ?? $vistoria->ponto->numero }}
                                @if($vistoria->ponto->complemento)
                                    <span class="text-muted">- {{ $vistoria->ponto->complemento }}</span>
                                @endif
                            </p>
                            <p class="text-muted" style="font-size: var(--text-xs);">
                                {{ $vistoria->ponto->enderecoAtualizado->NOME_BAIRRO_OFICIAL }} - {{ $vistoria->ponto->enderecoAtualizado->NOME_REGIONAL }}
                            </p>
                        @endif
                    </div>

                    @if($vistoria->resultadoAcao)
                        @php
                            $badgeClass = match(true) {
                                str_contains($vistoria->resultadoAcao->resultado, 'persiste') => 'badge-danger',
                                str_contains($vistoria->resultadoAcao->resultado, 'parcialmente') => 'badge-warning',
                                str_contains($vistoria->resultadoAcao->resultado, 'ausente') => 'badge-default',
                                str_contains($vistoria->resultadoAcao->resultado, 'constatado') => 'badge-info',
                                str_contains($vistoria->resultadoAcao->resultado, 'Conformidade') => 'badge-success',
                                default => 'badge-secondary',
                            };
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $vistoria->resultadoAcao->resultado }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- 1. Dados da Zeladoria --}}
        <div class="card mb-4">
            <div class="card-body">
                <h3 style="font-size: var(--text-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-3); display: flex; align-items: center; gap: var(--space-2);">
                    <svg style="width: 18px; height: 18px; color: var(--status-info);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Dados da Zeladoria
                </h3>

                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Tipo de Abordagem</span>
                        <span class="info-value">{{ $vistoria->tipoAbordagem->tipo ?? '-' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Registrado por</span>
                        <span class="info-value">{{ $vistoria->user->name ?? '-' }}</span>
                    </div>
                    @if($vistoria->houve_comunicado || $vistoria->data_prevista_zeladoria)
                        @if($vistoria->houve_comunicado)
                            <div class="info-item">
                                <span class="info-label">Comunicado de Zeladoria</span>
                                <span class="info-value">Sim</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Data do Comunicado</span>
                                <span class="info-value">{{ $vistoria->data_comunicado ? FormatoData::exibir($vistoria->data_comunicado) : '-' }}</span>
                            </div>
                        @endif
                        @if($vistoria->data_prevista_zeladoria)
                            <div class="info-item">
                                <span class="info-label">Data de Agendamento</span>
                                <span class="info-value">{{ FormatoData::exibir($vistoria->data_prevista_zeladoria) }}</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Período de Agendamento</span>
                                <span class="info-value">{{ $vistoria->periodo_zeladoria === 'manha' ? 'Manhã' : ($vistoria->periodo_zeladoria === 'tarde' ? 'Tarde' : '-') }}</span>
                            </div>
                        @endif
                    @endif
                    @if($vistoria->houve_lavacao)
                        <div class="info-item">
                            <span class="info-label">Houve Lavação</span>
                            <span class="info-value">Sim</span>
                        </div>
                    @endif
                </div>

                @if($vistoria->cancelada)
                    <div style="margin-top: var(--space-3); padding: var(--space-2) var(--space-3); background: rgba(239,68,68,0.1); border-radius: var(--radius-md); display: flex; align-items: center; gap: var(--space-2);">
                        <svg style="width: 16px; height: 16px; color: var(--status-danger, #ef4444);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        <span style="font-size: var(--text-xs); color: var(--status-danger, #ef4444); font-weight: var(--font-medium);">
                            Cancelada em {{ FormatoData::exibir($vistoria->cancelada_em) }}
                        </span>
                    </div>
                @elseif($vistoria->finalizada)
                    <div style="margin-top: var(--space-3); padding: var(--space-2) var(--space-3); background: var(--bg-success-subtle, rgba(34,197,94,0.1)); border-radius: var(--radius-md); display: flex; align-items: center; gap: var(--space-2);">
                        <svg style="width: 16px; height: 16px; color: var(--status-success);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span style="font-size: var(--text-xs); color: var(--status-success); font-weight: var(--font-medium);">
                            Finalizada em {{ FormatoData::exibir($vistoria->finalizada_em) }}
                        </span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Participantes --}}
        @if($vistoria->participantes->count() > 0)
            <div class="card mb-4">
                <div class="card-body">
                    <h3 style="font-size: var(--text-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-3); display: flex; align-items: center; gap: var(--space-2);">
                        <svg style="width: 18px; height: 18px; color: var(--accent-secondary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Participantes
                    </h3>
                    @php
                        $grouped = $vistoria->participantes->groupBy(fn ($u) => TipoEquipe::fromUser($u)->value);
                    @endphp
                    @foreach (TipoEquipe::ordenados() as $tipo)
                        @if ($grouped->has($tipo->value))
                            <div style="margin-bottom: var(--space-2);">
                                <span class="info-label" style="display: block;">{{ $tipo->label() }}</span>
                                @foreach ($grouped[$tipo->value] as $p)
                                    <span class="badge badge-secondary" style="margin-right: var(--space-1); margin-bottom: var(--space-1);">{{ $p->name }}</span>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        {{-- 2. Perfil da Ocorrencia (Caracteristicas) --}}
        <div class="card mb-4">
            <div class="card-body">
                <h3 style="font-size: var(--text-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-3); display: flex; align-items: center; gap: var(--space-2);">
                    <svg style="width: 18px; height: 18px; color: var(--status-warning);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Perfil da Ocorrencia
                </h3>

                {{-- Quantidades --}}
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Quantidade de Pessoas</span>
                        <span class="info-value">{{ $vistoria->quantidade_pessoas ?? 0 }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Material Recolhido (Kg)</span>
                        <span class="info-value">{{ $vistoria->qtd_kg ?? 0 }} kg</span>
                    </div>
                </div>

                {{-- Nomes das Pessoas --}}
                @if($vistoria->nomes_pessoas)
                    <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--border-primary);">
                        <span class="info-label" style="display: block; margin-bottom: var(--space-2);">Nomes das Pessoas</span>
                        <p class="text-secondary" style="font-size: var(--text-sm); white-space: pre-wrap;">{{ $vistoria->nomes_pessoas }}</p>
                    </div>
                @endif

                {{-- Abrigos --}}
                @if($vistoria->qtd_abrigos_provisorios > 0 || count($tiposAbrigoSelecionados) > 0 || $vistoria->tipoAbrigoDesmontado)
                    <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--border-primary);">
                        <span class="info-label" style="display: block; margin-bottom: var(--space-2);">
                            <svg style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: var(--space-1); color: var(--accent-secondary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                            Abrigos
                        </span>

                        <div class="info-grid">
                            @if($vistoria->qtd_abrigos_provisorios > 0)
                                <div class="info-item">
                                    <span class="info-label">Qtd. Abrigos Provisorios</span>
                                    <span class="info-value">{{ $vistoria->qtd_abrigos_provisorios }}</span>
                                </div>
                            @endif

                            @if($vistoria->tipoAbrigoDesmontado)
                                <div class="info-item">
                                    <span class="info-label">Tipo Abrigo Desmontado</span>
                                    <span class="info-value">{{ $vistoria->tipoAbrigoDesmontado->tipo_abrigo }}</span>
                                </div>
                            @endif
                        </div>

                        @if(count($tiposAbrigoSelecionados) > 0)
                            <div style="margin-top: var(--space-2);">
                                <span class="info-label" style="display: block; margin-bottom: var(--space-2);">Tipos de Abrigo</span>
                                <div style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
                                    @foreach($tiposAbrigoSelecionados as $tipo)
                                        <span class="badge badge-secondary">{{ $tipo }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Fatores de Complexidade --}}
                @php
                    $caracteristicas = [
                        ['campo' => 'resistencia', 'label' => 'Resistencia'],
                        ['campo' => 'num_reduzido', 'label' => 'Numero Reduzido'],
                        ['campo' => 'casal', 'label' => 'Casal', 'extra' => $vistoria->qtd_casais ? "({$vistoria->qtd_casais} casais)" : null],
                        ['campo' => 'catador_reciclados', 'label' => 'Catador de Reciclados'],
                        ['campo' => 'fixacao_antiga', 'label' => 'Fixacao Antiga'],
                        ['campo' => 'excesso_objetos', 'label' => 'Excesso de Objetos'],
                        ['campo' => 'trafico_ilicitos', 'label' => 'Trafico/Ilicitos'],
                        ['campo' => 'crianca_adolescente', 'label' => 'Crianca/Adolescente'],
                        ['campo' => 'idosos', 'label' => 'Idosos'],
                        ['campo' => 'gestante', 'label' => 'Gestante'],
                        ['campo' => 'lgbtqiapn', 'label' => 'LGBTQIAPN+'],
                        ['campo' => 'deficiente', 'label' => 'Deficiente'],
                        ['campo' => 'agrupamento_quimico', 'label' => 'Agrupamento Quimico'],
                        ['campo' => 'saude_mental', 'label' => 'Saude Mental'],
                        ['campo' => 'cena_uso_caracterizada', 'label' => 'Cena de Uso Caracterizada'],
                        ['campo' => 'animais', 'label' => 'Animais', 'extra' => $vistoria->qtd_animais ? "({$vistoria->qtd_animais} animais)" : null],
                    ];
                    $caracteristicasAtivas = collect($caracteristicas)->filter(fn($c) => $vistoria->{$c['campo']});
                @endphp

                @if($caracteristicasAtivas->count() > 0)
                    <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--border-primary);">
                        <span class="info-label" style="display: block; margin-bottom: var(--space-2);">
                            <svg style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: var(--space-1); color: var(--status-warning);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            Fatores de Complexidade
                        </span>
                        <div style="display: flex; flex-wrap: wrap; gap: var(--space-2);">
                            @foreach($caracteristicasAtivas as $c)
                                <span class="badge badge-warning">
                                    {{ $c['label'] }}
                                    @if(isset($c['extra']) && $c['extra'])
                                        <span class="text-muted">{{ $c['extra'] }}</span>
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- 3. Relatorio da Acao --}}
        <div class="card mb-4">
            <div class="card-body">
                <h3 style="font-size: var(--text-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-3); display: flex; align-items: center; gap: var(--space-2);">
                    <svg style="width: 18px; height: 18px; color: var(--accent-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Relatorio da Acao
                </h3>

                {{-- Resultado da Acao --}}
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Resultado da Acao</span>
                        <span class="info-value">
                            @if($vistoria->resultadoAcao)
                                @php
                                    $resultadoBadge = match(true) {
                                        str_contains($vistoria->resultadoAcao->resultado, 'persiste') => 'badge-danger',
                                        str_contains($vistoria->resultadoAcao->resultado, 'parcialmente') => 'badge-warning',
                                        str_contains($vistoria->resultadoAcao->resultado, 'ausente') => 'badge-default',
                                        str_contains($vistoria->resultadoAcao->resultado, 'constatado') => 'badge-info',
                                        str_contains($vistoria->resultadoAcao->resultado, 'Conformidade') => 'badge-success',
                                        default => 'badge-secondary',
                                    };
                                @endphp
                                <span class="badge {{ $resultadoBadge }}">{{ $vistoria->resultadoAcao->resultado }}</span>
                            @else
                                -
                            @endif
                        </span>
                    </div>
                </div>

                {{-- Acoes Realizadas --}}
                @if($vistoria->conducao_forcas_seguranca || $vistoria->apreensao_fiscal || $vistoria->auto_fiscalizacao_aplicado)
                    <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--border-primary);">
                        <span class="info-label" style="display: block; margin-bottom: var(--space-2);">
                            <svg style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: var(--space-1); color: var(--status-danger);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                            Acoes Realizadas
                        </span>

                        <div class="info-grid">
                            @if($vistoria->conducao_forcas_seguranca)
                                <div class="info-item">
                                    <span class="info-label">Conducao Forcas Seguranca</span>
                                    <span class="info-value">
                                        <span class="badge badge-danger">Sim</span>
                                        @if($vistoria->conducao_forcas_observacao)
                                            <span class="text-muted" style="display: block; font-size: var(--text-xs); margin-top: var(--space-1);">
                                                {{ $vistoria->conducao_forcas_observacao }}
                                            </span>
                                        @endif
                                    </span>
                                </div>
                            @endif

                            @if($vistoria->apreensao_fiscal)
                                <div class="info-item">
                                    <span class="info-label">Recolhimento de Inservíveis</span>
                                    <span class="badge badge-danger">Sim</span>
                                </div>
                            @endif

                            @if($vistoria->auto_fiscalizacao_aplicado)
                                <div class="info-item">
                                    <span class="info-label">Relatório de Orientação</span>
                                    <span class="info-value">
                                        <span class="badge badge-warning">Aplicado</span>
                                        @if($vistoria->auto_fiscalizacao_numero)
                                            <span class="text-mono" style="display: block; font-size: var(--text-xs); margin-top: var(--space-1);">
                                                N {{ $vistoria->auto_fiscalizacao_numero }}
                                            </span>
                                        @endif
                                    </span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Relatorio Descritivo --}}
                @if($vistoria->observacao)
                    <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--border-primary);">
                        <span class="info-label" style="display: block; margin-bottom: var(--space-2);">
                            <svg style="width: 14px; height: 14px; display: inline-block; vertical-align: middle; margin-right: var(--space-1); color: var(--text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                            </svg>
                            Relatorio Descritivo
                        </span>
                        <p class="text-secondary" style="font-size: var(--text-sm); white-space: pre-wrap;">{{ $vistoria->observacao }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- 4. Encaminhamentos --}}
        @php
            $encaminhamentos = collect([
                $vistoria->encaminhamento1,
                $vistoria->encaminhamento2,
                $vistoria->encaminhamento3,
                $vistoria->encaminhamento4,
                $vistoria->encaminhamento5,
                $vistoria->encaminhamento6,
            ])->filter();
        @endphp

        @if($encaminhamentos->count() > 0)
            <div class="card mb-4">
                <div class="card-body">
                    <h3 style="font-size: var(--text-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-3); display: flex; align-items: center; gap: var(--space-2);">
                        <svg style="width: 18px; height: 18px; color: var(--status-success);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                        Encaminhamentos
                    </h3>

                    <div style="display: flex; flex-direction: column; gap: var(--space-2);">
                        @foreach($encaminhamentos as $enc)
                            <div style="display: flex; align-items: center; gap: var(--space-2); padding: var(--space-2); background: var(--bg-tertiary); border-radius: var(--radius-md);">
                                <svg style="width: 16px; height: 16px; color: var(--status-success);" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span style="font-size: var(--text-sm);">{{ $enc->encaminhamento }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- 5. Moradores --}}
        @if($vistoria->moradoresEntrada->count() > 0)
            <div class="card mb-4">
                <div class="card-body">
                    <h3 style="font-size: var(--text-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-3); display: flex; align-items: center; gap: var(--space-2);">
                        <svg style="width: 18px; height: 18px; color: var(--status-info);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Moradores ({{ $vistoria->moradoresEntrada->count() }})
                    </h3>

                    <div style="display: flex; flex-direction: column; gap: var(--space-2);">
                        @foreach($vistoria->moradoresEntrada as $historico)
                            @if($historico->morador)
                                <a href="{{ route('moradores.show', $historico->morador) }}" class="location-card">
                                    <div class="location-card-content">
                                        <p class="location-card-address">{{ $historico->morador->nome_social }}</p>
                                        @if($historico->morador->apelido)
                                            <p class="location-card-detail">"{{ $historico->morador->apelido }}"</p>
                                        @endif
                                    </div>
                                    <svg style="width: 20px; height: 20px; color: var(--text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- 6. Fotos --}}
        @php
            $fotos = $vistoria->getMedia('fotos');
        @endphp

        <div class="card mb-4" id="fotos-section">
            <div class="card-body">
                <h3 style="font-size: var(--text-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-3); display: flex; align-items: center; gap: var(--space-2);">
                    <svg style="width: 18px; height: 18px; color: var(--accent-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Fotos (<span id="fotos-count">{{ $fotos->count() }}</span>)
                </h3>

                <div class="photo-grid" id="fotos-grid">
                    @foreach($fotos as $index => $foto)
                        <div
                            class="photo-item photo-item-expandable"
                            data-slide-index="{{ $index }}"
                            role="button"
                            tabindex="0"
                            aria-label="Ampliar foto {{ $index + 1 }}"
                        >
                            <img src="{{ $foto->hasGeneratedConversion('thumb') ? $foto->getUrl('thumb') : $foto->getUrl() }}" alt="Foto da vistoria" loading="lazy" x-on:error="$el.src='{{ $foto->getUrl() }}'">
                            @if($foto->getCustomProperty('publica', false))
                                <span class="photo-item-badge photo-item-badge--relatorio">No relatório</span>
                            @endif
                            @if($foto->getCustomProperty('legenda'))
                                <div class="photo-item-caption">{{ $foto->getCustomProperty('legenda') }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>

                {{-- Lightbox de fotos ampliadas --}}
                <div
                    id="slideshow-overlay"
                    class="slideshow-overlay"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Foto ampliada"
                    hidden
                >
                    <button type="button" id="slide-close" class="slideshow-close-btn" aria-label="Fechar">
                        <svg style="width:28px; height:28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>

                    <div id="slide-counter" class="slideshow-counter"></div>

                    <button type="button" id="slide-prev" class="slideshow-nav-btn slideshow-nav-btn--prev" aria-label="Foto anterior">
                        <svg style="width:24px; height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>

                    <img id="slide-image" src="" alt="Foto ampliada da zeladoria">

                    <button type="button" id="slide-next" class="slideshow-nav-btn slideshow-nav-btn--next" aria-label="Próxima foto">
                        <svg style="width:24px; height:24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>

                    <p id="slide-caption" class="slideshow-caption" hidden></p>
                </div>

                {{-- Fotos pendentes (local) --}}
                <div id="fotos-pendentes" class="hidden" style="margin-top: var(--space-3);">
                    <div style="display: flex; align-items: center; gap: var(--space-2); margin-bottom: var(--space-2);">
                        <svg style="width: 16px; height: 16px; color: var(--color-warning);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span style="font-size: var(--text-sm); color: var(--color-warning); font-weight: var(--font-medium);">
                            <span id="pendentes-count">0</span> foto(s) pendente(s) no dispositivo
                        </span>
                    </div>
                    <div id="pendentes-preview" class="photo-grid" style="margin-bottom: var(--space-3);"></div>
                    <button type="button" id="btn-sync-fotos" class="btn btn-primary btn-block">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Sincronizar Fotos
                    </button>
                    <div id="sync-progress" class="hidden" style="margin-top: var(--space-2);">
                        <div style="background: var(--bg-tertiary); border-radius: var(--radius-full); height: 6px; overflow: hidden;">
                            <div id="sync-bar" style="height: 100%; background: var(--accent-primary); border-radius: var(--radius-full); transition: width 300ms ease; width: 0%;"></div>
                        </div>
                        <p id="sync-status" style="font-size: var(--text-xs); color: var(--text-muted); margin-top: var(--space-1);"></p>
                    </div>
                </div>
            </div>
        </div>


        <script>
            window.SLIDESHOW_URLS = @json($fotos->map(fn ($f) => $f->getUrl())->values());
            window.SLIDESHOW_LEGENDS = @json($fotos->map(fn ($f) => (string) $f->getCustomProperty('legenda', ''))->values());
            window.VISTORIA_ID = {{ $vistoria->id }};
        </script>


        {{-- Botao Finalizar / Complementar --}}
        @if(!$vistoria->finalizada)
            @can('update', $vistoria)
                <form action="{{ route('vistorias.finalizar', $vistoria) }}" method="POST" style="margin-bottom: var(--space-3);"
                      x-on:submit="if(!confirm('Deseja finalizar esta zeladoria? Apos a finalizacao, nao sera possivel editar.')) $event.preventDefault()">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Finalizar Zeladoria
                    </button>
                </form>
            @endcan
        @else
            @can('reativar', $vistoria)
                <form action="{{ route('vistorias.reativar', $vistoria) }}" method="POST" style="margin-bottom: var(--space-3);"
                      x-on:submit="if(!confirm('Deseja reativar esta zeladoria para que o responsavel possa retomar a edicao?')) $event.preventDefault()">
                    @csrf
                    <button type="submit" class="btn btn-secondary w-full">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Reativar para Edicao
                    </button>
                </form>
            @endcan
            <div class="card mb-4">
                <div class="card-body">
                    <h3 style="font-size: var(--text-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-3);">Complementar Zeladoria</h3>
                    <p class="text-muted" style="font-size: var(--text-xs); margin-bottom: var(--space-3);">
                        Esta zeladoria foi finalizada. Voce pode adicionar uma complementacao mediante justificativa.
                    </p>
                    <form action="{{ route('vistorias.complementar', $vistoria) }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label class="form-label required">Justificativa</label>
                            <textarea name="justificativa" rows="3" required minlength="10" maxlength="1000"
                                      placeholder="Descreva o motivo da complementacao..." class="form-input form-textarea"></textarea>
                        </div>
                        <button type="submit" class="btn btn-secondary w-full">Registrar Complementacao</button>
                    </form>
                </div>
            </div>
        @endif

        {{-- Botao Cancelar --}}
        @can('cancelar', $vistoria)
            <form action="{{ route('vistorias.cancelar', $vistoria) }}" method="POST" style="margin-bottom: var(--space-3);"
                  x-on:submit="if(!confirm('Deseja cancelar esta zeladoria? Esta acao nao podera ser desfeita.')) $event.preventDefault()">
                @csrf
                <button type="submit" class="btn btn-danger w-full">
                    <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Cancelar Zeladoria
                </button>
            </form>
        @endcan

        {{-- Histórico do ponto (vistoria anterior + outras vistorias) --}}
        @if($vistoriaAnterior)
            @php
                $vaParts = [\Carbon\Carbon::parse($vistoriaAnterior->data_abordagem)->format('d/m/Y')];
                if ($vistoriaAnterior->user) { $vaParts[] = $vistoriaAnterior->user->name; }
                if ($vistoriaAnterior->resultadoAcao) { $vaParts[] = $vistoriaAnterior->resultadoAcao->resultado; }
                $vaParts[] = ($vistoriaAnterior->quantidade_pessoas ?? 0).' pessoas';
                if (($vistoriaAnterior->qtd_kg ?? 0) > 0) { $vaParts[] = $vistoriaAnterior->qtd_kg.' kg'; }
            @endphp
            <div class="card mb-3">
                <div class="card-body">
                    <h3 style="font-size: var(--text-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-2);">Vistoria anterior neste ponto</h3>
                    <div style="font-size: var(--text-sm); color: var(--text-muted);">{{ implode(' · ', $vaParts) }}</div>
                </div>
            </div>
        @endif

        @if($historicoPonto->count() > 0)
            <div class="card mb-3">
                <div class="card-body">
                    <h3 style="font-size: var(--text-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-2);">Outras vistorias neste ponto ({{ $historicoPonto->count() }})</h3>
                    <ul class="historico-lista">
                        @foreach($historicoPonto as $v)
                            @php
                                $metaParts = [$v->user->name ?? '—'];
                                if ($v->resultadoAcao) { $metaParts[] = $v->resultadoAcao->resultado; }
                                $dirParts = [($v->quantidade_pessoas ?? 0).' pessoas'];
                                if (($v->qtd_kg ?? 0) > 0) { $dirParts[] = $v->qtd_kg.' kg'; }
                            @endphp
                            <li>
                                <div>
                                    <a href="{{ route('vistorias.show', $v) }}"><span class="data">{{ \Carbon\Carbon::parse($v->data_abordagem)->format('d/m/Y') }}</span></a>
                                    <div class="meta">{{ implode(' · ', $metaParts) }}</div>
                                </div>
                                <div class="meta" style="text-align: right;">{{ implode(' · ', $dirParts) }}</div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Link para o Ponto --}}
        @if($vistoria->ponto)
            <a href="{{ route('pontos.show', $vistoria->ponto->id) }}" class="btn btn-secondary w-full">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Ver Ponto e Outras Vistorias
            </a>
        @endif
    </div>
@endsection

@push('scripts')
@vite('resources/js/vistoria-show.js')
@endpush
