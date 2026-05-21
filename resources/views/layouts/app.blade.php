<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-base" content="{{ rtrim(url('/'), '/') }}">
    <meta name="theme-color" content="#111214">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="POPRUA">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192x192.png') }}">

    <title>{{ config('app.name', 'POPRUA') }} - @yield('title', 'Sistema')</title>

    {{-- Google Fonts — Outfit: geometric, field-legible --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet"></noscript>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
</head>
<body>
    <a href="#main-content" class="skip-link">Pular para o conteudo</a>
    <div id="app">
        @include('layouts.partials.sidebar')


        {{-- Main Content --}}
        <div class="main-wrapper">
            {{-- Mobile Header --}}
            <header class="mobile-header">
                @hasSection('header')
                    @yield('header')
                @else
                    <a href="{{ route('dashboard') }}" class="mobile-header-back" aria-label="Voltar">
                        <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </a>
                    <span class="mobile-header-title">@yield('title', 'POPRUA')</span>
                    <div style="width: 44px;"></div>
                @endif
            </header>

            <main id="main-content" class="page has-mobile-header has-bottom-nav">
                @yield('content')
            </main>

        </div>

        {{-- Mobile Bottom Navigation — only field-essential items --}}
        <nav class="bottom-nav" id="bottom-nav" aria-label="Navegacao principal">
            <a href="{{ route('mapa.index') }}" class="bottom-nav-item {{ request()->routeIs('mapa.*') ? 'active' : '' }}">
                <svg class="bottom-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                </svg>
                <span class="bottom-nav-label">Mapa</span>
            </a>
            <a href="{{ route('vistorias.minhas') }}" class="bottom-nav-item {{ request()->routeIs('vistorias.minhas') ? 'active' : '' }}">
                <svg class="bottom-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                <span class="bottom-nav-label">Minhas</span><!-- zeladorias -->
            </a>
            <a href="{{ route('vistorias.index') }}" class="bottom-nav-item {{ request()->routeIs('vistorias.index') ? 'active' : '' }}">
                <svg class="bottom-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                <span class="bottom-nav-label">Zeladorias</span>
            </a>
            <a href="{{ route('moradores.index') }}" class="bottom-nav-item {{ request()->routeIs('moradores.*') ? 'active' : '' }}">
                <svg class="bottom-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span class="bottom-nav-label">Moradores</span>
            </a>
            <button type="button" class="bottom-nav-item {{ request()->routeIs('admin.*') || request()->routeIs('pontos.*') || request()->routeIs('dashboard') ? 'active' : '' }}" id="bottom-nav-more" aria-expanded="false" aria-controls="sidebar" aria-label="Mais opcoes">
                <svg class="bottom-nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <span class="bottom-nav-label">Mais</span>
            </button>
        </nav>
    </div>

    @if(request()->routeIs('mapa.index') && request('geocoded') == '1' && request('ponto_id'))
    {{-- Painel de Confirmacao de Geocodificacao --}}
    <div id="geocode-panel" class="card card-glass" style="position: fixed; top: 70px; left: var(--space-4); right: var(--space-4); z-index: 99999; border: 2px solid var(--color-warning);">
        <div class="card-body">
            <div style="display: flex; gap: var(--space-3);">
                <div style="flex-shrink: 0;">
                    <svg style="width: 32px; height: 32px; color: var(--color-warning);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
                <div style="flex: 1;">
                    <h3 style="font-size: var(--text-sm); font-weight: var(--font-semibold); color: var(--text-primary); margin: 0;">Confirmar Localização</h3>
                    <p class="text-muted" style="font-size: var(--text-sm); margin-top: var(--space-1);">
                        Clique no mapa para ajustar a posição ou confirme a localização atual.
                    </p>
                    <p id="geocode-coords" class="text-mono text-muted" style="margin-top: var(--space-2);"></p>
                </div>
            </div>
            <div style="display: flex; gap: var(--space-2); margin-top: var(--space-4);">
                <button id="btn-confirmar-geocode" class="btn btn-success" style="flex: 1;">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Confirmar
                </button>
                <a href="{{ route('mapa.index') }}" class="btn btn-secondary">
                    Cancelar
                </a>
            </div>
        </div>
    </div>
    @endif

    @if(request()->routeIs('mapa.index') && request('ajustar') == '1' && request('ponto_id'))
    {{-- Painel de Ajuste de Ponto --}}
    <div id="ajustar-panel" class="card card-glass" style="position: fixed; bottom: 80px; left: var(--space-3); right: var(--space-3); z-index: 99999; border: 2px solid var(--accent-primary);">
        <div class="card-body" style="padding: var(--space-3);">
            <div style="display: flex; align-items: center; gap: var(--space-3);">
                <div style="flex-shrink: 0; width: 40px; height: 40px; border-radius: var(--card-radius); background: var(--accent-dim); display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 22px; height: 22px; color: var(--accent-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="3" stroke-width="2"/>
                        <circle cx="12" cy="12" r="7" stroke-width="2"/>
                        <path stroke-linecap="round" stroke-width="2" d="M12 2v3M12 19v3M2 12h3M19 12h3"/>
                    </svg>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <h3 style="font-size: var(--text-sm); font-weight: var(--font-semibold); color: var(--text-primary); margin: 0;">Ajustar Localização</h3>
                    <p id="ajustar-endereco" class="text-muted" style="font-size: var(--text-xs); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        Mova o mapa para posicionar o crosshair
                    </p>
                    <p id="ajustar-coords" class="text-mono" style="font-size: var(--text-xs); color: var(--accent-primary); margin-top: 2px;"></p>
                </div>
            </div>
            <div style="display: flex; gap: var(--space-2); margin-top: var(--space-3);">
                <button id="btn-confirmar-ajuste" class="btn btn-success" style="flex: 1;">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Salvar
                </button>
                <a href="{{ route('pontos.index') }}" class="btn btn-secondary">
                    Cancelar
                </a>
            </div>
        </div>
    </div>
    @endif

    <div id="app-toast" class="toast"></div>

    @stack('scripts')

    <script>
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('{{ asset("sw.js") }}', { scope: '{{ asset("/") }}' })
            .then(function(reg) {
                setInterval(function() { reg.update(); }, 1800000);
                var refreshing = false;
                navigator.serviceWorker.addEventListener('controllerchange', function() {
                    if (!refreshing) { refreshing = true; window.location.reload(); }
                });
            });
    }
    </script>
</body>
</html>
