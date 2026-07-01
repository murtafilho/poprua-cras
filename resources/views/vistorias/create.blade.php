@extends('layouts.app')

@section('title', 'Nova Zeladoria')

@section('header')
    <div class="flex items-center gap-3 flex-1">
        <a href="{{ route('mapa.index') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title flex-1 text-center">Nova Vistoria</span>
        <button type="button" id="btn-salvar-rascunho" class="btn btn-ghost btn-sm" style="font-size: var(--text-xs); white-space: nowrap;">
            Salvar rascunho
        </button>
    </div>
    <div id="rascunho-status" class="text-muted" style="font-size: var(--text-xs); text-align: center; padding: 0 var(--space-3) var(--space-1); display: none;"></div>
@endsection

@section('content')
    <div class="form-page">
        <form id="vistoria-form" action="{{ route('vistorias.store') }}" method="POST" enctype="multipart/form-data" class="form-container" novalidate x-data="{}" x-cloak>
            @csrf
            <input type="hidden" name="lat" value="{{ $lat }}">
            <input type="hidden" name="lng" value="{{ $lng }}">
            @if($pontoProximo)
                <input type="hidden" name="ponto_id" value="{{ $pontoProximo->id }}">
            @endif

            <!-- Progress Stepper -->
            <div class="progress-stepper" id="progress-stepper" role="tablist" aria-label="Etapas da vistoria">
                <div class="stepper-item active" data-step="0" role="tab" tabindex="0">
                    <div class="stepper-circle">1</div>
                    <span class="stepper-label">Dados</span>
                </div>
                <div class="stepper-item" data-step="1" role="tab" tabindex="0">
                    <div class="stepper-circle">2</div>
                    <span class="stepper-label">Caract.</span>
                </div>
                <div class="stepper-item" data-step="2" role="tab" tabindex="0">
                    <div class="stepper-circle">3</div>
                    <span class="stepper-label">Relatorio</span>
                </div>
                <div class="stepper-item" data-step="3" role="tab" tabindex="0">
                    <div class="stepper-circle">4</div>
                    <span class="stepper-label">Encam.</span>
                </div>
                <div class="stepper-item" data-step="4" role="tab" tabindex="0">
                    <div class="stepper-circle">5</div>
                    <span class="stepper-label">Pessoas</span>
                </div>
                <div class="stepper-item" data-step="5" role="tab" tabindex="0">
                    <div class="stepper-circle">6</div>
                    <span class="stepper-label">Fotos</span>
                </div>
                <div class="stepper-item" data-step="6" role="tab" tabindex="0">
                    <div class="stepper-circle">7</div>
                    <span class="stepper-label">Revisar</span>
                </div>
            </div>
            <div class="step-indicator">
                <span id="step-indicator">Etapa <span class="step-indicator-text">1</span> de <span class="step-indicator-text">7</span></span>
            </div>

            <!-- Conteudo das Abas -->
            <div class="form-content">
                <!-- Aba 1: Dados Basicos -->
                <div class="tab-content" data-tab="0">
                    <!-- Localizacao -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h3 class="form-section-title">Localizacao</h3>
                            @if($pontoProximo)
                                <p class="text-success" style="font-size: var(--text-sm);">
                                    <span style="font-weight: var(--font-medium);">Ponto existente:</span>
                                    {{ $pontoProximo->enderecoAtualizado->tipo ?? '' }} {{ $pontoProximo->enderecoAtualizado->logradouro ?? '' }}, {{ $pontoProximo->enderecoAtualizado->numero ?? $pontoProximo->numero }} - {{ $pontoProximo->enderecoAtualizado->bairro ?? '' }}
                                </p>
                            @else
                                <div>
                                    <p class="text-warning" style="font-size: var(--text-sm); font-weight: var(--font-medium);">
                                        Novo ponto sera criado
                                    </p>
                                    @if($enderecoReferencia)
                                        <p class="text-secondary" style="font-size: var(--text-sm); margin-top: var(--space-1);">
                                            <span style="font-weight: var(--font-medium);">Referencia:</span>
                                            {{ $enderecoReferencia['tipo'] }} {{ $enderecoReferencia['logradouro'] }}, {{ $enderecoReferencia['numero'] }}
                                        </p>
                                        <p class="text-muted" style="font-size: var(--text-xs);">
                                            {{ $enderecoReferencia['bairro'] }} - {{ $enderecoReferencia['regional'] }}
                                        </p>
                                    @endif
                                </div>
                            @endif
                            <p class="text-muted" style="font-size: var(--text-xs); margin-top: var(--space-1);">
                                Lat: {{ number_format($lat, 6) }} | Lng: {{ number_format($lng, 6) }}
                            </p>

                            <div class="form-group mt-3">
                                <label class="form-label">Referencia do Endereco</label>
                                <input type="text" name="complemento_ponto"
                                       value="{{ $pontoProximo->complemento ?? $referenciaAutomatica ?? '' }}"
                                       placeholder="Ex: Proximo ao mercado, em frente a escola..."
                                       class="form-input">
                                <p class="form-hint">Descricao do local para facilitar a identificacao</p>
                            </div>
                        </div>
                    </div>

                    <!-- Dados da Zeladoria -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h3 class="form-section-title">Dados da Zeladoria</h3>

                            <div class="form-group">
                                <label class="form-label required">Data/Hora da Abordagem</label>
                                <input type="datetime-local" name="data_abordagem" value="{{ date('Y-m-d\TH:i') }}" required class="form-input js-date-ptbr">
                            </div>

                            <div class="form-group">
                                <label class="form-label required">Tipo de Abordagem</label>
                                <select name="tipo_abordagem_id" id="tipo_abordagem_id" required class="form-input form-select" x-on:change="toggleZeladoriaCampos()">
                                    <option value="">Selecione...</option>
                                    @foreach($tiposAbordagem as $tipo)
                                        <option value="{{ $tipo->id }}" data-tipo="{{ $tipo->tipo }}">{{ $tipo->tipo }}</option>
                                    @endforeach
                                </select>
                            </div>

                        </div>
                    </div>

                    <!-- Participantes da Equipe (usuários do sistema) -->
                    @if(isset($usuariosEquipe) && $usuariosEquipe->count() > 0)
                        <div class="card mb-4">
                            <div class="card-body">
                                <h3 class="form-section-title">Participantes</h3>
                                <p class="form-hint" style="margin-bottom: var(--space-3);">
                                    Marque os colegas que participaram desta vistoria.
                                    Os pré-selecionados vêm da sua <a href="{{ route('minha-equipe.index') }}">Minha Equipe</a>.
                                </p>

                                <div class="flex flex-col gap-1">
                                    @include('vistorias.partials.participantes-checklist', [
                                        'usuariosEquipe' => $usuariosEquipe,
                                        'participantesSelecionados' => old('participantes', $participantesPreSelecionados ?? []),
                                    ])
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Aba 2: Perfil da Ocorrencia -->
                <div class="tab-content hidden" data-tab="1">
                    <!-- Abrigos -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h3 class="form-section-title">Abrigos</h3>

                            <div class="form-group">
                                <label class="form-label">Qtd. Abrigos Provisorios</label>
                                <input type="number" name="qtd_abrigos_provisorios" id="qtd_abrigos" min="0" placeholder="0" x-on:change="atualizarCamposAbrigos()" class="form-input">
                            </div>

                            <div id="abrigos-container" class="hidden">
                                <label class="form-label">Tipos de Abrigo Desmontado</label>
                                <div id="abrigos-list" class="flex flex-col gap-2"></div>
                            </div>

                            <div id="tipo-abrigo-unico" class="form-group">
                                <label class="form-label">Tipo de Abrigo Desmontado</label>
                                <select name="tipo_abrigo_desmontado_id" class="form-input form-select">
                                    <option value="">Nenhum</option>
                                    @foreach($tiposAbrigo as $tipo)
                                        <option value="{{ $tipo->id }}">{{ $tipo->tipo_abrigo }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Fatores de Complexidade -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h3 class="form-section-title">Fatores de Complexidade</h3>
                            <div class="checkbox-grid">
                                <label class="checkbox-card">
                                    <input type="checkbox" name="resistencia" value="1" class="form-checkbox">
                                    <svg class="checkbox-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                    </svg>
                                    <span>Resistencia</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="num_reduzido" value="1" class="form-checkbox">
                                    <svg class="checkbox-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                    <span>Num. Reduzido</span>
                                </label>
                                <label class="checkbox-card checkbox-card-expandable">
                                    <input type="checkbox" name="casal" id="checkbox_casal" value="1" x-on:change="toggleQtdCasais()" class="form-checkbox">
                                    <svg class="checkbox-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                    </svg>
                                    <span>Casal</span>
                                    <input type="number" name="qtd_casais" id="qtd_casais" min="1" value="1" placeholder="Qtd" class="form-input form-input-sm checkbox-qty-input hidden" x-on:click.stop>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="catador_reciclados" value="1" class="form-checkbox">
                                    <svg class="checkbox-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    <span>Catador</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="fixacao_antiga" value="1" class="form-checkbox">
                                    <svg class="checkbox-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <span>Fixacao Antiga</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="excesso_objetos" value="1" class="form-checkbox">
                                    <svg class="checkbox-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                    <span>Excesso Objetos</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="trafico_ilicitos" value="1" class="form-checkbox">
                                    <svg class="checkbox-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                    </svg>
                                    <span>Trafico/Ilicitos</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="crianca_adolescente" value="1" class="form-checkbox">
                                    <svg class="checkbox-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <span>Crianca/Adolesc.</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="idosos" value="1" class="form-checkbox">
                                    <svg class="checkbox-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                    </svg>
                                    <span>Idosos</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="gestante" value="1" class="form-checkbox">
                                    <svg class="checkbox-icon" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2a3 3 0 100 6 3 3 0 000-6zm-1 8c-2.5 0-4 1.5-4 4v1h2v5h6v-5h2v-1c0-2.5-1.5-4-4-4h-2z"/>
                                    </svg>
                                    <span>Gestante</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="lgbtqiapn" value="1" class="form-checkbox">
                                    <svg class="checkbox-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                                    </svg>
                                    <span>LGBTQIAPN+</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="deficiente" value="1" class="form-checkbox">
                                    <svg class="checkbox-icon" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2a3 3 0 100 6 3 3 0 000-6zm-2 8l-4 4h3v6h6v-6h3l-4-4h-4z"/>
                                    </svg>
                                    <span>Deficiente</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="agrupamento_quimico" value="1" class="form-checkbox">
                                    <svg class="checkbox-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
                                    </svg>
                                    <span>Agrup. Quimico</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="saude_mental" value="1" class="form-checkbox">
                                    <svg class="checkbox-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                                    </svg>
                                    <span>Saude Mental</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="cena_uso_caracterizada" value="1" class="form-checkbox">
                                    <svg class="checkbox-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                    </svg>
                                    <span>Cena de Uso</span>
                                </label>
                                <label class="checkbox-card checkbox-card-expandable">
                                    <input type="checkbox" name="animais" id="checkbox_animais" value="1" x-on:change="toggleQtdAnimais()" class="form-checkbox">
                                    <svg class="checkbox-icon" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M4.5 9.5a2.5 2.5 0 115 0 2.5 2.5 0 01-5 0zm9 0a2.5 2.5 0 115 0 2.5 2.5 0 01-5 0zm-7.5 6a2 2 0 114 0 2 2 0 01-4 0zm7 0a2 2 0 114 0 2 2 0 01-4 0zm-3.5 2.5c0 1.38 1.12 2.5 2.5 2.5s2.5-1.12 2.5-2.5V16h-5v2z"/>
                                    </svg>
                                    <span>Animais</span>
                                    <input type="number" name="qtd_animais" id="qtd_animais" min="1" value="1" placeholder="Qtd" class="form-input form-input-sm checkbox-qty-input hidden" x-on:click.stop>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aba 3: Relatorio, Acoes e Encaminhamentos -->
                <div class="tab-content hidden" data-tab="2">
                    <!-- Resultado da Acao -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label required">Resultado da Acao</label>
                                <select name="resultado_acao_id" required class="form-input form-select">
                                    <option value="">Selecione...</option>
                                    @foreach($resultadosAcao as $resultado)
                                        @if($pontoProximo || !str_contains(strtolower($resultado->resultado), 'persiste'))
                                            <option value="{{ $resultado->id }}">{{ $resultado->resultado }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Acoes Realizadas: cada acao em seu proprio card -->
                    <h3 class="form-section-title" style="margin: var(--space-2) 0 var(--space-3);">Ações Realizadas</h3>

                    <!-- Conducao pelas Forcas de Seguranca -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="switch-field">
                                <input type="checkbox" class="switch-input" name="conducao_forcas_seguranca" value="1" x-on:change="toggleConducaoObs()">
                                <span class="switch-track"><span class="switch-thumb"></span></span>
                                <span class="switch-text">Condução pelas Forças de Segurança</span>
                                <span class="switch-state"></span>
                            </label>
                            <div id="conducao_obs_container" class="mt-2 hidden">
                                <textarea name="conducao_forcas_observacao" id="conducao_forcas_observacao" rows="2" placeholder="Observação sobre a condução..." class="form-input form-textarea"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Recolhimento de Inserviveis -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="switch-field">
                                <input type="checkbox" class="switch-input" name="apreensao_fiscal" value="1" x-on:change="document.getElementById('qtd_kg_container').classList.toggle('hidden', !$event.target.checked)">
                                <span class="switch-track"><span class="switch-thumb"></span></span>
                                <span class="switch-text">Recolhimento de Inservíveis</span>
                                <span class="switch-state"></span>
                            </label>
                            <div id="qtd_kg_container" class="mt-2 hidden">
                                <label class="form-label">Material recolhido (Kg)</label>
                                <input type="number" name="qtd_kg" min="0" placeholder="0" class="form-input">
                            </div>
                        </div>
                    </div>

                    <!-- Relatorio de Orientacao -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="switch-field">
                                <input type="checkbox" class="switch-input" name="auto_fiscalizacao_aplicado" value="1" x-on:change="toggleAutoNumero()">
                                <span class="switch-track"><span class="switch-thumb"></span></span>
                                <span class="switch-text">Relatório de Orientação</span>
                                <span class="switch-state"></span>
                            </label>
                            <div id="auto_numero_container" class="mt-2 hidden">
                                <input type="text" name="auto_fiscalizacao_numero" id="auto_fiscalizacao_numero" placeholder="Número Relatório Orientação" class="form-input">
                            </div>
                        </div>
                    </div>

                    <!-- Lavacao (limpeza com agua) -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="switch-field">
                                <input type="checkbox" class="switch-input" name="houve_lavacao" value="1">
                                <span class="switch-track"><span class="switch-thumb"></span></span>
                                <span class="switch-text">Houve Lavação</span>
                                <span class="switch-state"></span>
                            </label>
                            <p class="form-hint" style="margin-top: var(--space-2);">
                                Limpeza do local com água (não confundir com lavratura de auto).
                            </p>
                        </div>
                    </div>

                    <!-- Comunicado de Zeladoria -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="switch-field">
                                <input type="checkbox" class="switch-input" name="houve_comunicado" value="1" x-on:change="toggleComunicado()">
                                <span class="switch-track"><span class="switch-thumb"></span></span>
                                <span class="switch-text">Comunicado de Zeladoria</span>
                                <span class="switch-state"></span>
                            </label>
                            <p class="form-hint" style="margin-top: var(--space-2);">
                                Documento físico entregue aos moradores informando data prevista de retorno para zeladoria. O sistema registra as datas.
                            </p>
                            @php
                                $exibirComunicadoCampos = old('houve_comunicado')
                                    || old('data_prevista_zeladoria')
                                    || (old('tipo_abordagem_id') && collect($tiposAbordagem)->firstWhere('id', (int) old('tipo_abordagem_id'))?->isComunicacaoZeladoria());
                            @endphp
                            <div id="comunicado-zeladoria-campos" class="mt-3 {{ $exibirComunicadoCampos ? '' : 'hidden' }}">
                                @include('vistorias.partials.comunicado-zeladoria-datas')
                            </div>
                        </div>
                    </div>

                    <!-- Relatorio Descritivo -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <label class="form-label">Relatorio Descritivo da Acao</label>
                            <div class="input-with-voice">
                                <textarea name="observacao" id="observacao" rows="8" placeholder="Descreva detalhadamente a acao realizada..." class="form-input form-textarea" style="min-height: 200px;"></textarea>
                                <button type="button" x-on:click="startVoiceInput('observacao')" class="voice-btn">
                                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aba 4: Encaminhamentos -->
                <div class="tab-content hidden" data-tab="3">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h3 class="form-section-title">Encaminhamentos</h3>
                            <p class="text-muted mb-4" style="font-size: var(--text-sm);">
                                Selecione os encaminhamentos realizados durante esta vistoria (opcional).
                            </p>
                            <div class="flex flex-col gap-3">
                                <div class="form-group">
                                    <label class="form-label">Encaminhamento 1</label>
                                    <select name="e1_id" class="form-input form-select">
                                        <option value="">Nenhum</option>
                                        @foreach($encaminhamentos as $encaminhamento)
                                            <option value="{{ $encaminhamento->id }}">{{ $encaminhamento->encaminhamento }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Encaminhamento 2</label>
                                    <select name="e2_id" class="form-input form-select">
                                        <option value="">Nenhum</option>
                                        @foreach($encaminhamentos as $encaminhamento)
                                            <option value="{{ $encaminhamento->id }}">{{ $encaminhamento->encaminhamento }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Encaminhamento 3</label>
                                    <select name="e3_id" class="form-input form-select">
                                        <option value="">Nenhum</option>
                                        @foreach($encaminhamentos as $encaminhamento)
                                            <option value="{{ $encaminhamento->id }}">{{ $encaminhamento->encaminhamento }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Encaminhamento 4</label>
                                    <select name="e4_id" class="form-input form-select">
                                        <option value="">Nenhum</option>
                                        @foreach($encaminhamentos as $encaminhamento)
                                            <option value="{{ $encaminhamento->id }}">{{ $encaminhamento->encaminhamento }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Encaminhamento 5</label>
                                    <select name="e5_id" class="form-input form-select">
                                        <option value="">Nenhum</option>
                                        @foreach($encaminhamentos as $encaminhamento)
                                            <option value="{{ $encaminhamento->id }}">{{ $encaminhamento->encaminhamento }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Encaminhamento 6</label>
                                    <select name="e6_id" class="form-input form-select">
                                        <option value="">Nenhum</option>
                                        @foreach($encaminhamentos as $encaminhamento)
                                            <option value="{{ $encaminhamento->id }}">{{ $encaminhamento->encaminhamento }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aba 5: Pessoas no Ponto -->
                <div class="tab-content hidden" data-tab="4">
                    <!-- Estimativa de pessoas -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label">Estimativa num. pessoas</label>
                                <input type="number" name="quantidade_pessoas" min="0" placeholder="0" class="form-input">
                                <p class="form-hint">Estimativa do total de pessoas no local. Nao precisa coincidir com as pessoas cadastradas abaixo — nem sempre e possivel identificar todas.</p>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="form-section-title" style="margin-bottom: 0;">Pessoas no Ponto</h3>
                                <button type="button" x-on:click="abrirModalMorador()" class="btn btn-primary btn-sm">
                                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Nova
                                </button>
                            </div>

                            {{-- Busca de pessoa já cadastrada em outro ponto --}}
                            <div class="mb-4">
                                <div class="autocomplete-container">
                                    <svg style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-muted); pointer-events: none; z-index: 2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    <input type="text" id="busca-pessoa-existente" placeholder="Buscar pessoa já cadastrada (nome ou apelido)..." autocomplete="off" class="form-input" style="padding-left: 38px;">
                                    <div id="busca-pessoa-resultados" class="autocomplete-results" style="display: none;"></div>
                                </div>
                            </div>

                            {{-- Pessoas vinculadas de outros pontos (adicionadas via busca) --}}
                            <div id="pessoas-vinculadas" class="flex flex-col gap-2 mb-4"></div>

                            @if($pontoProximo && $pontoProximo->moradores->count() > 0)
                                <div class="mb-4">
                                    <p class="text-muted mb-2" style="font-size: var(--text-xs);">Pessoas já cadastradas neste ponto:</p>
                                    <div id="moradores-existentes" class="flex flex-col gap-2">
                                        @foreach($pontoProximo->moradores as $morador)
                                            <div class="morador-card">
                                                <div class="morador-avatar">
                                                    @if($morador->getFirstMediaUrl('fotos'))
                                                        <img src="{{ $morador->getFirstMediaUrl('fotos', 'thumb') ?: $morador->getFirstMediaUrl('fotos') }}" alt="{{ $morador->nome_social }}">
                                                    @else
                                                        <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                        </svg>
                                                    @endif
                                                </div>
                                                <div class="morador-info">
                                                    <p class="morador-name">{{ $morador->nome_social }}</p>
                                                    @if($morador->apelido)
                                                        <p class="morador-nickname">"{{ $morador->apelido }}"</p>
                                                    @endif
                                                </div>
                                                <label class="morador-presence">
                                                    <input type="checkbox" name="moradores_presentes[]" value="{{ $morador->id }}" checked class="form-checkbox">
                                                    <span>Presente</span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <p class="text-muted mb-4" id="sem-moradores-msg" style="font-size: var(--text-sm);">
                                    @if($pontoProximo)
                                        Nenhuma pessoa cadastrada neste ponto.
                                    @else
                                        As pessoas serão vinculadas ao novo ponto após o cadastro.
                                    @endif
                                </p>
                            @endif

                            <div id="novos-moradores" class="flex flex-col gap-2"></div>

                            <p class="text-muted mt-3 text-center" style="font-size: var(--text-xs);">
                                <span id="morador-count">0</span> nova(s) pessoa(s) a cadastrar
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Aba 6: Fotos da Vistoria -->
                <div class="tab-content hidden" data-tab="5">
                    <div class="card mb-4">
                        <div class="card-body">
                            <label class="form-label mb-3">Fotos da Vistoria</label>

                            <input type="file" id="camera-input-back" accept="image/*" capture="environment" class="hidden">
                            <input type="file" id="gallery-input" accept="image/*" multiple class="hidden">

                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" x-on:click="openCamera('back')" class="btn btn-primary btn-block">
                                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    Tirar Foto
                                </button>
                                <button type="button" x-on:click="document.getElementById('gallery-input').click()" class="btn btn-secondary btn-block">
                                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    Anexar Arquivo
                                </button>
                            </div>

                            {{-- Drop-zone desktop (clique-em-anexar-arquivo continua funcionando; isto adiciona arrastar) --}}
                            <div id="fotos-drop-zone"
                                 class="mt-3 text-center hidden md:block"
                                 style="border: 2px dashed var(--border-color, #d0d7de); border-radius: 8px; padding: var(--space-4); cursor: pointer; transition: background 0.15s, border-color 0.15s;">
                                <svg style="width: 28px; height: 28px; color: var(--text-muted); margin-bottom: 4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <p style="font-size: var(--text-sm); color: var(--text-muted); margin: 0;">
                                    Arraste fotos aqui ou clique para escolher
                                </p>
                            </div>

                            <div id="fotos-preview" class="photos-grid mt-4"></div>

                            <p class="text-muted mt-3 text-center" style="font-size: var(--text-xs);">
                                <span id="foto-count">0</span> foto(s) selecionada(s)
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Aba 7: Revisar e Finalizar -->
                <div class="tab-content hidden" data-tab="6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h3 class="form-section-title">
                                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Revisao da Zeladoria
                            </h3>
                            <p class="text-muted mb-4" style="font-size: var(--text-sm);">Verifique os dados antes de finalizar.</p>

                            <div id="review-checklist" class="review-checklist"></div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-body" style="text-align: center;">
                            <div id="review-status" class="mb-4"></div>
                            <button type="submit" id="btn-submit" class="btn btn-primary btn-block btn-lg">
                                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Registrar Zeladoria
                            </button>
                            <div id="submit-status" role="status" aria-live="polite" aria-atomic="true"
                                 style="display: none; align-items: center; justify-content: center; gap: 8px; margin-top: var(--space-3); font-size: var(--text-sm); color: var(--text-muted);">
                                <span class="spinner spinner-sm" aria-hidden="true"></span>
                                <span>Registrando zeladoria...</span>
                            </div>
                            <a href="{{ route('mapa.index') }}" id="btn-cancelar-vistoria" class="btn btn-ghost btn-block mt-2">Cancelar</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script>window.VISTORIA_TIPOS_ABRIGO = @json($tiposAbrigo);</script>

    <!-- Modal Adicionar/Editar Morador -->
    <div id="modal-morador" class="modal-overlay hidden" x-data="{}" x-on:click.self="fecharModalMorador()">
        <div class="modal" x-on:click.stop>
            <div class="modal-header">
                <h3 id="modal-morador-titulo" class="modal-title">Nova Pessoa</h3>
                <button type="button" x-on:click="fecharModalMorador()" class="btn btn-ghost btn-icon btn-sm">
                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="morador-edit-index" value="">

                <div class="form-group">
                    <label for="morador-nome-social" class="form-label required">Nome Social</label>
                    <input type="text" id="morador-nome-social" placeholder="Como deseja ser chamado" class="form-input">
                </div>

                <div class="form-group">
                    <label for="morador-apelido" class="form-label">Apelido</label>
                    <input type="text" id="morador-apelido" placeholder="Como e conhecido" class="form-input">
                </div>

                <div class="form-group">
                    <label for="morador-genero" class="form-label">Genero</label>
                    <select id="morador-genero" class="form-input form-select">
                        <option value="">Prefiro nao informar</option>
                        <option value="Homem cisgenero">Homem cisgenero</option>
                        <option value="Mulher cisgenero">Mulher cisgenero</option>
                        <option value="Homem trans">Homem trans</option>
                        <option value="Mulher trans">Mulher trans</option>
                        <option value="Travesti">Travesti</option>
                        <option value="Nao-binario">Nao-binario</option>
                        <option value="Outro">Outro</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="morador-documento" class="form-label">Documento</label>
                    <input type="text" id="morador-documento" placeholder="CPF ou RG" class="form-input">
                </div>

                <div class="form-group">
                    <label for="morador-contato" class="form-label">Contato</label>
                    <input type="text" id="morador-contato" placeholder="Telefone ou outro" class="form-input">
                </div>

                <div class="form-group">
                    <label for="morador-observacoes" class="form-label">Observacoes</label>
                    <textarea id="morador-observacoes" rows="2" placeholder="Informacoes adicionais" class="form-input form-textarea"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" x-on:click="salvarMorador()" class="btn btn-primary flex-1">Salvar</button>
                <button type="button" x-on:click="fecharModalMorador()" class="btn btn-secondary flex-1">Cancelar</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    window.VISTORIA_RASCUNHO_CTX = {
        ponto_id: {{ $pontoProximo?->id ?? 'null' }},
        lat: {{ $lat !== null && $lat !== '' ? json_encode((float) $lat) : 'null' }},
        lng: {{ $lng !== null && $lng !== '' ? json_encode((float) $lng) : 'null' }},
        debounce_ms: {{ (int) \App\Models\Parametro::get('rascunho_debounce_ms', 5000) }},
    };
</script>
@vite('resources/js/vistoria-form.js')
@endpush
