<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#184186">
    <meta name="description" content="SIZEM — Sistema Integrado de Zeladoria Municipal. Alinhado à ADPF 976 / PNPSR. Adaptado a Belo Horizonte.">
    <title>{{ $brand }} — Home</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="home-page">
    <a href="#home-main" class="skip-link">Pular para o conteúdo</a>

    <main id="home-main" class="home-shell">
        <header class="home-brand">
            <x-application-logo class="home-logo" alt="{{ config('app.brand', 'SIZEM') }}" />
            <h1 class="home-title">{{ $brand }}</h1>
            <p class="home-subtitle">Sistema Integrado de Zeladoria Municipal</p>
            <p class="home-version" id="home-version"
               data-versao-web="{{ $version }}"
               title="{{ $versionDetalhe }}">{{ $version }}</p>
            <div class="home-rule" aria-hidden="true"></div>
            <p class="home-seal">ADPF 976 · PNPSR</p>
        </header>

        <section class="home-compliance" aria-label="Conformidade normativa">
            <p>
                Instrumento municipal de registro e diagnóstico de zeladoria,
                alinhado à <strong>ADPF 976</strong> / <strong>PNPSR</strong>
                (Decreto nº 7.053/2009).
            </p>
            <p>
                Adaptado ao município de <strong>Belo Horizonte</strong>
                (Portaria Conjunta nº 009/2026).
            </p>
        </section>

        @auth
            <nav class="home-launcher" aria-label="Funções do sistema">
                <a href="{{ route('mapa.index') }}" class="home-tile">
                    <svg class="home-tile-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                    </svg>
                    <span class="home-tile-label">Mapa</span>
                </a>
                <a href="{{ route('vistorias.index') }}" class="home-tile">
                    <svg class="home-tile-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                    </svg>
                    <span class="home-tile-label">Zeladorias</span>
                </a>
                <a href="{{ route('vistorias.minhas') }}" class="home-tile">
                    <svg class="home-tile-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    <span class="home-tile-label">Minhas</span>
                </a>
                <a href="{{ route('pontos.index') }}" class="home-tile">
                    <svg class="home-tile-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="home-tile-label">Pontos</span>
                </a>
                <a href="{{ route('moradores.index') }}" class="home-tile">
                    <svg class="home-tile-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span class="home-tile-label">Pessoas</span>
                </a>
                <a href="{{ route('dashboard') }}" class="home-tile">
                    <svg class="home-tile-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    <span class="home-tile-label">Dashboard</span>
                </a>
            </nav>
        @else
            <div class="home-actions">
                <a href="{{ route('login') }}" class="btn btn-primary btn-lg home-login-btn">
                    Entrar
                </a>
            </div>
        @endauth

        <footer class="home-footer">
            <p>Prefeitura de Belo Horizonte · GINFI / SUFIS</p>
            <nav class="home-footer-links" aria-label="Informações">
                @auth
                    <a href="{{ route('sobre.index') }}" class="home-footer-link">Sobre o sistema</a>
                @endauth
            </nav>
            @auth
                <form method="POST" action="{{ route('logout') }}" class="home-logout">
                    @csrf
                    <button type="submit" class="home-logout-btn">Sair</button>
                </form>
            @endauth
        </footer>
    </main>

    <script>
        // Dentro do app de campo, a MainActivity injeta a versao do APK instalado.
        // Ela chega depois do HTML, entao o rotulo comeca com a versao publicada
        // (data + commit) e e completado quando o valor aparece — no navegador
        // nada acontece e o rotulo permanece o da web.
        (function () {
            var el = document.getElementById('home-version');
            if (!el) return;

            function aplicar() {
                var apk = window.__sizemAppVersao;
                if (!apk) return false;
                // No app, a versao do APK e a informacao principal; a data sai da
                // linha (fica no title) para o rotulo nao virar uma frase.
                var partes = el.dataset.versaoWeb.split(' · ');
                var commit = partes[partes.length - 1];
                el.textContent = 'v' + apk + ' · ' + commit;
                el.title = 'APK ' + apk + ' · ' + el.title;
                return true;
            }

            if (aplicar()) return;
            document.addEventListener('sizem:app-versao', aplicar, { once: true });
            // Rede de seguranca: se o evento vier antes deste script, o valor ja
            // esta na window; se vier depois do listener, o evento resolve.
            setTimeout(aplicar, 1500);
        })();
    </script>
</body>
</html>
