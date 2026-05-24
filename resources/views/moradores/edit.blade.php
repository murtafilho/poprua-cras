@extends('layouts.app')

@section('title', 'Editar Morador')

@section('header')
    <div class="flex items-center gap-3 flex-1">
        <a href="{{ route('moradores.show', $morador) }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title flex-1 text-center">Editar Morador</span>
        <div style="width: 44px;"></div>
    </div>
@endsection

@section('content')
    <div class="form-page">
        <form action="{{ route('moradores.update', $morador) }}" method="POST" enctype="multipart/form-data" class="form-container">
            @csrf
            @method('PUT')

            <div class="form-content">
                <!-- Fotos do Morador -->
                @php
                    $fotosExistentes = $morador->getMedia('fotos')->sortByDesc('created_at')->values();
                @endphp
                <div class="card mb-4">
                    <div class="card-body">
                        <h3 class="form-section-title">Fotos de identificacao</h3>

                        @if($fotosExistentes->count() > 0)
                            <div class="photo-gallery mb-3" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 6px;">
                                @foreach($fotosExistentes as $foto)
                                    <div style="position: relative; aspect-ratio: 1; border-radius: 6px; overflow: hidden;">
                                        <img src="{{ $foto->getUrl('thumb') ?: $foto->getUrl() }}"
                                             alt="Foto {{ $loop->iteration }} do morador"
                                             style="width: 100%; height: 100%; object-fit: cover;"
                                             loading="lazy">
                                        <button type="button"
                                                data-foto-id="{{ $foto->id }}"
                                                class="js-remover-foto"
                                                style="position: absolute; top: 4px; right: 4px; width: 22px; height: 22px; border-radius: 50%; background: rgba(0,0,0,0.65); color: #fff; border: 0; cursor: pointer; display: flex; align-items: center; justify-content: center;"
                                                title="Remover esta foto">
                                            <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                        <span style="position: absolute; bottom: 0; left: 0; right: 0; padding: 1px 4px; background: rgba(0,0,0,0.55); color: #fff; font-size: 9px; text-align: center;">
                                            {{ $foto->created_at?->format('d/m/Y') }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

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
                            <span id="fotos-novas-count" class="text-muted" style="font-size: var(--text-xs);"></span>
                        </div>

                        <p class="text-muted" style="font-size: var(--text-xs); margin-top: 8px;">As fotos servem para identificar o morador quando ele aparecer em outros pontos. Voce pode adicionar varias.</p>

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
                    const csrf = '{{ csrf_token() }}';
                    const moradorId = {{ $morador->id }};

                    const cameraInput = document.getElementById('camera-input');
                    const galleryInput = document.getElementById('gallery-input');
                    const countEl = document.getElementById('fotos-novas-count');

                    function syncFromCamera() {
                        if (cameraInput.files.length === 0) return;
                        const dt = new DataTransfer();
                        Array.from(galleryInput.files).forEach(f => dt.items.add(f));
                        Array.from(cameraInput.files).forEach(f => dt.items.add(f));
                        galleryInput.files = dt.files;
                        cameraInput.value = '';
                        updateCount();
                    }
                    function updateCount() {
                        const n = galleryInput.files.length;
                        countEl.textContent = n > 0 ? `${n} nova(s) foto(s) para adicionar` : '';
                    }
                    cameraInput.addEventListener('change', syncFromCamera);
                    galleryInput.addEventListener('change', updateCount);

                    document.querySelectorAll('.js-remover-foto').forEach(btn => {
                        btn.addEventListener('click', async () => {
                            if (!confirm('Remover esta foto?')) return;
                            const id = btn.dataset.fotoId;
                            const res = await fetch(`/api/moradores/${moradorId}/fotos/${id}`, {
                                method: 'DELETE',
                                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                                credentials: 'same-origin'
                            });
                            if (res.ok) {
                                btn.closest('div').remove();
                            } else {
                                alert('Erro ao remover foto.');
                            }
                        });
                    });
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
                                <input type="text" name="nome_social" id="nome_social" value="{{ old('nome_social', $morador->nome_social) }}" required
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
                                <input type="text" name="nome_registro" id="nome_registro" value="{{ old('nome_registro', $morador->nome_registro) }}"
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
                                <input type="text" name="apelido" id="apelido" value="{{ old('apelido', $morador->apelido) }}"
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
                                <option value="Homem cisgenero" {{ old('genero', $morador->genero) == 'Homem cisgenero' ? 'selected' : '' }}>Homem cisgenero</option>
                                <option value="Mulher cisgenero" {{ old('genero', $morador->genero) == 'Mulher cisgenero' ? 'selected' : '' }}>Mulher cisgenero</option>
                                <option value="Homem trans" {{ old('genero', $morador->genero) == 'Homem trans' ? 'selected' : '' }}>Homem trans</option>
                                <option value="Mulher trans" {{ old('genero', $morador->genero) == 'Mulher trans' ? 'selected' : '' }}>Mulher trans</option>
                                <option value="Travesti" {{ old('genero', $morador->genero) == 'Travesti' ? 'selected' : '' }}>Travesti</option>
                                <option value="Nao-binario" {{ old('genero', $morador->genero) == 'Nao-binario' ? 'selected' : '' }}>Nao-binario</option>
                                <option value="Outro" {{ old('genero', $morador->genero) == 'Outro' ? 'selected' : '' }}>Outro</option>
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
                                <input type="text" name="documento" id="documento" value="{{ old('documento', $morador->documento) }}"
                                       class="form-input"
                                       placeholder="CPF ou RG">
                            </div>

                            <div class="form-group">
                                <label for="contato" class="form-label">Contato</label>
                                <input type="text" name="contato" id="contato" value="{{ old('contato', $morador->contato) }}"
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
                                          placeholder="Informacoes adicionais relevantes...">{{ old('observacoes', $morador->observacoes) }}</textarea>
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
                <a href="{{ route('moradores.show', $morador) }}" class="btn btn-secondary">
                    Cancelar
                </a>
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
@endpush

@push('scripts')
@vite('resources/js/morador-form.js')
@endpush
