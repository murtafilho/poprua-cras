<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-base" content="{{ rtrim(url('/'), '/') }}">
    <meta name="theme-color" content="#184186">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.brand', 'SIZEM BH') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192x192.png') }}">

    <title>{{ config('app.brand', 'SIZEM BH') }} — @yield('title', 'Sistema')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .fullscreen-main {
            height: 100vh;
            overflow: hidden;
        }
        .fullscreen-content {
            width: 100%;
            height: 100%;
        }
        .fullscreen-content iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body @class(['has-homolog-banner' => config('app.homologacao_banner')])>
    <x-homologacao-banner />
    <div id="app">
        {{-- Sidebar Overlay --}}
        @include('layouts.partials.sidebar')


        {{-- Main Content (no header) --}}
        <div class="main-wrapper">
            <main class="fullscreen-main">
                @yield('content')
            </main>
        </div>
    </div>

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
