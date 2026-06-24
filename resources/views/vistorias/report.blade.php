@extends('layouts.app')

@section('title', 'Relatório de Zeladoria #' . $vistoria->id)

@php
    $statusZeladoria = $vistoria->cancelada ? 'cancelada' : ($vistoria->finalizada ? 'finalizada' : 'em-andamento');
@endphp

@section('header')
    <a href="{{ route('vistorias.show', $vistoria) }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
        <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <span class="mobile-header-title" style="flex: 1; text-align: center;">Relatório de Zeladoria</span>
    <div style="display: flex; gap: var(--space-1);">
        <a href="{{ route('vistorias.report.print', $vistoria) }}" target="_blank" rel="noopener" class="btn btn-ghost btn-icon" title="Imprimir para processo administrativo (A4)">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
        </a>
    </div>
@endsection

@push('styles')
<style>
    /* Layout enxuto — cards sem moldura, hierarquia por tipografia */
    .relatorio-wrapper {
        max-width: 720px;
        margin: 0 auto;
        padding-bottom: var(--space-6);
    }

    .section-card {
        background: transparent;
        border: none;
        border-radius: 0;
        margin-bottom: var(--space-6);
        overflow: visible;
    }
    .section-card-header {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 0;
        border: none;
        background: transparent;
        margin-bottom: var(--space-2);
    }
    .section-card-header h3 {
        font-size: 11px;
        font-weight: 600;
        color: var(--text-secondary, #a1a9b4);
        text-transform: uppercase;
        letter-spacing: 0.7px;
        margin: 0;
    }
    .section-card-header svg {
        width: 14px; height: 14px;
        color: var(--text-secondary, #a1a9b4);
        opacity: 0.7;
    }
    .section-card-body { padding: 0; }

    .info-row {
        display: grid;
        grid-template-columns: max-content 1fr;
        column-gap: var(--space-4);
        row-gap: 6px;
        align-items: baseline;
    }
    .info-row dt {
        color: var(--text-secondary, #a1a9b4);
        font-size: 14px;
        font-weight: 400;
    }
    .info-row dd {
        margin: 0;
        font-size: 15px;
        font-weight: 500;
        color: var(--text-primary);
    }
    .info-row dd.vazio {
        color: var(--text-secondary, #a1a9b4);
        font-style: italic;
        font-weight: 400;
    }

    .badge-list {
        display: block;
        column-count: 2;
        column-gap: var(--space-4);
    }
    .badge-fator {
        display: block;
        padding: 4px 0;
        background: transparent;
        border: none;
        border-radius: 0;
        font-size: 14px;
        color: var(--text-secondary, #a1a9b4);
        break-inside: avoid;
        -webkit-column-break-inside: avoid;
    }
    .badge-fator.ativo {
        background: transparent;
        border-color: transparent;
        color: var(--text-primary);
        font-weight: 500;
    }
    .badge-fator.ativo::before {
        content: "✓";
        display: inline-block;
        width: 18px;
        color: var(--accent-primary, #2dd4bf);
        font-weight: 600;
    }
    .badge-fator:not(.ativo)::before {
        content: "·";
        display: inline-block;
        width: 18px;
        color: var(--border-secondary, #353940);
    }

    /* Resultado e status: só texto, sem caixa colorida */
    .result-banner {
        padding: 0;
        border-radius: 0;
        text-align: left;
        font-weight: 600;
        margin-bottom: var(--space-2);
        font-size: 17px;
        line-height: 1.4;
        background: transparent !important;
        color: var(--text-primary) !important;
    }
    .result-banner.danger { color: var(--color-danger, #f87171) !important; }
    .result-banner.success { color: var(--color-success, #34d399) !important; }

    .foto-grid-app {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 8px;
    }
    .foto-grid-app a {
        display: block;
        position: relative;
        aspect-ratio: 4/3;
        border-radius: 6px;
        overflow: hidden;
        border: 1px solid var(--border-primary, #282b33);
        background: var(--bg-secondary, #1d1f24);
    }
    .foto-grid-app a.foto-publica {
        outline: 2px solid var(--accent-primary, #2dd4bf);
        outline-offset: -2px;
    }
    .foto-grid-app img {
        width: 100%; height: 100%; object-fit: cover; display: block;
    }
    .foto-badge {
        position: absolute;
        bottom: 6px;
        left: 6px;
        display: inline-flex;
        align-items: center;
        gap: 3px;
        padding: 3px 7px;
        border-radius: 999px;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        backdrop-filter: blur(4px);
    }
    .foto-badge.pub {
        background: var(--accent-primary, #2dd4bf);
        color: var(--text-inverse, #111214);
    }
    .foto-badge.priv {
        background: rgba(17, 18, 20, 0.78);
        color: var(--text-secondary, #a1a9b4);
    }

    .observacao-bloco {
        background: transparent;
        border-left: 2px solid var(--border-secondary, #353940);
        padding: 4px 0 4px var(--space-3);
        border-radius: 0;
        white-space: pre-wrap;
        font-size: 15px;
        color: var(--text-primary);
        line-height: 1.6;
    }

    .historico-banner {
        background: transparent;
        border: none;
        border-top: 1px solid var(--border-secondary, #353940);
        border-bottom: 1px solid var(--border-secondary, #353940);
        border-radius: 0;
        padding: var(--space-3) 0;
        margin-bottom: var(--space-6);
    }
    .historico-banner-title {
        font-weight: 600;
        font-size: 11px;
        color: var(--text-secondary, #a1a9b4);
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: 0.7px;
    }
    .historico-anterior-resumo {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-4);
        margin-top: var(--space-2);
        font-size: 13px;
    }
    .historico-mini {
        background: transparent;
        padding: 0;
        border-radius: 0;
        font-size: 13px;
        color: var(--text-secondary, #a1a9b4);
    }
    .historico-mini .label {
        display: inline;
        color: var(--text-secondary, #a1a9b4);
        text-transform: none;
        letter-spacing: 0;
        font-size: 13px;
    }
    .historico-mini .label::after { content: " "; }
    .historico-mini .valor {
        display: inline;
        font-weight: 500;
        color: var(--text-primary);
    }
    .historico-mini .delta { font-size: 12px; margin-left: 2px; font-variant-numeric: tabular-nums; }
    .historico-mini .delta.up { color: var(--color-danger, #f87171); }
    .historico-mini .delta.down { color: var(--color-success, #34d399); }

    .historico-lista {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .historico-lista li {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: var(--space-3);
        padding: 8px 0;
        border-bottom: 1px solid var(--border-primary, #282b33);
        font-size: 14px;
    }
    .historico-lista li:last-child { border-bottom: none; }
    .historico-lista .data { font-weight: 500; font-variant-numeric: tabular-nums; }
    .historico-lista .meta { color: var(--text-secondary, #a1a9b4); font-size: 13px; }
    .historico-lista a { color: var(--text-primary); text-decoration: none; }
    .historico-lista a:hover { color: var(--accent-primary); }

    @media (max-width: 600px) {
        .info-row { grid-template-columns: 1fr; row-gap: 2px; }
        .info-row dt { font-size: 12px; margin-top: 6px; }
        .badge-list { column-count: 1; }
    }
</style>
@endpush

@section('content')
<div class="page-content relatorio-wrapper">

    @php
        $dataAbordagem = $vistoria->data_abordagem ? \Carbon\Carbon::parse($vistoria->data_abordagem) : null;
        $horaAbordagem = $dataAbordagem?->format('H:i');
        $temHora = $horaAbordagem && $horaAbordagem !== '00:00';

        $ponto = $vistoria->ponto;
        $endAtu = $ponto?->enderecoAtualizado;
        $logradouro = trim(($endAtu->SIGLA_TIPO_LOGRADOURO ?? '') . ' ' . ($endAtu->NOME_LOGRADOURO ?? ''));
        $numero = $endAtu->NUMERO_IMOVEL ?? $ponto?->numero ?? '';
        $bairro = $endAtu->NOME_BAIRRO_OFICIAL ?? null;
        $regional = $endAtu->NOME_REGIONAL ?? null;

        $caracteristicas = [
            ['campo' => 'casal', 'label' => 'Casal', 'extra' => $vistoria->qtd_casais ? "({$vistoria->qtd_casais})" : null],
            ['campo' => 'num_reduzido', 'label' => 'Número reduzido'],
            ['campo' => 'catador_reciclados', 'label' => 'Catador de reciclados'],
            ['campo' => 'resistencia', 'label' => 'Resistência'],
            ['campo' => 'fixacao_antiga', 'label' => 'Fixação antiga'],
            ['campo' => 'excesso_objetos', 'label' => 'Excesso de objetos'],
            ['campo' => 'trafico_ilicitos', 'label' => 'Tráfico / ilícitos'],
            ['campo' => 'crianca_adolescente', 'label' => 'Criança / adolescente'],
            ['campo' => 'idosos', 'label' => 'Idosos'],
            ['campo' => 'gestante', 'label' => 'Gestante'],
            ['campo' => 'lgbtqiapn', 'label' => 'LGBTQIAPN+'],
            ['campo' => 'cena_uso_caracterizada', 'label' => 'Cena de uso caracterizada'],
            ['campo' => 'deficiente', 'label' => 'Deficiente'],
            ['campo' => 'agrupamento_quimico', 'label' => 'Agrupamento químico'],
            ['campo' => 'saude_mental', 'label' => 'Saúde mental'],
            ['campo' => 'animais', 'label' => 'Animais', 'extra' => $vistoria->qtd_animais ? "({$vistoria->qtd_animais})" : null],
        ];

        $encaminhamentos = collect([
            $vistoria->encaminhamento1, $vistoria->encaminhamento2, $vistoria->encaminhamento3,
            $vistoria->encaminhamento4, $vistoria->encaminhamento5, $vistoria->encaminhamento6,
        ])->filter();

        $fotos = $vistoria->getMedia('fotos');

        $badgeRes = match(true) {
            !$vistoria->resultadoAcao => null,
            str_contains($vistoria->resultadoAcao->resultado, 'persiste') => 'danger',
            str_contains($vistoria->resultadoAcao->resultado, 'parcialmente') => 'warning',
            str_contains($vistoria->resultadoAcao->resultado, 'ausente') => 'neutral',
            str_contains($vistoria->resultadoAcao->resultado, 'constatado') => 'info',
            str_contains($vistoria->resultadoAcao->resultado, 'Conformidade') => 'success',
            default => 'neutral',
        };

        $anterior = $vistoriaAnterior ?? null;
        $deltaPessoas = null;
        if ($anterior && filled($anterior->quantidade_pessoas) && filled($vistoria->quantidade_pessoas)) {
            $deltaPessoas = ($vistoria->quantidade_pessoas - $anterior->quantidade_pessoas);
        }
    @endphp

    {{-- STATUS DA ZELADORIA --}}
    @if($vistoria->cancelada)
        <div class="result-banner danger">
            ⊘ Zeladoria cancelada
            @if($vistoria->cancelada_em) em {{ \Carbon\Carbon::parse($vistoria->cancelada_em)->format('d/m/Y H:i') }}@endif
        </div>
    @elseif($vistoria->finalizada)
        <div class="result-banner success">
            ✓ Zeladoria finalizada
            @if($vistoria->finalizada_em) em {{ \Carbon\Carbon::parse($vistoria->finalizada_em)->format('d/m/Y H:i') }}@endif
        </div>
    @endif

    {{-- HISTÓRICO DO PONTO — banner topo --}}
    @if($anterior)
        <div class="historico-banner">
            <div class="historico-banner-title">
                ⓘ Vistoria anterior neste ponto — {{ \Carbon\Carbon::parse($anterior->data_abordagem)->diffForHumans() }}
            </div>
            <div style="font-size: var(--text-sm); color: #78350f;">
                {{ \Carbon\Carbon::parse($anterior->data_abordagem)->format('d/m/Y') }}
                @if($anterior->user)
                    por <strong>{{ $anterior->user->name }}</strong>
                @endif
                @if($anterior->resultadoAcao)
                    — resultado: <em>{{ $anterior->resultadoAcao->resultado }}</em>
                @endif
            </div>
            <div class="historico-anterior-resumo">
                <div class="historico-mini">
                    <div class="label">Pessoas (antes)</div>
                    <div class="valor">
                        {{ $anterior->quantidade_pessoas ?? 0 }}
                        @if($deltaPessoas !== null && $deltaPessoas !== 0)
                            <span class="delta {{ $deltaPessoas > 0 ? 'up' : 'down' }}">
                                {{ $deltaPessoas > 0 ? '+' : '' }}{{ $deltaPessoas }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="historico-mini">
                    <div class="label">Material recolhido</div>
                    <div class="valor">{{ $anterior->qtd_kg ?? 0 }} kg</div>
                </div>
                <div class="historico-mini">
                    <div class="label">Abrigos provisórios</div>
                    <div class="valor">{{ $anterior->qtd_abrigos_provisorios ?? 0 }}</div>
                </div>
                <div class="historico-mini">
                    <div class="label">Detalhes</div>
                    <div class="valor"><a href="{{ route('vistorias.show', $anterior) }}">Abrir #{{ $anterior->id }}</a></div>
                </div>
            </div>
            @if(filled($anterior->observacao))
                <details style="margin-top: var(--space-2); font-size: var(--text-xs, 12px);">
                    <summary style="cursor:pointer; color:#78350f;">Observação registrada na vistoria anterior</summary>
                    <div style="background: rgba(255,255,255,0.7); padding: 8px; border-radius: 4px; margin-top: 4px; white-space: pre-wrap;">{{ $anterior->observacao }}</div>
                </details>
            @endif
        </div>
    @endif

    {{-- RESULTADO --}}
    @if($vistoria->resultadoAcao)
        <div class="result-banner {{ $badgeRes }}">
            {{ $vistoria->resultadoAcao->resultado }}
        </div>
    @endif

    {{-- 1. DADOS DA VISTORIA --}}
    <div class="section-card">
        <div class="section-card-header">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <h3>Dados da vistoria</h3>
        </div>
        <div class="section-card-body">
            <dl class="info-row">
                <dt>ID</dt><dd>#{{ $vistoria->id }}</dd>
                <dt>{{ $temHora ? 'Data e hora' : 'Data' }}</dt>
                <dd>{{ $dataAbordagem ? $dataAbordagem->format('d/m/Y') . ($temHora ? ' '.$horaAbordagem : '') : '—' }}</dd>
                <dt>Tipo de abordagem</dt><dd>{{ $vistoria->tipoAbordagem->tipo ?? '—' }}</dd>
                @if(filled($vistoria->data_prevista_zeladoria))
                    <dt>Data prevista</dt><dd>{{ \Carbon\Carbon::parse($vistoria->data_prevista_zeladoria)->format('d/m/Y H:i') }}</dd>
                @endif
                @if(filled($vistoria->periodo_zeladoria))
                    <dt>Período</dt><dd>{{ $vistoria->periodo_zeladoria }}</dd>
                @endif
                <dt>Registrada por</dt><dd>{{ $vistoria->user->name ?? '—' }}</dd>
                @if($vistoria->relationLoaded('participantes') && $vistoria->participantes->count() > 0)
                    <dt>Equipe ({{ $vistoria->participantes->count() }})</dt>
                    <dd>{{ $vistoria->participantes->map(fn($p) => $p->name ?? ('#'.$p->id))->join(', ') }}</dd>
                @endif
                <dt>Pessoas abordadas</dt><dd>{{ $vistoria->quantidade_pessoas ?? 0 }}</dd>
                <dt>Material recolhido</dt><dd>{{ $vistoria->qtd_kg ?? 0 }} kg</dd>
                @if(filled($vistoria->movimento_migratorio))
                    <dt>Movimento migratório</dt><dd>{{ $vistoria->movimento_migratorio }}</dd>
                @endif
            </dl>
        </div>
    </div>

    {{-- 2. LOCALIZAÇÃO --}}
    <div class="section-card">
        <div class="section-card-header">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <h3>Localização</h3>
        </div>
        <div class="section-card-body">
            <dl class="info-row">
                <dt>Endereço</dt>
                @if(filled($logradouro) || filled($numero))
                    <dd>{{ trim($logradouro) }}{{ filled($numero) ? ', '.$numero : '' }}</dd>
                @else
                    <dd class="vazio">não informado</dd>
                @endif
                <dt>Bairro</dt><dd>{{ $bairro ?? '—' }}</dd>
                <dt>Regional</dt><dd>{{ $regional ?? '—' }}</dd>
                @if($ponto?->lat && $ponto?->lng)
                    <dt>Coordenadas</dt><dd>{{ $ponto->lat }}, {{ $ponto->lng }}</dd>
                @endif
                <dt>Ponto</dt><dd>{{ $ponto?->id ? '#'.$ponto->id : '—' }}</dd>
            </dl>
        </div>
    </div>

    {{-- 3. FATORES DE COMPLEXIDADE --}}
    <div class="section-card">
        <div class="section-card-header">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <h3>Fatores de complexidade observados</h3>
        </div>
        <div class="section-card-body">
            <div class="badge-list">
                @foreach($caracteristicas as $c)
                    @php $marcado = (bool) $vistoria->{$c['campo']}; @endphp
                    <span class="badge-fator {{ $marcado ? 'ativo' : '' }}">
                        {{ $marcado ? '✓ ' : '' }}{{ $c['label'] }}@if(!empty($c['extra'])) {{ $c['extra'] }} @endif
                    </span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- 4. AÇÕES E MATERIAIS --}}
    @if($vistoria->conducao_forcas_seguranca || $vistoria->apreensao_fiscal || $vistoria->auto_fiscalizacao_aplicado || filled($vistoria->material_apreendido) || filled($vistoria->material_descartado))
    <div class="section-card">
        <div class="section-card-header">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
            <h3>Ações fiscalizatórias e materiais</h3>
        </div>
        <div class="section-card-body">
            <dl class="info-row">
                <dt>Condução por forças de segurança</dt>
                <dd>{{ $vistoria->conducao_forcas_seguranca ? 'Sim' : 'Não' }}@if($vistoria->conducao_forcas_seguranca && filled($vistoria->conducao_forcas_observacao)) — {{ $vistoria->conducao_forcas_observacao }}@endif</dd>
                <dt>Recolhimento de Inservíveis</dt><dd>{{ $vistoria->apreensao_fiscal ? 'Sim' : 'Não' }}</dd>
                <dt>Relatório de Orientação</dt>
                <dd>{{ $vistoria->auto_fiscalizacao_aplicado ? 'Aplicado' : 'Não aplicado' }}@if($vistoria->auto_fiscalizacao_aplicado && filled($vistoria->auto_fiscalizacao_numero)) — nº {{ $vistoria->auto_fiscalizacao_numero }}@endif</dd>
                @if(filled($vistoria->material_apreendido))
                    <dt>Material apreendido</dt><dd>{{ $vistoria->material_apreendido }}</dd>
                @endif
                @if(filled($vistoria->material_descartado))
                    <dt>Material descartado</dt><dd>{{ $vistoria->material_descartado }}</dd>
                @endif
            </dl>
        </div>
    </div>
    @endif

    {{-- 5. ENCAMINHAMENTOS --}}
    @if($encaminhamentos->count() > 0)
    <div class="section-card">
        <div class="section-card-header">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
            </svg>
            <h3>Encaminhamentos ({{ $encaminhamentos->count() }})</h3>
        </div>
        <div class="section-card-body">
            <ol style="margin-left: 20px;">
                @foreach($encaminhamentos as $enc)
                    <li>{{ $enc->encaminhamento }}</li>
                @endforeach
            </ol>
        </div>
    </div>
    @endif

    {{-- 6. PESSOAS --}}
    @if(filled($vistoria->nomes_pessoas) || $vistoria->moradoresEntrada->count() > 0)
    <div class="section-card">
        <div class="section-card-header">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <h3>Pessoas identificadas</h3>
        </div>
        <div class="section-card-body">
            @if(filled($vistoria->nomes_pessoas))
                <div style="margin-bottom: var(--space-3);">
                    <div style="font-size: var(--text-sm); color: var(--text-muted); margin-bottom: 4px;">Nomes citados</div>
                    <div class="observacao-bloco">{{ $vistoria->nomes_pessoas }}</div>
                </div>
            @endif
            @if($vistoria->moradoresEntrada->count() > 0)
                <div style="font-size: var(--text-sm); color: var(--text-muted); margin-bottom: 4px;">Moradores cadastrados ({{ $vistoria->moradoresEntrada->count() }})</div>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    @foreach($vistoria->moradoresEntrada as $hist)
                        @if($hist->morador)
                            <li style="padding: 6px 0; border-bottom: 1px solid var(--border-color, #e5e7eb);">
                                <a href="{{ route('moradores.show', $hist->morador) }}" style="color: var(--accent-primary); text-decoration: none;">
                                    {{ $hist->morador->nome_social }}
                                    @if(filled($hist->morador->apelido))
                                        <span class="text-muted">— "{{ $hist->morador->apelido }}"</span>
                                    @endif
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
    @endif

    {{-- 7. OBSERVAÇÕES --}}
    @if(filled($vistoria->observacao))
    <div class="section-card">
        <div class="section-card-header">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
            </svg>
            <h3>Observações</h3>
        </div>
        <div class="section-card-body">
            <div class="observacao-bloco">{{ $vistoria->observacao }}</div>
        </div>
    </div>
    @endif

    {{-- 8. FOTOS --}}
    @if($fotos->count() > 0)
    @php $fotosPublicas = $fotos->filter(fn($m) => (bool) $m->getCustomProperty('publica', false))->count(); @endphp
    <div class="section-card">
        <div class="section-card-header">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <h3>Registro fotográfico ({{ $fotos->count() }}{{ $fotosPublicas > 0 ? " · {$fotosPublicas} no processo" : '' }})</h3>
        </div>
        <div class="section-card-body">
            <div class="foto-grid-app">
                @foreach($fotos as $foto)
                    @php $pub = (bool) $foto->getCustomProperty('publica', false); @endphp
                    <a href="{{ $foto->getUrl() }}" target="_blank" rel="noopener" class="foto-wrap{{ $pub ? ' foto-publica' : '' }}" title="{{ $pub ? 'Pública — aparece no relatório do processo' : 'Privada — não aparece no relatório do processo' }}">
                        <img src="{{ $foto->getUrl('preview') }}" alt="Foto da vistoria" loading="lazy">
                        <span class="foto-badge {{ $pub ? 'pub' : 'priv' }}">
                            @if($pub)
                                <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                pública
                            @else
                                <svg style="width:12px;height:12px;" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="9" rx="1.5" stroke-linecap="round" stroke-linejoin="round"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 1 1 8 0v4"/></svg>
                                privada
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
            @if($fotosPublicas === 0)
                <p style="margin-top: var(--space-3); font-size: 13px; color: var(--text-secondary, #a1a9b4);">
                    Nenhuma foto marcada como pública — para incluir fotos no relatório do processo, abra a vistoria em <strong>Editar</strong> e clique no cadeado de cada foto.
                </p>
            @endif
        </div>
    </div>
    @endif

    {{-- HISTÓRICO COMPLETO DO PONTO --}}
    @if(isset($historicoPonto) && $historicoPonto->count() > 0)
    <div class="section-card">
        <div class="section-card-header">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <h3>Outras vistorias neste ponto ({{ $historicoPonto->count() }})</h3>
        </div>
        <div class="section-card-body">
            <ul class="historico-lista">
                @foreach($historicoPonto as $v)
                    <li>
                        <div>
                            <a href="{{ route('vistorias.show', $v) }}">
                                <span class="data">{{ \Carbon\Carbon::parse($v->data_abordagem)->format('d/m/Y') }}</span>
                            </a>
                            <div class="meta">
                                {{ $v->user->name ?? '—' }}
                                @if($v->resultadoAcao)
                                    · {{ $v->resultadoAcao->resultado }}
                                @endif
                            </div>
                        </div>
                        <div class="meta" style="text-align: right;">
                            {{ $v->quantidade_pessoas ?? 0 }} pessoas
                            @if(($v->qtd_kg ?? 0) > 0)
                                · {{ $v->qtd_kg }} kg
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
    @endif

    {{-- Botão de imprimir fixo no rodapé pra mobile --}}
    <div style="text-align: center; margin-top: var(--space-4);">
        <a href="{{ route('vistorias.report.print', $vistoria) }}" target="_blank" rel="noopener" class="btn btn-primary">
            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
            Imprimir versão A4 para processo administrativo
        </a>
    </div>

</div>
@endsection
