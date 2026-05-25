@extends('layouts.app')

@section('title', 'Nova Pessoa')

@section('header')
    <div class="flex items-center gap-3 flex-1">
        <a href="{{ route('moradores.index') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title flex-1 text-center">Nova Pessoa</span>
        <div style="width: 44px;"></div>
    </div>
@endsection

@section('content')
    <div class="form-page">
        <form action="{{ route('moradores.store') }}" method="POST" enctype="multipart/form-data" class="form-container">
            @csrf

            <div class="form-content">
                {{-- Ponto vinculado --}}
                @if($ponto)
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="flex items-center gap-3">
                                <div class="flex-shrink-0">
                                    <svg style="width: 24px; height: 24px; color: var(--accent-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-muted" style="font-size: var(--text-xs);">Vinculando ao ponto</p>
                                    <p style="font-weight: var(--font-medium);">
                                        {{ $ponto->enderecoAtualizado->SIGLA_TIPO_LOGRADOURO ?? '' }}
                                        {{ $ponto->enderecoAtualizado->NOME_LOGRADOURO ?? '' }},
                                        {{ $ponto->enderecoAtualizado->NUMERO_IMOVEL ?? $ponto->numero }}
                                    </p>
                                    <p class="text-muted" style="font-size: var(--text-xs);">
                                        {{ $ponto->enderecoAtualizado->NOME_BAIRRO_OFICIAL ?? '' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="ponto_id" value="{{ $ponto->id }}">
                @endif

                <!-- Fotos do Morador (identificacao) -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="form-section-title">Fotos de identificacao</h3>
                        <div id="foto-thumbs" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 6px; margin-bottom: 8px;"></div>
                        <div class="flex items-center gap-3">
                            <input type="file" id="camera-input" accept="image/*" capture="environment" multiple class="hidden">
                            <input type="file" id="gallery-input" name="fotografias[]" accept="image/*" multiple class="hidden">

                            <button type="button" x-on:click="document.getElementById('camera-input').click()" class="btn btn-primary btn-sm">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Tirar foto
                            </button>
                            <button type="button" x-on:click="document.getElementById('gallery-input').click()" class="btn btn-secondary btn-sm">
                                <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                Galeria
                            </button>
                        </div>
                        <p class="text-muted" style="font-size: var(--text-xs); margin-top: 8px;">Adicione uma ou mais fotos para identificar o morador caso ele apareca em outros pontos.</p>
                        @error('fotografias')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                        @error('fotografias.*')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                @push('scripts')
                <script>
                (function() {
                    const cameraInput = document.getElementById('camera-input');
                    const galleryInput = document.getElementById('gallery-input');
                    const thumbs = document.getElementById('foto-thumbs');

                    function mergeFiles(srcInput) {
                        if (srcInput.files.length === 0) return;
                        const dt = new DataTransfer();
                        Array.from(galleryInput.files).forEach(f => dt.items.add(f));
                        Array.from(srcInput.files).forEach(f => dt.items.add(f));
                        galleryInput.files = dt.files;
                        if (srcInput !== galleryInput) srcInput.value = '';
                        renderThumbs();
                    }
                    function renderThumbs() {
                        thumbs.innerHTML = '';
                        Array.from(galleryInput.files).forEach((f, idx) => {
                            const wrap = document.createElement('div');
                            wrap.style.cssText = 'position: relative; aspect-ratio: 1; border-radius: 6px; overflow: hidden;';
                            const img = document.createElement('img');
                            img.src = URL.createObjectURL(f);
                            img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
                            const rm = document.createElement('button');
                            rm.type = 'button';
                            rm.innerHTML = '&times;';
                            rm.style.cssText = 'position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border-radius: 50%; background: rgba(0,0,0,0.65); color: #fff; border: 0; cursor: pointer; font-size: 14px; line-height: 1;';
                            rm.addEventListener('click', () => {
                                const dt = new DataTransfer();
                                Array.from(galleryInput.files).forEach((file, i) => { if (i !== idx) dt.items.add(file); });
                                galleryInput.files = dt.files;
                                renderThumbs();
                            });
                            wrap.appendChild(img);
                            wrap.appendChild(rm);
                            thumbs.appendChild(wrap);
                        });
                    }
                    cameraInput.addEventListener('change', () => mergeFiles(cameraInput));
                    galleryInput.addEventListener('change', renderThumbs);
                })();
                </script>
                @endpush

                <!-- Identificacao -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="form-section-title">Identificacao</h3>

                        <div class="form-group">
                            <label for="nome_social" class="form-label required">Nome Social</label>
                            <div class="input-with-voice">
                                <input type="text" name="nome_social" id="nome_social" value="{{ old('nome_social') }}" required
                                       class="form-input @error('nome_social') is-invalid @enderror"
                                       placeholder="Nome pelo qual deseja ser chamado">
                                <button type="button" x-on:click="startVoiceInput('nome_social')" class="voice-btn">
                                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                                    </svg>
                                </button>
                            </div>
                            @error('nome_social')
                                <p class="form-error">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label for="nome_registro" class="form-label">Nome de Registro</label>
                            <div class="input-with-voice">
                                <input type="text" name="nome_registro" id="nome_registro" value="{{ old('nome_registro') }}"
                                       class="form-input"
                                       placeholder="Nome civil (se diferente)">
                                <button type="button" x-on:click="startVoiceInput('nome_registro')" class="voice-btn">
                                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="apelido" class="form-label">Apelido</label>
                            <div class="input-with-voice">
                                <input type="text" name="apelido" id="apelido" value="{{ old('apelido') }}"
                                       class="form-input"
                                       placeholder="Como e conhecido">
                                <button type="button" x-on:click="startVoiceInput('apelido')" class="voice-btn">
                                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="genero" class="form-label">Genero</label>
                            <select name="genero" id="genero" class="form-input form-select">
                                <option value="">Prefiro nao informar</option>
                                <option value="Homem cisgenero" {{ old('genero') == 'Homem cisgenero' ? 'selected' : '' }}>Homem cisgenero</option>
                                <option value="Mulher cisgenero" {{ old('genero') == 'Mulher cisgenero' ? 'selected' : '' }}>Mulher cisgenero</option>
                                <option value="Homem trans" {{ old('genero') == 'Homem trans' ? 'selected' : '' }}>Homem trans</option>
                                <option value="Mulher trans" {{ old('genero') == 'Mulher trans' ? 'selected' : '' }}>Mulher trans</option>
                                <option value="Travesti" {{ old('genero') == 'Travesti' ? 'selected' : '' }}>Travesti</option>
                                <option value="Nao-binario" {{ old('genero') == 'Nao-binario' ? 'selected' : '' }}>Nao-binario</option>
                                <option value="Outro" {{ old('genero') == 'Outro' ? 'selected' : '' }}>Outro</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Contato e Documentos -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="form-section-title">Contato e Documentos</h3>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="form-group">
                                <label for="documento" class="form-label">Documento</label>
                                <input type="text" name="documento" id="documento" value="{{ old('documento') }}"
                                       class="form-input"
                                       placeholder="CPF ou RG">
                            </div>

                            <div class="form-group">
                                <label for="contato" class="form-label">Contato</label>
                                <input type="text" name="contato" id="contato" value="{{ old('contato') }}"
                                       class="form-input"
                                       placeholder="Telefone">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Observacoes -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="form-section-title">Observacoes</h3>

                        <div class="form-group">
                            <div class="input-with-voice">
                                <textarea name="observacoes" id="observacoes" rows="3"
                                          class="form-input form-textarea"
                                          placeholder="Informacoes adicionais relevantes...">{{ old('observacoes') }}</textarea>
                                <button type="button" x-on:click="startVoiceInput('observacoes')" class="voice-btn">
                                    <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Botoes fixos --}}
            <div class="form-actions">
                <a href="{{ route('moradores.index') }}" class="btn btn-secondary">
                    Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Cadastrar
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
@endpush

@push('scripts')
@vite('resources/js/morador-form.js')
@endpush
