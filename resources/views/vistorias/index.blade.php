@extends('layouts.app')

@php $isMinhas = request()->boolean('_minhas'); @endphp

@section('title', $isMinhas ? 'Minhas Zeladorias' : 'Zeladorias')

@push('styles')
<style>
    details.card summary::-webkit-details-marker { display: none; }
    details.card[open] .details-chevron { transform: rotate(180deg); }
    .vistorias-table td { vertical-align: middle; }
    .vistorias-table .row-actions { display: inline-flex; gap: 2px; justify-content: center; }
</style>
@endpush

@section('header')
    <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
        <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <span class="mobile-header-title" style="flex: 1; text-align: center;">{{ $isMinhas ? 'Minhas Zeladorias' : 'Zeladorias' }}</span>
    <a href="{{ route('mapa.index', ['nova_vistoria' => 1]) }}" class="btn btn-ghost btn-icon" title="Nova vistoria">
        <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
    </a>
@endsection

@section('content')
    <div class="page-content">
        {{-- Mensagens --}}
        @if(session('success'))
            <div class="alert alert-success mb-4">
                <div class="alert-content">
                    <p class="alert-message">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        {{-- Busca por Endereco --}}
        <div class="card mb-4" style="overflow: visible;">
            <div class="card-body">
                <form method="GET" action="{{ route('vistorias.index') }}" id="form-busca-endereco">
                    @if(request('bairro'))<input type="hidden" name="bairro" value="{{ request('bairro') }}">@endif
                    @if(request('regional'))<input type="hidden" name="regional" value="{{ request('regional') }}">@endif
                    @if(request('resultado'))<input type="hidden" name="resultado" value="{{ request('resultado') }}">@endif
                    @if(request('data_inicio'))<input type="hidden" name="data_inicio" value="{{ request('data_inicio') }}">@endif
                    @if(request('data_fim'))<input type="hidden" name="data_fim" value="{{ request('data_fim') }}">@endif
                    @if(request('logradouro'))<input type="hidden" name="logradouro" value="{{ request('logradouro') }}">@endif
                    @if(request('numero'))<input type="hidden" name="numero" value="{{ request('numero') }}">@endif
                    @if(request('tipo_abordagem'))<input type="hidden" name="tipo_abordagem" value="{{ request('tipo_abordagem') }}">@endif
                    @if(request('situacao_comunicado'))<input type="hidden" name="situacao_comunicado" value="{{ request('situacao_comunicado') }}">@endif
                    @if(request('retorno_previsto'))<input type="hidden" name="retorno_previsto" value="{{ request('retorno_previsto') }}">@endif
                    <input type="hidden" name="endereco" id="hidden-endereco" value="{{ request('endereco') }}">
                    <input type="hidden" name="numero_endereco" id="hidden-numero-endereco" value="{{ request('numero_endereco') }}">
                    <div class="autocomplete-container">
                        <svg style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-muted); pointer-events: none; z-index: 2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input
                            type="text"
                            id="search-endereco"
                            placeholder="Buscar endereco..."
                            autocomplete="off"
                            class="form-input"
                            style="padding-left: 38px;"
                            value="{{ request('endereco') ? (request('numero_endereco') ? request('endereco') . ', ' . request('numero_endereco') : request('endereco')) : '' }}"
                        >
                        <div id="search-results" class="autocomplete-results" style="display: none;"></div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Busca Avancada --}}
        @php
            $temFiltroAvancado = request('logradouro') || request('numero') || request('bairro') || request('regional') || request('resultado') || request('data_inicio') || request('data_fim') || request('tipo_abordagem') || request('situacao_comunicado') || request('retorno_previsto');
        @endphp
        <details class="card mb-4" {{ $temFiltroAvancado ? 'open' : '' }}>
            <summary class="card-header" style="cursor: pointer; display: flex; align-items: center; justify-content: space-between; user-select: none; list-style: none;">
                <span style="display: flex; align-items: center; gap: var(--space-2); font-weight: var(--font-medium); font-size: var(--text-sm);">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Busca Avancada
                    @if($temFiltroAvancado)
                        <span class="badge badge-info" style="font-size: var(--text-xs);">Ativo</span>
                    @endif
                </span>
                <svg class="details-chevron" style="width: 16px; height: 16px; transition: transform 0.2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </summary>
            <div class="card-body">
                <form method="GET" action="{{ route('vistorias.index') }}" style="display: flex; flex-direction: column; gap: var(--space-3);">
                    @if(request('endereco'))<input type="hidden" name="endereco" value="{{ request('endereco') }}">@endif
                    @if(request('numero_endereco'))<input type="hidden" name="numero_endereco" value="{{ request('numero_endereco') }}">@endif
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label class="form-label">Logradouro</label>
                            <input type="text" name="logradouro" value="{{ request('logradouro') }}" placeholder="Ex: AFONSO PENA" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Numero</label>
                            <input type="text" name="numero" value="{{ request('numero') }}" placeholder="Ex: 1000" class="form-input">
                        </div>
                    </div>
                    <div class="form-row form-row-3">
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
                    <div class="form-row form-row-3">
                        <div class="form-group">
                            <label class="form-label">Supervisor</label>
                            <select name="supervisor" class="form-input form-select">
                                <option value="">Todos</option>
                                @foreach($supervisores as $sup)
                                    <option value="{{ $sup->id }}" {{ request('supervisor') == $sup->id ? 'selected' : '' }}>
                                        {{ $sup->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Data Inicio</label>
                            <input type="date" name="data_inicio" value="{{ request('data_inicio') }}" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Data Fim</label>
                            <input type="date" name="data_fim" value="{{ request('data_fim') }}" class="form-input">
                        </div>
                    </div>
                    <div class="form-row form-row-2">
                        <div class="form-group">
                            <label class="form-label">Data Prevista Retorno (Inicio)</label>
                            <input type="date" name="data_prevista_inicio" value="{{ request('data_prevista_inicio') }}" class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Data Prevista Retorno (Fim)</label>
                            <input type="date" name="data_prevista_fim" value="{{ request('data_prevista_fim') }}" class="form-input">
                        </div>
                    </div>
                    <div class="form-row form-row-3">
                        <div class="form-group">
                            <label class="form-label">Tipo de Abordagem</label>
                            <select name="tipo_abordagem" class="form-input form-select">
                                <option value="">Todos</option>
                                @foreach($tiposAbordagem as $tipo)
                                    <option value="{{ $tipo->id }}" {{ request('tipo_abordagem') == $tipo->id ? 'selected' : '' }}>
                                        {{ $tipo->tipo }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Situacao Comunicado</label>
                            <select name="situacao_comunicado" class="form-input form-select">
                                <option value="">Todos</option>
                                <option value="com_comunicado" {{ request('situacao_comunicado') == 'com_comunicado' ? 'selected' : '' }}>Com comunicado</option>
                                <option value="sem_comunicado" {{ request('situacao_comunicado') == 'sem_comunicado' ? 'selected' : '' }}>Sem comunicado</option>
                                <option value="aguardando_retorno" {{ request('situacao_comunicado') == 'aguardando_retorno' ? 'selected' : '' }}>Aguardando retorno</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Retorno Previsto</label>
                            <select name="retorno_previsto" class="form-input form-select">
                                <option value="">Todos</option>
                                <option value="vencidos" {{ request('retorno_previsto') == 'vencidos' ? 'selected' : '' }}>Vencidos</option>
                                <option value="proximos_7" {{ request('retorno_previsto') == 'proximos_7' ? 'selected' : '' }}>Proximos 7 dias</option>
                                <option value="proximos_30" {{ request('retorno_previsto') == 'proximos_30' ? 'selected' : '' }}>Proximos 30 dias</option>
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
                        <a href="{{ route('vistorias.index') }}" class="btn btn-secondary">Limpar</a>
                    </div>
                    @if(request('data_prevista_inicio'))
                        <div style="margin-top: var(--space-2); display: flex; gap: var(--space-2);">
                            <a href="{{ route('vistorias.roteiro', request()->only(['data_prevista_inicio', 'data_prevista_fim', 'supervisor', 'regional'])) }}"
                               target="_blank" class="btn btn-secondary" style="flex: 1;">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                </svg>
                                Imprimir Roteiro
                            </a>
                            <a href="{{ route('vistorias.roteiro', array_merge(request()->only(['data_prevista_inicio', 'data_prevista_fim', 'supervisor', 'regional']), ['format' => 'pdf'])) }}"
                               class="btn btn-primary" style="flex: 1;">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                Exportar PDF
                            </a>
                        </div>
                    @endif
                </form>
            </div>
        </details>

        {{-- Tabela --}}
        <div class="table-container">
            <table class="table table-striped vistorias-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Endereço</th>
                        <th class="hide-mobile">Tipo</th>
                        <th>Situação</th>
                        <th class="hide-mobile">Resultado</th>
                        <th class="hide-mobile">Retorno</th>
                        <th class="hide-mobile text-center">Pessoas</th>
                        <th class="text-center">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($vistorias as $vistoria)
                        @php
                            $dataAbordagem = \Carbon\Carbon::parse($vistoria->data_abordagem);
                            $isAdmin = auth()->user()->hasRole('admin');
                            $isAberta = !$vistoria->finalizada && !$vistoria->cancelada;
                            $isOwner = $vistoria->user_id == auth()->id();
                            $podeEditar = $isAdmin || $isOwner;
                            $dp = $vistoria->data_prevista_zeladoria ? \Carbon\Carbon::parse($vistoria->data_prevista_zeladoria) : null;
                            $dias = $dp ? now()->startOfDay()->diffInDays($dp, false) : null;
                            $diasPrecaria = \App\Models\Parametro::get('info_precaria_dias', 60);
                            $ultimaVistoriaPonto = isset($vistoria->ultima_vistoria_ponto) ? \Carbon\Carbon::parse($vistoria->ultima_vistoria_ponto) : null;
                            $infoPrecaria = !$ultimaVistoriaPonto || $ultimaVistoriaPonto->diffInDays(now()) > $diasPrecaria;

                            // Fase semantica do workflow (badge na coluna Situacao)
                            if ($vistoria->cancelada) {
                                $fase = 'Cancelada'; $faseBadge = 'badge-danger';
                            } elseif ($vistoria->finalizada && $infoPrecaria) {
                                $fase = 'Informação Precária'; $faseBadge = 'badge-warning';
                            } elseif ($vistoria->finalizada) {
                                $fase = 'Concluída'; $faseBadge = 'badge-success';
                            } elseif ($infoPrecaria && $isAberta) {
                                $fase = 'Atualizar Informação'; $faseBadge = 'badge-info';
                            } elseif ($dp && $dias !== null && $dias < 0) {
                                $fase = 'Retorno Vencido'; $faseBadge = 'badge-danger';
                            } elseif ($dp) {
                                $fase = 'Retorno Agendado'; $faseBadge = 'badge-accent';
                            } else {
                                $fase = 'Aguardando Retorno'; $faseBadge = 'badge-secondary';
                            }

                            $resultBadge = match(true) {
                                !$vistoria->resultado_acao => 'badge-accent',
                                str_contains($vistoria->resultado_acao, 'persiste') => 'badge-danger',
                                str_contains($vistoria->resultado_acao, 'parcialmente') => 'badge-warning',
                                str_contains($vistoria->resultado_acao, 'ausente') => 'badge-default',
                                str_contains($vistoria->resultado_acao, 'constatado') => 'badge-info',
                                str_contains($vistoria->resultado_acao, 'Conformidade') => 'badge-success',
                                default => 'badge-secondary',
                            };
                            $tipoBadge = $vistoria->tipo_abordagem ? match(true) {
                                str_contains(mb_strtolower($vistoria->tipo_abordagem), 'comunica') => 'badge-info',
                                str_contains(mb_strtolower($vistoria->tipo_abordagem), 'fiscal') => 'badge-danger',
                                str_contains(mb_strtolower($vistoria->tipo_abordagem), 'zeladoria') => 'badge-success',
                                default => 'badge-secondary',
                            } : '';
                        @endphp
                        <tr>
                            <td style="white-space: nowrap;">
                                <span style="font-weight: var(--font-semibold);">{{ $dataAbordagem->format('d/m/Y') }}</span>
                                @if($dataAbordagem->format('H:i') !== '00:00')
                                <span class="text-muted" style="font-size: var(--text-xs); display: block;">{{ $dataAbordagem->format('H:i') }}</span>
                                @endif
                            </td>
                            <td style="min-width: 220px;">
                                <a href="{{ route('vistorias.show', $vistoria->id) }}" style="text-decoration: none; color: inherit; display: block;">
                                    <span style="font-weight: var(--font-medium);">@if($vistoria->tipo){{ $vistoria->tipo }} @endif{{ $vistoria->logradouro }}{{ $vistoria->numero ? ', ' . $vistoria->numero : '' }}</span>
                                    @if($vistoria->complemento)<span class="text-muted"> · {{ $vistoria->complemento }}</span>@endif
                                    <span class="text-muted" style="font-size: var(--text-xs); display: block;">{{ $vistoria->bairro }} · {{ $vistoria->regional ?? 'N/A' }}</span>
                                </a>
                            </td>
                            <td class="hide-mobile">
                                @if($vistoria->tipo_abordagem)
                                    <span class="badge {{ $tipoBadge }}">{{ $vistoria->tipo_abordagem }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td style="white-space: nowrap;">
                                <span class="badge {{ $faseBadge }}">{{ $fase }}</span>
                                @if($dp && $isAberta)
                                    <span class="text-muted" style="font-size: var(--text-xs); display: block;{{ $dias !== null && $dias < 0 ? ' color: var(--color-danger);' : '' }}">
                                        {{ $dp->format('d/m') }}{{ $dias !== null && $dias < 0 ? ' · vencida' : '' }}
                                    </span>
                                @endif
                            </td>
                            <td class="hide-mobile">
                                <span class="badge {{ $resultBadge }}">{{ $vistoria->resultado_acao ?: 'Sem resultado' }}</span>
                            </td>
                            <td class="hide-mobile" style="white-space: nowrap;">
                                @if($dp)
                                    <span @if($dias !== null && $dias < 0) style="color: var(--color-danger); font-weight: var(--font-medium);" @endif>{{ $dp->format('d/m/Y') }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="hide-mobile text-center">{{ $vistoria->quantidade_pessoas ?: '—' }}</td>
                            <td class="text-center">
                                <div class="row-actions">
                                    @if($vistoria->lat && $vistoria->lng)
                                        <a href="{{ route('mapa.index', ['lat' => $vistoria->lat, 'lng' => $vistoria->lng, 'zoom' => 19, 'ponto_id' => $vistoria->ponto_id, 'ajustar' => 1]) }}" class="btn btn-ghost btn-sm btn-icon" title="Mapa">
                                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                                        </a>
                                    @endif
                                    @if($podeEditar)
                                        <a href="{{ route('vistorias.edit', $vistoria->id) }}" class="btn btn-ghost btn-sm btn-icon" title="Editar">
                                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        </a>
                                    @endif
                                    <a href="{{ route('vistorias.show', $vistoria->id) }}" class="btn btn-ghost btn-sm btn-icon" title="Detalhes">
                                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    </a>
                                    <a href="{{ route('vistorias.report', $vistoria->id) }}" class="btn btn-ghost btn-sm btn-icon hide-mobile" title="Relatório">
                                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <p class="empty-state-title">Nenhuma zeladoria encontrada</p>
                                    <p class="empty-state-description">Ajuste os filtros ou crie uma nova zeladoria.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Paginacao --}}
        <x-pagination-bar :paginator="$vistorias->withQueryString()" label="vistorias" />
    </div>
@endsection

@push('scripts')
@vite('resources/js/vistoria-index.js')
@endpush
