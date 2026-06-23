@extends('layouts.app')

@section('title', 'Editar Vistoria')

@section('header')
    <div class="flex items-center gap-3 flex-1">
        <a href="{{ route('vistorias.show', $vistoria) }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title flex-1 text-center">Editar Vistoria</span>
        <div style="width: 44px;"></div>
    </div>
@endsection

@section('content')
    <div class="form-page">
        <form id="vistoria-form" action="{{ route('vistorias.update', $vistoria) }}" method="POST" enctype="multipart/form-data" class="form-container" novalidate x-data="{}">
            @csrf
            @method('PUT')

            <!-- Progress Stepper -->
            <div class="progress-stepper" id="progress-stepper">
                <div class="stepper-item active" data-step="0" onclick="goToStep(0)">
                    <div class="stepper-circle">1</div>
                    <span class="stepper-label">Dados</span>
                </div>
                <div class="stepper-item" data-step="1" onclick="goToStep(1)">
                    <div class="stepper-circle">2</div>
                    <span class="stepper-label">Caract.</span>
                </div>
                <div class="stepper-item" data-step="2" onclick="goToStep(2)">
                    <div class="stepper-circle">3</div>
                    <span class="stepper-label">Relatorio</span>
                </div>
                <div class="stepper-item" data-step="3" onclick="goToStep(3)">
                    <div class="stepper-circle">4</div>
                    <span class="stepper-label">Encam.</span>
                </div>
                <div class="stepper-item" data-step="4" onclick="goToStep(4)">
                    <div class="stepper-circle">5</div>
                    <span class="stepper-label">Pessoas</span>
                </div>
                <div class="stepper-item" data-step="5" onclick="goToStep(5)">
                    <div class="stepper-circle">6</div>
                    <span class="stepper-label">Fotos</span>
                </div>
                <div class="stepper-item" data-step="6" onclick="goToStep(6)">
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
                            <div id="ponto-atual">
                                @if($vistoria->ponto && $vistoria->ponto->enderecoAtualizado)
                                    <p style="font-size: var(--text-sm);">
                                        <span style="font-weight: var(--font-medium);">
                                            {{ $vistoria->ponto->enderecoAtualizado->SIGLA_TIPO_LOGRADOURO }}
                                            {{ $vistoria->ponto->enderecoAtualizado->NOME_LOGRADOURO }},
                                            {{ $vistoria->ponto->enderecoAtualizado->NUMERO_IMOVEL ?? $vistoria->ponto->numero }}
                                        </span>
                                    </p>
                                    <p class="text-muted" style="font-size: var(--text-xs);">
                                        {{ $vistoria->ponto->enderecoAtualizado->NOME_BAIRRO_OFICIAL }} - {{ $vistoria->ponto->enderecoAtualizado->NOME_REGIONAL }}
                                    </p>
                                @endif
                                @if($vistoria->ponto && $vistoria->ponto->lat && $vistoria->ponto->lng)
                                    <p class="text-muted" style="font-size: var(--text-xs); margin-top: var(--space-1);">
                                        Lat: {{ number_format($vistoria->ponto->lat, 6) }} | Lng: {{ number_format($vistoria->ponto->lng, 6) }}
                                    </p>
                                @endif
                            </div>
                            <input type="hidden" name="ponto_id" id="ponto_id_input" value="{{ $vistoria->ponto_id }}">
                            <div style="margin-top: var(--space-2);">
                                <button type="button" x-on:click="document.getElementById('alterar-ponto-section').classList.toggle('hidden')"
                                        class="btn btn-secondary btn-sm">
                                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                    </svg>
                                    Alterar Ponto
                                </button>
                            </div>
                            <div id="alterar-ponto-section" class="hidden" style="margin-top: var(--space-3);">
                                <div class="form-group">
                                    <label class="form-label">Buscar ponto por endereco</label>
                                    <input type="text" id="busca-ponto" placeholder="Digite o logradouro..." class="form-input"
                                           autocomplete="off">
                                    <div id="resultados-ponto" class="hidden" style="margin-top: var(--space-2); max-height: 200px; overflow-y: auto; border: 1px solid var(--border-primary); border-radius: var(--radius-md);"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dados da Zeladoria -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <h3 class="form-section-title">Dados da Zeladoria</h3>

                            <div class="form-group">
                                <label class="form-label required">Data/Hora da Abordagem</label>
                                <input type="datetime-local" name="data_abordagem"
                                       value="{{ $vistoria->data_abordagem ? $vistoria->data_abordagem->format('Y-m-d\TH:i') : '' }}"
                                       required class="form-input">
                            </div>

                            <div class="form-group">
                                <label class="form-label required">Tipo de Abordagem</label>
                                <select name="tipo_abordagem_id" id="tipo_abordagem_id" required class="form-input form-select">
                                    <option value="">Selecione...</option>
                                    @foreach($tiposAbordagem as $tipo)
                                        <option value="{{ $tipo->id }}" data-tipo="{{ $tipo->tipo }}" {{ $vistoria->tipo_abordagem_id == $tipo->id ? 'selected' : '' }}>
                                            {{ $tipo->tipo }}
                                        </option>
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
                                </p>

                                @php
                                    $participanteIds = $vistoria->participantes->pluck('id')->toArray();
                                @endphp

                                <div class="flex flex-col gap-1">
                                    @foreach($usuariosEquipe as $u)
                                        <label class="checkbox-option" style="display: flex; align-items: center; gap: var(--space-2); font-size: var(--text-sm);">
                                            <input type="checkbox" name="participantes[]" value="{{ $u->id }}"
                                                   {{ in_array($u->id, old('participantes', $participanteIds)) ? 'checked' : '' }} class="form-checkbox">
                                            <span>{{ $u->name }}@if($u->email) <span class="text-muted" style="font-size: var(--text-xs);">— {{ $u->email }}</span>@endif</span>
                                        </label>
                                    @endforeach
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
                                <input type="number" name="qtd_abrigos_provisorios" id="qtd_abrigos" min="0" placeholder="0"
                                       value="{{ $vistoria->qtd_abrigos_provisorios ?: '' }}"
                                       x-on:change="atualizarCamposAbrigos()" class="form-input">
                            </div>

                            <div id="abrigos-container" class="{{ ($vistoria->qtd_abrigos_provisorios ?? 0) > 0 ? '' : 'hidden' }}">
                                <label class="form-label">Tipos de Abrigo Desmontado</label>
                                <div id="abrigos-list" class="flex flex-col gap-2"></div>
                            </div>

                            <div id="tipo-abrigo-unico" class="form-group {{ ($vistoria->qtd_abrigos_provisorios ?? 0) > 0 ? 'hidden' : '' }}">
                                <label class="form-label">Tipo de Abrigo Desmontado</label>
                                <select name="tipo_abrigo_desmontado_id" class="form-input form-select">
                                    <option value="">Nenhum</option>
                                    @foreach($tiposAbrigo as $tipo)
                                        <option value="{{ $tipo->id }}" {{ $vistoria->tipo_abrigo_desmontado_id == $tipo->id ? 'selected' : '' }}>
                                            {{ $tipo->tipo_abrigo }}
                                        </option>
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
                                    <input type="checkbox" name="resistencia" value="1" {{ $vistoria->resistencia ? 'checked' : '' }} class="form-checkbox">
                                    <span>Resistencia</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="num_reduzido" value="1" {{ $vistoria->num_reduzido ? 'checked' : '' }} class="form-checkbox">
                                    <span>Num. Reduzido</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="casal" id="checkbox_casal" value="1" {{ $vistoria->casal ? 'checked' : '' }} x-on:change="toggleQtdCasais()" class="form-checkbox">
                                    <span>Casal</span>
                                </label>
                                <div id="qtd_casais_container" class="{{ $vistoria->casal ? '' : 'hidden' }}">
                                    <input type="number" name="qtd_casais" id="qtd_casais" min="1"
                                           value="{{ $vistoria->qtd_casais ?? 1 }}" placeholder="Qtd." class="form-input form-input-sm">
                                </div>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="catador_reciclados" value="1" {{ $vistoria->catador_reciclados ? 'checked' : '' }} class="form-checkbox">
                                    <span>Catador</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="fixacao_antiga" value="1" {{ $vistoria->fixacao_antiga ? 'checked' : '' }} class="form-checkbox">
                                    <span>Fixacao Antiga</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="excesso_objetos" value="1" {{ $vistoria->excesso_objetos ? 'checked' : '' }} class="form-checkbox">
                                    <span>Excesso Objetos</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="trafico_ilicitos" value="1" {{ $vistoria->trafico_ilicitos ? 'checked' : '' }} class="form-checkbox">
                                    <span>Trafico/Ilicitos</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="crianca_adolescente" value="1" {{ $vistoria->crianca_adolescente ? 'checked' : '' }} class="form-checkbox">
                                    <span>Crianca/Adolesc.</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="idosos" value="1" {{ $vistoria->idosos ? 'checked' : '' }} class="form-checkbox">
                                    <span>Idosos</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="gestante" value="1" {{ $vistoria->gestante ? 'checked' : '' }} class="form-checkbox">
                                    <span>Gestante</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="lgbtqiapn" value="1" {{ $vistoria->lgbtqiapn ? 'checked' : '' }} class="form-checkbox">
                                    <span>LGBTQIAPN+</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="deficiente" value="1" {{ $vistoria->deficiente ? 'checked' : '' }} class="form-checkbox">
                                    <span>Deficiente</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="agrupamento_quimico" value="1" {{ $vistoria->agrupamento_quimico ? 'checked' : '' }} class="form-checkbox">
                                    <span>Agrup. Quimico</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="saude_mental" value="1" {{ $vistoria->saude_mental ? 'checked' : '' }} class="form-checkbox">
                                    <span>Saude Mental</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="cena_uso_caracterizada" value="1" {{ $vistoria->cena_uso_caracterizada ? 'checked' : '' }} class="form-checkbox">
                                    <span>Cena de Uso</span>
                                </label>
                                <label class="checkbox-card">
                                    <input type="checkbox" name="animais" id="checkbox_animais" value="1" {{ $vistoria->animais ? 'checked' : '' }} x-on:change="toggleQtdAnimais()" class="form-checkbox">
                                    <span>Animais</span>
                                </label>
                                <div id="qtd_animais_container" class="{{ $vistoria->animais ? '' : 'hidden' }}">
                                    <input type="number" name="qtd_animais" id="qtd_animais" min="1"
                                           value="{{ $vistoria->qtd_animais ?? 1 }}" placeholder="Qtd." class="form-input form-input-sm">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Aba 3: Relatorio da Acao -->
                <div class="tab-content hidden" data-tab="2">
                    <!-- Resultado da Acao -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="form-group">
                                <label class="form-label required">Resultado da Acao</label>
                                <select name="resultado_acao_id" required class="form-input form-select">
                                    <option value="">Selecione...</option>
                                    @foreach($resultadosAcao as $resultado)
                                        <option value="{{ $resultado->id }}" {{ $vistoria->resultado_acao_id == $resultado->id ? 'selected' : '' }}>
                                            {{ $resultado->resultado }}
                                        </option>
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
                            <label class="form-label">Condução pelas Forças de Segurança</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="conducao_forcas_seguranca" value="1" {{ $vistoria->conducao_forcas_seguranca ? 'checked' : '' }} x-on:change="toggleConducaoObs()" class="form-radio">
                                    <span>Sim</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="conducao_forcas_seguranca" value="0" {{ !$vistoria->conducao_forcas_seguranca ? 'checked' : '' }} x-on:change="toggleConducaoObs()" class="form-radio">
                                    <span>Não</span>
                                </label>
                            </div>
                            <div id="conducao_obs_container" class="mt-2 {{ $vistoria->conducao_forcas_seguranca ? '' : 'hidden' }}">
                                <textarea name="conducao_forcas_observacao" id="conducao_forcas_observacao" rows="2" placeholder="Observação sobre a condução..." class="form-input form-textarea">{{ $vistoria->conducao_forcas_observacao }}</textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Apreensao Fiscal -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="form-label">Apreensão Fiscal</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="apreensao_fiscal" value="1" {{ $vistoria->apreensao_fiscal ? 'checked' : '' }} x-on:change="document.getElementById('qtd_kg_container').classList.toggle('hidden', $event.target.value !== '1')" class="form-radio">
                                    <span>Sim</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="apreensao_fiscal" value="0" {{ !$vistoria->apreensao_fiscal ? 'checked' : '' }} x-on:change="document.getElementById('qtd_kg_container').classList.toggle('hidden', $event.target.value !== '1')" class="form-radio">
                                    <span>Não</span>
                                </label>
                            </div>
                            <div id="qtd_kg_container" class="mt-2 {{ $vistoria->apreensao_fiscal ? '' : 'hidden' }}">
                                <label class="form-label">Qtd. Kg (material apreendido)</label>
                                <input type="number" name="qtd_kg" min="0" placeholder="0" value="{{ $vistoria->qtd_kg ?: '' }}" class="form-input">
                            </div>
                        </div>
                    </div>

                    <!-- Auto de Fiscalizacao Aplicado -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="form-label">Auto de Fiscalização Aplicado</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="auto_fiscalizacao_aplicado" value="1" {{ $vistoria->auto_fiscalizacao_aplicado ? 'checked' : '' }} x-on:change="toggleAutoNumero()" class="form-radio">
                                    <span>Sim</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="auto_fiscalizacao_aplicado" value="0" {{ !$vistoria->auto_fiscalizacao_aplicado ? 'checked' : '' }} x-on:change="toggleAutoNumero()" class="form-radio">
                                    <span>Não</span>
                                </label>
                            </div>
                            <div id="auto_numero_container" class="mt-2 {{ $vistoria->auto_fiscalizacao_aplicado ? '' : 'hidden' }}">
                                <input type="text" name="auto_fiscalizacao_numero" id="auto_fiscalizacao_numero"
                                       value="{{ $vistoria->auto_fiscalizacao_numero }}"
                                       placeholder="Número do Auto de Fiscalização" class="form-input">
                            </div>
                        </div>
                    </div>

                    <!-- Lavratura -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="form-label">Houve Lavratura</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="houve_lavratura" value="1" {{ $vistoria->houve_lavratura ? 'checked' : '' }} x-on:change="toggleProtocolo()" class="form-radio">
                                    <span>Sim</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="houve_lavratura" value="0" {{ !$vistoria->houve_lavratura ? 'checked' : '' }} x-on:change="toggleProtocolo()" class="form-radio">
                                    <span>Não</span>
                                </label>
                            </div>
                            <div id="tipo_protocolo_container" class="mt-2 {{ $vistoria->houve_lavratura ? '' : 'hidden' }}">
                                <label class="form-label">Tipo de Protocolo</label>
                                <select name="tipo_protocolo" class="form-input form-select">
                                    <option value="">Selecione...</option>
                                    <option value="chuva" {{ $vistoria->tipo_protocolo === 'chuva' ? 'selected' : '' }}>Chuva</option>
                                    <option value="frio" {{ $vistoria->tipo_protocolo === 'frio' ? 'selected' : '' }}>Frio</option>
                                    <option value="normal" {{ $vistoria->tipo_protocolo === 'normal' ? 'selected' : '' }}>Normal</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Comunicado de Zeladoria -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="form-label">Houve Comunicado entregue?</label>
                            <p class="form-hint" style="margin-top: -4px; margin-bottom: var(--space-2);">
                                Documento físico entregue aos moradores informando data prevista de retorno para zeladoria. O sistema registra as datas.
                            </p>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="houve_comunicado" value="1" {{ $vistoria->houve_comunicado ? 'checked' : '' }} x-on:change="toggleComunicado()" class="form-radio">
                                    <span>Sim</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="houve_comunicado" value="0" {{ !$vistoria->houve_comunicado ? 'checked' : '' }} x-on:change="toggleComunicado()" class="form-radio">
                                    <span>Não</span>
                                </label>
                            </div>
                            <div id="data_comunicado_container" class="mt-3 {{ $vistoria->houve_comunicado ? '' : 'hidden' }}">
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="form-group">
                                        <label class="form-label">Data de Entrega</label>
                                        <input type="date" name="data_comunicado" value="{{ $vistoria->data_comunicado?->format('Y-m-d') }}" class="form-input">
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label">Data de Retorno</label>
                                        <input type="datetime-local" name="data_prevista_zeladoria" value="{{ old('data_prevista_zeladoria', $vistoria->data_prevista_zeladoria?->format('Y-m-d\TH:i')) }}" class="form-input">
                                    </div>
                                </div>
                                <div class="form-group mt-2">
                                    <label class="form-label">Período de Retorno</label>
                                    <select name="periodo_zeladoria" class="form-input form-select">
                                        <option value="">Selecione...</option>
                                        <option value="manha" {{ $vistoria->periodo_zeladoria === 'manha' ? 'selected' : '' }}>Manhã</option>
                                        <option value="tarde" {{ $vistoria->periodo_zeladoria === 'tarde' ? 'selected' : '' }}>Tarde</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Relatorio Descritivo -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <label class="form-label">Relatorio Descritivo da Acao</label>
                            <div class="input-with-voice">
                                <textarea name="observacao" id="observacao" rows="8" placeholder="Descreva detalhadamente a acao realizada..." class="form-input form-textarea" style="min-height: 200px;">{{ $vistoria->observacao }}</textarea>
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
                                @for($i = 1; $i <= 6; $i++)
                                    <div class="form-group">
                                        <label class="form-label">Encaminhamento {{ $i }}</label>
                                        <select name="e{{ $i }}_id" class="form-input form-select">
                                            <option value="">Nenhum</option>
                                            @foreach($encaminhamentos as $enc)
                                                <option value="{{ $enc->id }}" {{ $vistoria->{'e'.$i.'_id'} == $enc->id ? 'selected' : '' }}>
                                                    {{ $enc->encaminhamento }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endfor
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
                                <input type="number" name="quantidade_pessoas" min="0" placeholder="0" value="{{ $vistoria->quantidade_pessoas ?: '' }}" class="form-input">
                                <p class="form-hint">Estimativa do total de pessoas no local. Nao precisa coincidir com as pessoas cadastradas abaixo — nem sempre e possivel identificar todas.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pessoas no Ponto -->
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

                            {{-- Pessoas vinculadas de outros pontos --}}
                            <div id="pessoas-vinculadas" class="flex flex-col gap-2 mb-4"></div>

                            @if($vistoria->ponto && $vistoria->ponto->moradores->count() > 0)
                                <div class="mb-4">
                                    <p class="text-muted mb-2" style="font-size: var(--text-xs);">Pessoas cadastradas neste ponto:</p>
                                    <div id="moradores-existentes" class="flex flex-col gap-2">
                                        @foreach($vistoria->ponto->moradores as $morador)
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
                                    Nenhuma pessoa cadastrada neste ponto.
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
                    <!-- Fotos -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <label class="form-label mb-3">Fotos da Vistoria</label>

                            @php $fotosExistentes = $vistoria->getMedia('fotos'); @endphp
                            @if($fotosExistentes->count() > 0)
                                <div class="mb-4">
                                    <p class="text-muted mb-2" style="font-size: var(--text-xs);">Fotos existentes — clique no cadeado para tornar pública (aparece no relatório). Texto sob a foto vira legenda.</p>
                                    <div class="photos-grid" id="fotos-existentes">
                                        @foreach($fotosExistentes as $foto)
                                            @php
                                                $fotoPublica = (bool) $foto->getCustomProperty('publica', false);
                                                $fotoLegenda = (string) $foto->getCustomProperty('legenda', '');
                                            @endphp
                                            <div class="photo-preview-wrap" id="foto-existente-{{ $foto->id }}" data-vistoria-id="{{ $vistoria->id }}">
                                                <div class="photo-preview {{ $fotoPublica ? 'foto-publica' : '' }}">
                                                    <img src="{{ $foto->getUrl('thumb') }}" alt="Foto" loading="lazy">
                                                    <button type="button" x-on:click="togglePublicaFoto({{ $foto->id }})" class="photo-publica-btn" data-publica="{{ $fotoPublica ? '1' : '0' }}" title="{{ $fotoPublica ? 'Pública — aparece no relatório do processo' : 'Privada — só no app' }}">
                                                        @if($fotoPublica)
                                                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                        @else
                                                            <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="9" rx="1.5" stroke-linecap="round" stroke-linejoin="round"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 1 1 8 0v4"/></svg>
                                                        @endif
                                                    </button>
                                                    <button type="button" x-on:click="marcarRemoverFoto({{ $foto->id }})" class="photo-remove-btn">
                                                        <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                                <input type="text" class="photo-legenda-input form-input"
                                                       placeholder="Legenda (opcional)"
                                                       maxlength="255"
                                                       value="{{ $fotoLegenda }}"
                                                       data-media-id="{{ $foto->id }}"
                                                       onchange="salvarLegendaFoto({{ $foto->id }}, this.value)">
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <style>
                                    .photo-publica-btn {
                                        position: absolute;
                                        bottom: 6px;
                                        right: 6px;
                                        width: 28px;
                                        height: 28px;
                                        display: flex;
                                        align-items: center;
                                        justify-content: center;
                                        border-radius: 50%;
                                        border: none;
                                        background: rgba(17, 18, 20, 0.78);
                                        color: var(--text-secondary, #a1a9b4);
                                        cursor: pointer;
                                        transition: all 120ms;
                                    }
                                    .photo-publica-btn[data-publica="1"] {
                                        background: var(--accent-primary, #2dd4bf);
                                        color: var(--text-inverse, #111214);
                                    }
                                    .photo-publica-btn:hover { transform: scale(1.08); }
                                    .photo-preview.foto-publica {
                                        outline: 2px solid var(--accent-primary, #2dd4bf);
                                        outline-offset: 1px;
                                        border-radius: 6px;
                                    }
                                    .photo-preview-wrap {
                                        display: flex;
                                        flex-direction: column;
                                        gap: var(--space-1);
                                    }
                                    .photo-legenda-input {
                                        font-size: var(--text-xs);
                                        padding: 4px 8px;
                                        height: auto;
                                    }
                                    .photo-legenda-input.saving { opacity: 0.6; }
                                    .photo-legenda-input.saved {
                                        border-color: var(--accent-primary, #2dd4bf);
                                        transition: border-color 1.5s;
                                    }
                                </style>
                                <script>
                                    async function togglePublicaFoto(mediaId) {
                                        const wrapper = document.getElementById(`foto-existente-${mediaId}`);
                                        if (!wrapper) return;
                                        const vistoriaId = wrapper.dataset.vistoriaId;
                                        const btn = wrapper.querySelector('.photo-publica-btn');
                                        btn.disabled = true;
                                        try {
                                            const resp = await fetch(`/ginfi/poprua-cras/public/api/vistorias/${vistoriaId}/fotos/${mediaId}/toggle-publica`, {
                                                method: 'POST',
                                                headers: {
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                                    'Accept': 'application/json',
                                                },
                                                credentials: 'same-origin',
                                            });
                                            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                                            const data = await resp.json();
                                            const publica = data.publica;
                                            btn.dataset.publica = publica ? '1' : '0';
                                            btn.title = publica ? 'Pública — aparece no relatório do processo' : 'Privada — só no app';
                                            wrapper.classList.toggle('foto-publica', publica);
                                            btn.innerHTML = publica
                                                ? '<svg style="width:16px;height:16px;" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round"/></svg>'
                                                : '<svg style="width:16px;height:16px;" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="9" rx="1.5" stroke-linecap="round" stroke-linejoin="round"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 1 1 8 0v4"/></svg>';
                                        } catch (e) {
                                            console.error('Falha ao alternar pública:', e);
                                            alert('Não foi possível atualizar. Tente novamente.');
                                        } finally {
                                            btn.disabled = false;
                                        }
                                    }

                                    async function salvarLegendaFoto(mediaId, legenda) {
                                        const wrapper = document.getElementById(`foto-existente-${mediaId}`);
                                        if (!wrapper) return;
                                        const vistoriaId = wrapper.dataset.vistoriaId;
                                        const input = wrapper.querySelector('.photo-legenda-input');
                                        input.classList.remove('saved');
                                        input.classList.add('saving');
                                        try {
                                            const resp = await fetch(`/ginfi/poprua-cras/public/api/vistorias/${vistoriaId}/fotos/${mediaId}/legenda`, {
                                                method: 'PATCH',
                                                headers: {
                                                    'Content-Type': 'application/json',
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                                    'Accept': 'application/json',
                                                },
                                                credentials: 'same-origin',
                                                body: JSON.stringify({ legenda: legenda || '' }),
                                            });
                                            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
                                            input.classList.add('saved');
                                            setTimeout(() => input.classList.remove('saved'), 1500);
                                        } catch (e) {
                                            console.error('Falha ao salvar legenda:', e);
                                            alert('Não foi possível salvar a legenda. Tente novamente.');
                                        } finally {
                                            input.classList.remove('saving');
                                        }
                                    }
                                </script>
                            @endif

                            <input type="file" id="camera-input-back" accept="image/*" capture="environment" class="hidden">
                            <input type="file" id="gallery-input" accept="image/*" multiple class="hidden">

                            <div class="grid grid-cols-2 gap-2">
                                <button type="button" x-on:click="openCamera()" class="btn btn-primary btn-block">
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
                                <span id="foto-count">0</span> nova(s) foto(s) selecionada(s)
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
                            <p class="text-muted mb-4" style="font-size: var(--text-sm);">Verifique os dados antes de salvar.</p>

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
                                Salvar Alteracoes
                            </button>
                            <button type="button" id="btn-finalizar"
                                    class="btn btn-success btn-block btn-lg mt-2"
                                    x-on:click="if(confirm('Deseja salvar e finalizar esta zeladoria? Apos a finalizacao, nao sera possivel editar.')) { document.getElementById('finalizar-apos-salvar').value='1'; document.getElementById('btn-submit').click(); }">
                                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Salvar e Finalizar
                            </button>
                            <a href="{{ route('vistorias.show', $vistoria) }}" class="btn btn-ghost btn-block mt-2">Cancelar</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navegação entre abas -->
            <div class="form-step-nav" id="form-step-nav">
                <button type="button" id="btn-prev" class="btn btn-secondary" onclick="goToStep(Math.max(0, window.__currentTab - 1))" style="display: none;">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    Anterior
                </button>
                <button type="button" id="btn-next" class="btn btn-primary" onclick="goToStep(Math.min(4, window.__currentTab + 1))">
                    Próximo
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </button>
            </div>

            <!-- Inputs para fotos a remover -->
            <div id="fotos-remover-inputs"></div>
            <input type="hidden" id="finalizar-apos-salvar" name="finalizar_apos_salvar" value="0">
        </form>
    </div>

    <!-- Modal Adicionar Morador -->
    <div id="modal-morador" class="modal-overlay hidden" x-on:click.self="fecharModalMorador()">
        <div class="modal modal-bottom" x-on:click.stop>
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
                    <input type="text" id="morador-apelido" placeholder="Como é conhecido" class="form-input">
                </div>
                <div class="form-group">
                    <label for="morador-genero" class="form-label">Gênero</label>
                    <select id="morador-genero" class="form-input form-select">
                        <option value="">Prefiro não informar</option>
                        <option value="Homem cisgenero">Homem cisgênero</option>
                        <option value="Mulher cisgenero">Mulher cisgênero</option>
                        <option value="Homem trans">Homem trans</option>
                        <option value="Mulher trans">Mulher trans</option>
                        <option value="Travesti">Travesti</option>
                        <option value="Nao-binario">Não-binário</option>
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
                    <label for="morador-observacoes" class="form-label">Observações</label>
                    <textarea id="morador-observacoes" rows="2" placeholder="Informações adicionais" class="form-input form-textarea"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" x-on:click="salvarMorador()" class="btn btn-primary flex-1">Salvar</button>
                <button type="button" x-on:click="fecharModalMorador()" class="btn btn-secondary flex-1">Cancelar</button>
            </div>
        </div>
    </div>

    <script>window.VISTORIA_TIPOS_ABRIGO = @json($tiposAbrigo); window.VISTORIA_ABRIGOS_SELECIONADOS = @json($vistoria->abrigos_tipos ?? []); window.VISTORIA_ID = {{ $vistoria->id }};</script>
@endsection

@push('scripts')
@vite('resources/js/vistoria-edit.js')
@endpush
