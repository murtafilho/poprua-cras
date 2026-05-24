@extends('layouts.app')

@section('title', $morador->nome_social)

@section('header')
    <div class="flex items-center gap-3 flex-1">
        <a href="{{ route('moradores.index') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title flex-1 text-center truncate">{{ $morador->nome_social }}</span>
        <a href="{{ route('moradores.edit', $morador) }}" class="btn btn-ghost btn-icon" title="Editar">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
        </a>
    </div>
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

        {{-- Card Principal --}}
        <div class="card mb-4">
            <div class="card-body">
                @php
                    $fotos = $morador->getMedia('fotos')->sortByDesc('created_at')->values();
                    $fotoPrincipal = $fotos->first();
                @endphp
                <div class="profile-header">
                    {{-- Foto principal (mais recente) --}}
                    @if($fotoPrincipal)
                        <a href="{{ $fotoPrincipal->getUrl('preview') ?: $fotoPrincipal->getUrl() }}" target="_blank" class="profile-avatar">
                            <img src="{{ $fotoPrincipal->getUrl('thumb') ?: $fotoPrincipal->getUrl() }}" alt="{{ $morador->nome_social }}" loading="lazy">
                        </a>
                    @else
                        <div class="profile-avatar">
                            <svg style="width: 48px; height: 48px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                    @endif

                    {{-- Dados --}}
                    <div class="profile-info">
                        <h2 class="profile-name">{{ $morador->nome_social }}</h2>

                        @if($morador->apelido)
                            <p class="profile-nickname">"{{ $morador->apelido }}"</p>
                        @endif

                        @if($morador->nome_registro && $morador->nome_registro !== $morador->nome_social)
                            <p class="text-muted" style="font-size: var(--text-xs); margin-top: var(--space-1);">
                                Nome de registro: {{ $morador->nome_registro }}
                            </p>
                        @endif

                        <div class="profile-details">
                            @if($morador->genero)
                                <div class="profile-detail">
                                    <span class="profile-detail-label">Genero:</span>
                                    <span class="profile-detail-value">{{ $morador->genero }}</span>
                                </div>
                            @endif

                            @if($morador->documento)
                                <div class="profile-detail">
                                    <span class="profile-detail-label">Documento:</span>
                                    <span class="profile-detail-value">{{ $morador->documento }}</span>
                                </div>
                            @endif

                            @if($morador->contato)
                                <div class="profile-detail">
                                    <span class="profile-detail-label">Contato:</span>
                                    <span class="profile-detail-value">{{ $morador->contato }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                @if($morador->observacoes)
                    <div class="profile-section">
                        <h3 class="profile-section-title">Observacoes</h3>
                        <p class="text-secondary" style="font-size: var(--text-sm); white-space: pre-wrap;">{{ $morador->observacoes }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Galeria de Fotos (identificacao) --}}
        @if($fotos->count() > 0)
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="flex items-center gap-2" style="font-size: var(--text-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-3);">
                        <svg style="width: 20px; height: 20px; color: var(--accent-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        Fotos de identificacao
                        <span class="badge badge-secondary" style="margin-left: auto;">{{ $fotos->count() }}</span>
                    </h3>
                    <div class="photo-gallery" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 8px;">
                        @foreach($fotos as $foto)
                            <a href="{{ $foto->getUrl('preview') ?: $foto->getUrl() }}" target="_blank"
                               class="photo-gallery-item"
                               style="aspect-ratio: 1; border-radius: 8px; overflow: hidden; position: relative; display: block;"
                               title="{{ $foto->created_at?->format('d/m/Y H:i') }}">
                                <img src="{{ $foto->getUrl('thumb') ?: $foto->getUrl() }}"
                                     alt="Foto {{ $loop->iteration }}"
                                     style="width: 100%; height: 100%; object-fit: cover;"
                                     loading="lazy">
                                <span style="position: absolute; bottom: 0; left: 0; right: 0; padding: 2px 6px; background: rgba(0,0,0,0.55); color: #fff; font-size: 10px; text-align: center;">
                                    {{ $foto->created_at?->format('d/m/Y') }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Ponto Atual --}}
        <div class="card mb-4">
            <div class="card-body">
                <h3 class="flex items-center gap-2" style="font-size: var(--text-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-3);">
                    <svg style="width: 20px; height: 20px; color: var(--accent-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    </svg>
                    Ponto Atual
                </h3>
                @if($morador->pontoAtual)
                    <a href="{{ route('pontos.show', $morador->pontoAtual->id) }}" class="location-card">
                        <div class="location-card-content">
                            <p class="location-card-address">
                                {{ $morador->pontoAtual->enderecoAtualizado->logradouro ?? 'Endereco' }}, {{ $morador->pontoAtual->enderecoAtualizado->numero ?? $morador->pontoAtual->numero }}
                            </p>
                            <p class="location-card-detail">
                                {{ $morador->pontoAtual->enderecoAtualizado->bairro ?? '' }} - {{ $morador->pontoAtual->enderecoAtualizado->regional ?? '' }}
                            </p>
                        </div>
                        <svg style="width: 20px; height: 20px; color: var(--text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @else
                    <p class="text-muted" style="font-size: var(--text-sm);">Nenhum ponto vinculado atualmente.</p>
                @endif
            </div>
        </div>

        {{-- Historico de Movimentacao --}}
        <div class="card mb-4">
            <div class="card-body">
                <h3 class="flex items-center gap-2" style="font-size: var(--text-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-3);">
                    <svg style="width: 20px; height: 20px; color: var(--status-info);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Historico de Movimentacao
                </h3>

                @if($historico->count() > 0)
                    <div class="timeline">
                        @foreach($historico as $registro)
                            <div class="timeline-item {{ !$loop->last ? 'has-line' : '' }}">
                                <div class="timeline-marker {{ $registro->data_saida ? 'inactive' : 'active' }}"></div>
                                <div class="timeline-content">
                                    <p class="timeline-title">
                                        {{ $registro->ponto->enderecoAtualizado->logradouro ?? 'Ponto' }}, {{ $registro->ponto->enderecoAtualizado->numero ?? $registro->ponto->numero ?? '' }}
                                    </p>
                                    <p class="timeline-subtitle">
                                        {{ $registro->ponto->enderecoAtualizado->bairro ?? '' }}
                                    </p>
                                    <div class="timeline-dates">
                                        <span class="timeline-date entry">
                                            <svg style="width: 12px; height: 12px;" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            Entrada: {{ $registro->data_entrada->format('d/m/Y') }}
                                        </span>
                                        @if($registro->data_saida)
                                            <span class="timeline-date exit">
                                                <svg style="width: 12px; height: 12px;" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                                </svg>
                                                Saida: {{ $registro->data_saida->format('d/m/Y') }}
                                            </span>
                                        @else
                                            <span class="badge badge-success">Atual</span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted" style="font-size: var(--text-sm);">Nenhum historico de movimentacao registrado.</p>
                @endif
            </div>
        </div>

        {{-- Acoes --}}
        <div class="flex gap-3">
            <a href="{{ route('moradores.edit', $morador) }}" class="btn btn-primary flex-1">
                <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Editar
            </a>
            <form action="{{ route('moradores.destroy', $morador) }}" method="POST" class="flex-1"
                  x-on:submit="if (!confirm('Tem certeza que deseja excluir este morador?')) $event.preventDefault()">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger w-full">
                    <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Excluir
                </button>
            </form>
        </div>
    </div>
@endsection
