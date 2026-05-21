@extends('layouts.app')

@section('title', 'Moradores')

@section('header')
    <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
        <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
    </a>
    <span class="mobile-header-title" style="flex: 1; text-align: center;">Moradores</span>
    <a href="{{ route('moradores.create') }}" class="btn btn-ghost btn-icon" title="Novo morador">
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

        {{-- Filtros --}}
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route('moradores.index') }}" style="display: flex; flex-direction: column; gap: var(--space-3);">
                    <div class="form-row form-row-3">
                        <div class="form-group">
                            <label class="form-label">Buscar</label>
                            <input type="text" name="search" value="{{ request('search') }}"
                                   placeholder="Nome ou apelido..."
                                   class="form-input">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Genero</label>
                            <select name="genero" class="form-input form-select">
                                <option value="">Todos</option>
                                @foreach($generos as $genero)
                                    <option value="{{ $genero }}" {{ request('genero') == $genero ? 'selected' : '' }}>
                                        {{ $genero }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Situacao</label>
                            <select name="situacao" class="form-input form-select">
                                <option value="">Todos</option>
                                <option value="com_ponto" {{ request('situacao') == 'com_ponto' ? 'selected' : '' }}>Com ponto</option>
                                <option value="sem_ponto" {{ request('situacao') == 'sem_ponto' ? 'selected' : '' }}>Sem ponto</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">
                            <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            Filtrar
                        </button>
                        <a href="{{ route('moradores.index') }}" class="btn btn-secondary">
                            Limpar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        {{-- Lista --}}
        <div class="morador-list">
            @forelse($moradores as $morador)
                <a href="{{ route('moradores.show', $morador) }}" class="card card-interactive">
                    <div class="card-body">
                        <div class="morador-card-content">
                            {{-- Foto principal (mais recente) --}}
                            @php
                                $fotoCapa = $morador->getMedia('fotos')->sortByDesc('created_at')->first();
                            @endphp
                            @if($fotoCapa)
                                <div class="avatar avatar-lg" style="position: relative;">
                                    <img src="{{ $fotoCapa->getUrl('thumb') ?: $fotoCapa->getUrl() }}"
                                         alt="{{ $morador->nome_social }}"
                                         loading="lazy">
                                    @if($morador->getMedia('fotos')->count() > 1)
                                        <span style="position: absolute; bottom: 0; right: 0; background: var(--accent-primary, #1f6feb); color: #fff; font-size: 9px; padding: 1px 5px; border-radius: 999px;">
                                            +{{ $morador->getMedia('fotos')->count() - 1 }}
                                        </span>
                                    @endif
                                </div>
                            @else
                                <div class="avatar avatar-lg">
                                    <svg style="width: 28px; height: 28px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                            @endif

                            {{-- Info --}}
                            <div class="morador-info">
                                <div class="morador-name-row">
                                    <h3 class="truncate" style="font-weight: var(--font-semibold);">
                                        {{ $morador->nome_social }}
                                    </h3>
                                    @if($morador->apelido)
                                        <span class="text-muted" style="font-size: var(--text-sm);">({{ $morador->apelido }})</span>
                                    @endif
                                </div>

                                @if($morador->genero)
                                    <p class="text-secondary" style="font-size: var(--text-sm);">{{ $morador->genero }}</p>
                                @endif

                                @if($morador->pontoAtual)
                                    <p class="morador-location">
                                        <svg style="width: 12px; height: 12px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        </svg>
                                        {{ $morador->pontoAtual->enderecoAtualizado->logradouro ?? '' }}, {{ $morador->pontoAtual->enderecoAtualizado->numero ?? $morador->pontoAtual->numero }}
                                    </p>
                                @else
                                    <p class="text-muted" style="font-size: var(--text-xs); margin-top: var(--space-1);">Sem ponto vinculado</p>
                                @endif
                            </div>

                            {{-- Seta --}}
                            <div class="text-muted" style="flex-shrink: 0;">
                                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="empty-state">
                    <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <h3 class="empty-state-title">Nenhum morador encontrado</h3>
                    <p class="empty-state-description">Comece cadastrando o primeiro morador no sistema.</p>
                    <a href="{{ route('moradores.create') }}" class="btn btn-primary">
                        <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Cadastrar morador
                    </a>
                </div>
            @endforelse
        </div>

        {{-- Paginacao --}}
        <x-pagination-bar :paginator="$moradores->withQueryString()" label="moradores" />
    </div>
@endsection
