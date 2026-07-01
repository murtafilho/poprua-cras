<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#184186">

        <title>{{ config('app.brand', 'SIZEM BH') }}</title>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body @class(['has-homolog-banner' => config('app.homologacao_banner')])>
        <x-homologacao-banner />
        <div class="guest-container">
            <div class="guest-logo">
                <a href="/">
                    <x-application-logo style="width: 64px; height: 64px; color: var(--accent-primary);" />
                </a>
            </div>

            <div class="card guest-card">
                <div class="card-body">
                    {{ $slot }}
                </div>
            </div>

            <p class="guest-footer">
                {{ config('app.brand', 'SIZEM BH') }} v2.0 &copy; {{ date('Y') }} — Prefeitura de Belo Horizonte
            </p>
        </div>
    </body>
</html>
