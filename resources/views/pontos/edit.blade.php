@extends('layouts.app')

@section('title', 'Editar Ponto')

@section('header')
    <div class="flex items-center gap-3 flex-1">
        <a href="{{ route('pontos.show', $ponto->id) }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title flex-1 text-center">Editar Ponto #{{ $ponto->id }}</span>
        <div style="width: 44px;"></div>
    </div>
@endsection

@section('content')
    <div class="form-page">
        <form action="{{ route('pontos.update', $ponto) }}" method="POST" class="form-container">
            @csrf
            @method('PUT')

            <div class="form-content">
                {{-- Endereco atual --}}
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="form-section-title">Endereco Atual</h3>

                        @if($ponto->enderecoAtualizado)
                            <div style="padding: var(--space-3); background: var(--bg-tertiary); border-radius: var(--radius-md); margin-bottom: var(--space-3);">
                                <p style="font-weight: var(--font-medium);">
                                    {{ $ponto->enderecoAtualizado->SIGLA_TIPO_LOGRADOURO }}
                                    {{ $ponto->enderecoAtualizado->NOME_LOGRADOURO }},
                                    {{ $ponto->enderecoAtualizado->NUMERO_IMOVEL ?? $ponto->numero }}
                                </p>
                                <p class="text-muted" style="font-size: var(--text-xs);">
                                    {{ $ponto->enderecoAtualizado->NOME_BAIRRO_POPULAR }} - {{ $ponto->enderecoAtualizado->NOME_REGIONAL }}
                                </p>
                            </div>
                        @else
                            <p class="text-muted" style="font-size: var(--text-sm); margin-bottom: var(--space-3);">Nenhum endereco vinculado.</p>
                        @endif

                        {{-- Buscar novo endereco --}}
                        <div class="form-group">
                            <label class="form-label">Buscar Endereco</label>
                            <div class="autocomplete-container">
                                <svg style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-muted); pointer-events: none; z-index: 2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                                <input type="text" id="busca-endereco" class="form-input" style="padding-left: 38px;" placeholder="Digite logradouro, numero..." autocomplete="off">
                                <div id="endereco-results" class="autocomplete-results" style="display: none;"></div>
                            </div>
                            <input type="hidden" name="endereco_atualizado_id" id="endereco_atualizado_id" value="{{ old('endereco_atualizado_id') }}">
                            <div id="endereco-selecionado" class="hidden" style="margin-top: var(--space-2); padding: var(--space-2); background: rgba(139, 92, 246, 0.1); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between;">
                                <span id="endereco-selecionado-texto" style="font-size: var(--text-sm);"></span>
                                <button type="button" onclick="limparEndereco()" class="btn btn-ghost btn-sm" style="color: var(--status-danger);">
                                    <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            <p class="text-muted" style="font-size: var(--text-xs); margin-top: var(--space-1);">Selecione um endereco da lista para alterar o vinculo. Deixe vazio para manter o atual.</p>
                        </div>
                    </div>
                </div>

                {{-- Numero e Complemento --}}
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="form-section-title">Identificacao</h3>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="form-group">
                                <label for="numero" class="form-label">Numero</label>
                                <input type="text" name="numero" id="numero" value="{{ old('numero', $ponto->numero) }}"
                                       class="form-input @error('numero') is-invalid @enderror"
                                       placeholder="Ex: 1234 ou S/N">
                                @error('numero')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="complemento" class="form-label">Complemento</label>
                                <input type="text" name="complemento" id="complemento" value="{{ old('complemento', $ponto->complemento) }}"
                                       class="form-input @error('complemento') is-invalid @enderror"
                                       placeholder="Referencia do local">
                                @error('complemento')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="observacao" class="form-label">Observacao</label>
                            <textarea name="observacao" id="observacao" rows="2"
                                      class="form-input form-textarea @error('observacao') is-invalid @enderror"
                                      placeholder="Observacoes sobre o ponto">{{ old('observacao', $ponto->observacao) }}</textarea>
                            @error('observacao')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Coordenadas --}}
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="form-section-title">Coordenadas</h3>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="form-group">
                                <label for="lat" class="form-label">Latitude</label>
                                <input type="text" name="lat" id="lat" value="{{ old('lat', $ponto->lat) }}"
                                       class="form-input @error('lat') is-invalid @enderror"
                                       placeholder="-19.912345">
                                @error('lat')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="lng" class="form-label">Longitude</label>
                                <input type="text" name="lng" id="lng" value="{{ old('lng', $ponto->lng) }}"
                                       class="form-input @error('lng') is-invalid @enderror"
                                       placeholder="-43.940123">
                                @error('lng')
                                    <p class="form-error">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Mini mapa para ajuste visual --}}
                        <div id="mini-map" style="height: 250px; border-radius: var(--radius-md); margin-top: var(--space-3); border: 1px solid var(--border-primary);"></div>
                        <p class="text-muted" style="font-size: var(--text-xs); margin-top: var(--space-1);">Clique no mapa para ajustar a posicao do ponto. As coordenadas serao atualizadas automaticamente.</p>
                    </div>
                </div>
            </div>

            {{-- Botoes fixos --}}
            <div class="form-actions">
                <a href="{{ route('pontos.show', $ponto->id) }}" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">
                    <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Salvar
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
@vite('resources/js/ponto-edit.js')
@endpush
