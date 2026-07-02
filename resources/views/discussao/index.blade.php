<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SIZEM &mdash; Documento de Discussao</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&family=Source+Serif+4:ital,opsz,wght@0,8..60,300;0,8..60,400;0,8..60,600;0,8..60,700;1,8..60,400&family=IBM+Plex+Sans:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --ink: #1a1a2e;
            --ink-light: #2d2d44;
            --ink-muted: #5c5c7a;
            --ink-faint: #8888a4;
            --parchment: #faf8f4;
            --parchment-warm: #f5f0e8;
            --parchment-dark: #ede7db;
            --gold: #b8860b;
            --gold-light: #d4a843;
            --gold-faint: rgba(184, 134, 11, 0.08);
            --crimson: #8b1a2b;
            --crimson-light: #a82d40;
            --forest: #1a5c3a;
            --rule: #c9c0b0;
            --rule-light: #ddd6c9;
            --serif: 'Source Serif 4', 'Georgia', 'Times New Roman', serif;
            --display: 'Playfair Display', 'Georgia', serif;
            --sans: 'IBM Plex Sans', 'Helvetica Neue', sans-serif;
            --mono: 'JetBrains Mono', 'Consolas', monospace;
        }

        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        html {
            font-size: 17px;
            scroll-behavior: smooth;
            scroll-padding-top: 80px;
        }

        body {
            font-family: var(--serif);
            color: var(--ink);
            background: var(--parchment);
            line-height: 1.75;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        /* ── Grain overlay ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 9999;
            opacity: 0.025;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        /* ── Top bar ── */
        .top-bar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--ink);
            border-bottom: 3px solid var(--gold);
            padding: 0 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 56px;
        }

        .top-bar-brand {
            font-family: var(--sans);
            font-weight: 600;
            font-size: 0.75rem;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--gold-light);
        }

        .top-bar-label {
            font-family: var(--sans);
            font-weight: 400;
            font-size: 0.7rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.45);
        }

        .top-bar-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn-print {
            font-family: var(--sans);
            font-size: 0.7rem;
            font-weight: 500;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.6);
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.12);
            padding: 0.4rem 1rem;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-print:hover {
            color: var(--gold-light);
            border-color: var(--gold);
            background: rgba(184, 134, 11, 0.1);
        }

        /* ── Layout ── */
        .page-wrapper {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: calc(100vh - 56px);
        }

        /* ── Sidebar TOC ── */
        .toc-sidebar {
            position: sticky;
            top: 56px;
            height: calc(100vh - 56px);
            overflow-y: auto;
            background: var(--parchment-warm);
            border-right: 1px solid var(--rule);
            padding: 2rem 1.5rem;
        }

        .toc-sidebar::-webkit-scrollbar { width: 4px; }
        .toc-sidebar::-webkit-scrollbar-track { background: transparent; }
        .toc-sidebar::-webkit-scrollbar-thumb { background: var(--rule); border-radius: 2px; }

        .toc-title {
            font-family: var(--sans);
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--ink-muted);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--rule);
        }

        .toc-list {
            list-style: none;
        }

        .toc-list li {
            margin-bottom: 0.15rem;
        }

        .toc-link {
            display: block;
            font-family: var(--sans);
            font-size: 0.78rem;
            font-weight: 400;
            color: var(--ink-muted);
            text-decoration: none;
            padding: 0.35rem 0.6rem;
            border-radius: 3px;
            border-left: 2px solid transparent;
            transition: all 0.2s;
            line-height: 1.4;
        }

        .toc-link:hover {
            color: var(--ink);
            background: var(--gold-faint);
            border-left-color: var(--gold);
        }

        .toc-link.toc-l1 {
            font-weight: 600;
            color: var(--ink-light);
            margin-top: 0.6rem;
        }

        .toc-link.toc-l2 {
            padding-left: 1.2rem;
            font-size: 0.73rem;
        }

        .toc-link .toc-num {
            font-family: var(--mono);
            font-size: 0.65rem;
            color: var(--gold);
            margin-right: 0.4rem;
        }

        /* ── Main content ── */
        .content-area {
            padding: 3rem 4rem 6rem;
            max-width: 860px;
        }

        /* ── Document header ── */
        .doc-header {
            text-align: center;
            margin-bottom: 3rem;
            padding-bottom: 2.5rem;
            border-bottom: 2px solid var(--ink);
            position: relative;
        }

        .doc-header::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--ink);
        }

        .doc-coat {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .doc-coat-line {
            width: 60px;
            height: 1px;
            background: var(--gold);
        }

        .doc-coat-emblem {
            width: 48px;
            height: 48px;
            border: 2px solid var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--display);
            font-weight: 800;
            font-size: 1.1rem;
            color: var(--gold);
        }

        .doc-institution {
            font-family: var(--sans);
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.25em;
            text-transform: uppercase;
            color: var(--ink-muted);
            margin-bottom: 0.3rem;
        }

        .doc-subtitle {
            font-family: var(--sans);
            font-size: 0.6rem;
            font-weight: 400;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--ink-faint);
            margin-bottom: 2rem;
        }

        .doc-title {
            font-family: var(--display);
            font-size: 2rem;
            font-weight: 700;
            color: var(--ink);
            line-height: 1.2;
            margin-bottom: 0.5rem;
        }

        .doc-title-sub {
            font-family: var(--display);
            font-size: 1.15rem;
            font-weight: 400;
            font-style: italic;
            color: var(--ink-muted);
            margin-bottom: 1.5rem;
        }

        .doc-meta {
            display: flex;
            justify-content: center;
            gap: 2.5rem;
            flex-wrap: wrap;
        }

        .doc-meta-item {
            font-family: var(--sans);
            font-size: 0.7rem;
            color: var(--ink-faint);
        }

        .doc-meta-item strong {
            color: var(--ink-muted);
            font-weight: 600;
        }

        /* ── Sections ── */
        .titulo {
            margin-top: 3.5rem;
            margin-bottom: 2rem;
        }

        .titulo-header {
            display: flex;
            align-items: baseline;
            gap: 0.75rem;
            margin-bottom: 0.3rem;
        }

        .titulo-num {
            font-family: var(--sans);
            font-size: 0.65rem;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--gold);
            white-space: nowrap;
        }

        .titulo-rule {
            flex: 1;
            height: 1px;
            background: var(--rule);
        }

        .titulo-name {
            font-family: var(--display);
            font-size: 1.55rem;
            font-weight: 700;
            color: var(--ink);
            line-height: 1.3;
            margin-bottom: 0.2rem;
        }

        .titulo-desc {
            font-family: var(--serif);
            font-size: 0.9rem;
            font-style: italic;
            color: var(--ink-muted);
            margin-bottom: 1.5rem;
        }

        /* ── Capitulos ── */
        .capitulo {
            margin-top: 2.5rem;
            margin-bottom: 1.5rem;
        }

        .capitulo-header {
            font-family: var(--sans);
            font-size: 0.6rem;
            font-weight: 600;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--crimson);
            margin-bottom: 0.2rem;
        }

        .capitulo-name {
            font-family: var(--display);
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--ink-light);
            margin-bottom: 0.3rem;
            line-height: 1.35;
        }

        .capitulo-divider {
            width: 40px;
            height: 2px;
            background: var(--gold);
            margin-bottom: 1rem;
        }

        /* ── Artigos ── */
        .artigo {
            margin-bottom: 1.5rem;
            padding-left: 1.5rem;
            position: relative;
        }

        .artigo::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.4rem;
            bottom: 0.4rem;
            width: 2px;
            background: var(--rule-light);
        }

        .artigo-num {
            font-family: var(--sans);
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--gold);
        }

        .artigo p {
            margin-bottom: 0.6rem;
            text-align: justify;
            hyphens: auto;
        }

        /* ── Inciso / Alinea ── */
        .incisos {
            list-style: none;
            margin: 0.5rem 0 0.8rem 0;
        }

        .incisos > li {
            padding-left: 1.8rem;
            position: relative;
            margin-bottom: 0.4rem;
            text-align: justify;
        }

        .incisos > li::before {
            content: attr(data-num);
            position: absolute;
            left: 0;
            font-family: var(--sans);
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--ink-muted);
        }

        .alineas {
            list-style: none;
            margin: 0.3rem 0 0.5rem 0;
        }

        .alineas > li {
            padding-left: 1.4rem;
            position: relative;
            margin-bottom: 0.3rem;
        }

        .alineas > li::before {
            content: attr(data-let);
            position: absolute;
            left: 0;
            font-family: var(--sans);
            font-weight: 500;
            font-size: 0.8rem;
            color: var(--ink-faint);
        }

        /* ── Tables ── */
        .tabela-wrapper {
            margin: 1.2rem 0;
            overflow-x: auto;
        }

        .tabela {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
        }

        .tabela thead th {
            font-family: var(--sans);
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--parchment);
            background: var(--ink);
            padding: 0.6rem 0.8rem;
            text-align: left;
            border: 1px solid var(--ink-light);
        }

        .tabela tbody td {
            padding: 0.55rem 0.8rem;
            border: 1px solid var(--rule);
            vertical-align: top;
        }

        .tabela tbody tr:nth-child(even) {
            background: var(--gold-faint);
        }

        .tabela tbody tr:hover {
            background: rgba(184, 134, 11, 0.06);
        }

        .tabela .col-norma {
            font-family: var(--sans);
            font-weight: 600;
            font-size: 0.82rem;
            color: var(--ink-light);
            white-space: nowrap;
        }

        /* ── Callout boxes ── */
        .callout {
            margin: 1.5rem 0;
            padding: 1.2rem 1.5rem;
            border-radius: 3px;
            position: relative;
        }

        .callout::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            border-radius: 3px 0 0 3px;
        }

        .callout-gold {
            background: var(--gold-faint);
            border: 1px solid rgba(184, 134, 11, 0.15);
        }

        .callout-gold::before { background: var(--gold); }

        .callout-crimson {
            background: rgba(139, 26, 43, 0.04);
            border: 1px solid rgba(139, 26, 43, 0.12);
        }

        .callout-crimson::before { background: var(--crimson); }

        .callout-forest {
            background: rgba(26, 92, 58, 0.04);
            border: 1px solid rgba(26, 92, 58, 0.12);
        }

        .callout-forest::before { background: var(--forest); }

        .callout-title {
            font-family: var(--sans);
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin-bottom: 0.4rem;
        }

        .callout-gold .callout-title { color: var(--gold); }
        .callout-crimson .callout-title { color: var(--crimson); }
        .callout-forest .callout-title { color: var(--forest); }

        .callout p {
            font-size: 0.9rem;
            line-height: 1.65;
        }

        /* ── Paragrafo unico / caput ── */
        .paragrafo-unico {
            font-style: italic;
            color: var(--ink-muted);
            margin-top: 0.4rem;
        }

        .paragrafo-unico strong {
            font-style: normal;
            color: var(--ink-light);
        }

        /* ── Links ── */
        a.ref-link {
            color: var(--crimson);
            text-decoration: none;
            border-bottom: 1px solid rgba(139, 26, 43, 0.25);
            transition: all 0.2s;
        }

        a.ref-link:hover {
            color: var(--crimson-light);
            border-bottom-color: var(--crimson-light);
        }

        /* ── Footer ── */
        .doc-footer {
            margin-top: 4rem;
            padding-top: 2rem;
            border-top: 2px solid var(--ink);
            position: relative;
        }

        .doc-footer::before {
            content: '';
            position: absolute;
            top: -5px;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--ink);
        }

        .doc-footer-label {
            font-family: var(--sans);
            font-size: 0.6rem;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--ink-muted);
            margin-bottom: 1rem;
        }

        .fontes-list {
            list-style: none;
            columns: 1;
        }

        .fontes-list li {
            font-size: 0.8rem;
            margin-bottom: 0.5rem;
            padding-left: 1rem;
            position: relative;
            line-height: 1.5;
            break-inside: avoid;
        }

        .fontes-list li::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.55rem;
            width: 4px;
            height: 4px;
            background: var(--gold);
            border-radius: 50%;
        }

        .fontes-list li a {
            color: var(--crimson);
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: border-color 0.2s;
        }

        .fontes-list li a:hover {
            border-bottom-color: var(--crimson);
        }

        .doc-end-mark {
            text-align: center;
            margin-top: 3rem;
            padding: 1.5rem 0;
        }

        .doc-end-mark span {
            font-family: var(--sans);
            font-size: 0.6rem;
            font-weight: 600;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            color: var(--ink-faint);
            padding: 0 1.5rem;
            position: relative;
        }

        .doc-end-mark span::before,
        .doc-end-mark span::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 30px;
            height: 1px;
            background: var(--rule);
        }

        .doc-end-mark span::before { right: 100%; }
        .doc-end-mark span::after { left: 100%; }

        /* ── Comparison matrix ── */
        .tabela-matrix thead th {
            font-size: 0.62rem;
            padding: 0.5rem 0.5rem;
            text-align: center;
            vertical-align: bottom;
            line-height: 1.3;
        }

        .tabela-matrix thead th:first-child {
            text-align: left;
            min-width: 140px;
        }

        .tabela-matrix tbody td {
            text-align: center;
            padding: 0.45rem 0.5rem;
            font-size: 0.82rem;
        }

        .tabela-matrix tbody td:first-child {
            text-align: left;
            font-family: var(--sans);
            font-weight: 500;
            font-size: 0.78rem;
            color: var(--ink-light);
        }

        .tabela-matrix .col-sizem {
            background: rgba(184, 134, 11, 0.06) !important;
            border-left: 2px solid var(--gold) !important;
            border-right: 2px solid var(--gold) !important;
        }

        .tabela-matrix thead .col-sizem {
            background: var(--gold) !important;
            color: #fff !important;
            border-left: 2px solid var(--gold) !important;
            border-right: 2px solid var(--gold) !important;
        }

        .ico-sim, .ico-nao, .ico-parcial {
            display: inline-block;
            width: 20px;
            height: 20px;
            line-height: 20px;
            border-radius: 50%;
            font-family: var(--sans);
            font-weight: 700;
            font-size: 0.65rem;
            text-align: center;
        }

        .ico-sim {
            background: rgba(26, 92, 58, 0.12);
            color: var(--forest);
        }

        .ico-nao {
            background: rgba(139, 26, 43, 0.08);
            color: var(--crimson);
        }

        .ico-parcial {
            background: rgba(184, 134, 11, 0.12);
            color: var(--gold);
        }

        .sistema-card {
            margin-bottom: 1.5rem;
            padding: 1.2rem 1.5rem;
            background: var(--parchment-warm);
            border: 1px solid var(--rule-light);
            border-radius: 4px;
            position: relative;
        }

        .sistema-card-header {
            display: flex;
            align-items: baseline;
            gap: 0.6rem;
            margin-bottom: 0.5rem;
        }

        .sistema-card-badge {
            font-family: var(--mono);
            font-size: 0.6rem;
            font-weight: 500;
            padding: 0.15rem 0.5rem;
            border-radius: 2px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            white-space: nowrap;
        }

        .badge-comercial { background: rgba(139, 26, 43, 0.08); color: var(--crimson); }
        .badge-opensource { background: rgba(26, 92, 58, 0.08); color: var(--forest); }
        .badge-governo { background: rgba(184, 134, 11, 0.1); color: var(--gold); }

        .sistema-card-name {
            font-family: var(--display);
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--ink-light);
        }

        .sistema-card-origin {
            font-family: var(--sans);
            font-size: 0.75rem;
            color: var(--ink-faint);
            margin-bottom: 0.6rem;
        }

        .sistema-card p {
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .sistema-card .feat-list {
            list-style: none;
            margin: 0.4rem 0 0 0;
        }

        .sistema-card .feat-list li {
            padding-left: 1.2rem;
            position: relative;
            font-size: 0.88rem;
            margin-bottom: 0.25rem;
        }

        .sistema-card .feat-list li::before {
            content: '\2014';
            position: absolute;
            left: 0;
            color: var(--gold);
            font-weight: 700;
        }

        /* ── Responsive ── */
        /* ── Toggle e overlay ── */
        .toc-toggle {
            display: none;
            background: none;
            border: none;
            color: rgba(255,255,255,0.7);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .toc-toggle:hover {
            color: var(--gold-light);
            background: rgba(255,255,255,0.08);
        }

        .toc-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 90;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .toc-overlay.active {
            display: block;
            opacity: 1;
        }

        @media (max-width: 900px) {
            .toc-toggle {
                display: flex;
            }

            .page-wrapper {
                grid-template-columns: 1fr;
            }

            .toc-sidebar {
                position: fixed;
                top: 0;
                left: 0;
                width: 280px;
                height: 100vh;
                z-index: 95;
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
                border-right: 3px solid var(--gold);
                padding-top: 1rem;
            }

            .toc-sidebar.open {
                transform: translateX(0);
            }

            .content-area {
                padding: 2rem 1.5rem 4rem;
            }
            .doc-title {
                font-size: 1.5rem;
            }
            .doc-meta {
                gap: 1rem;
            }
        }

        /* ── Print ── */
        @media print {
            .top-bar, .toc-sidebar { display: none; }
            body::before { display: none; }
            .page-wrapper { grid-template-columns: 1fr; }
            .content-area { padding: 0; max-width: 100%; }
            .titulo { break-before: page; }
            .artigo { break-inside: avoid; }
            html { font-size: 11pt; }
        }

        /* ── Scroll animation ── */
        .fade-in {
            opacity: 0;
            transform: translateY(12px);
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>

    {{-- Overlay do menu mobile --}}
    <div class="toc-overlay" id="toc-overlay"></div>

    {{-- Top Bar --}}
    <header class="top-bar">
        <div style="display: flex; align-items: center; gap: 0.75rem;">
            <button class="toc-toggle" id="toc-toggle" aria-label="Abrir indice">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <div>
                <span class="top-bar-brand">SIZEM</span>
                <span class="top-bar-label" style="margin-left: 0.75rem;">Documento de Discussao</span>
            </div>
        </div>
        <div class="top-bar-actions">
            <button class="btn-print" x-on:click="window.print()">Imprimir</button>
        </div>
    </header>

    <div class="page-wrapper">

        {{-- Table of Contents --}}
        <nav class="toc-sidebar">
            <div class="toc-title">Indice</div>
            <ol class="toc-list">
                <li><a href="#titulo-i" class="toc-link toc-l1"><span class="toc-num">I</span>Sistemas de Identificacao no Mundo</a></li>
                <li><a href="#cap-i-1" class="toc-link toc-l2"><span class="toc-num">1.1</span>HMIS (EUA)</a></li>
                <li><a href="#cap-i-2" class="toc-link toc-l2"><span class="toc-num">1.2</span>Paises Nordicos</a></li>
                <li><a href="#cap-i-3" class="toc-link toc-l2"><span class="toc-num">1.3</span>Finlandia &mdash; Housing First</a></li>
                <li><a href="#cap-i-4" class="toc-link toc-l2"><span class="toc-num">1.4</span>Dados Administrativos (OCDE)</a></li>

                <li><a href="#titulo-ii" class="toc-link toc-l1"><span class="toc-num">II</span>Exigencia de Identificacao</a></li>
                <li><a href="#cap-ii-1" class="toc-link toc-l2"><span class="toc-num">2.1</span>Barreiras Documentais</a></li>
                <li><a href="#cap-ii-2" class="toc-link toc-l2"><span class="toc-num">2.2</span>Framework Legal (EUA)</a></li>
                <li><a href="#cap-ii-3" class="toc-link toc-l2"><span class="toc-num">2.3</span>Principio Geral</a></li>

                <li><a href="#titulo-iii" class="toc-link toc-l1"><span class="toc-num">III</span>Protecao de Dados e Etica</a></li>
                <li><a href="#cap-iii-1" class="toc-link toc-l2"><span class="toc-num">3.1</span>GDPR e Populacoes Vulneraveis</a></li>
                <li><a href="#cap-iii-2" class="toc-link toc-l2"><span class="toc-num">3.2</span>Principios Eticos</a></li>

                <li><a href="#titulo-iv" class="toc-link toc-l1"><span class="toc-num">IV</span>Migracao e Intermunicipalidade</a></li>
                <li><a href="#cap-iv-1" class="toc-link toc-l2"><span class="toc-num">4.1</span>Desafio da Mobilidade</a></li>
                <li><a href="#cap-iv-2" class="toc-link toc-l2"><span class="toc-num">4.2</span>Contexto Brasileiro</a></li>
                <li><a href="#cap-iv-3" class="toc-link toc-l2"><span class="toc-num">4.3</span>Estrutura de Atendimento</a></li>
                <li><a href="#cap-iv-4" class="toc-link toc-l2"><span class="toc-num">4.4</span>Regras do CadUnico</a></li>

                <li><a href="#titulo-v" class="toc-link toc-l1"><span class="toc-num">V</span>Casos de Uso e Referencias</a></li>
                <li><a href="#cap-v-1" class="toc-link toc-l2"><span class="toc-num">5.1</span>Modelos Internacionais</a></li>
                <li><a href="#cap-v-2" class="toc-link toc-l2"><span class="toc-num">5.2</span>Posicionamento do SIZEM</a></li>

                <li><a href="#titulo-vi" class="toc-link toc-l1"><span class="toc-num">VI</span>Legislacao Brasileira</a></li>
                <li><a href="#cap-vi-1" class="toc-link toc-l2"><span class="toc-num">6.1</span>Base Constitucional</a></li>
                <li><a href="#cap-vi-2" class="toc-link toc-l2"><span class="toc-num">6.2</span>Legislacao Estruturante</a></li>
                <li><a href="#cap-vi-3" class="toc-link toc-l2"><span class="toc-num">6.3</span>Saude</a></li>
                <li><a href="#cap-vi-4" class="toc-link toc-l2"><span class="toc-num">6.4</span>Assistencia Social (SUAS)</a></li>
                <li><a href="#cap-vi-5" class="toc-link toc-l2"><span class="toc-num">6.5</span>Cadastro e Identificacao</a></li>
                <li><a href="#cap-vi-6" class="toc-link toc-l2"><span class="toc-num">6.6</span>Plano Ruas Visiveis</a></li>
                <li><a href="#cap-vi-7" class="toc-link toc-l2"><span class="toc-num">6.7</span>Principios para Sistemas</a></li>

                <li><a href="#titulo-vii" class="toc-link toc-l1"><span class="toc-num">VII</span>Sistemas de Coleta em Campo</a></li>
                <li><a href="#cap-vii-1" class="toc-link toc-l2"><span class="toc-num">7.1</span>Clarity Human Services</a></li>
                <li><a href="#cap-vii-2" class="toc-link toc-l2"><span class="toc-num">7.2</span>Counting Us</a></li>
                <li><a href="#cap-vii-3" class="toc-link toc-l2"><span class="toc-num">7.3</span>Censo PopRua Rio (Survey123)</a></li>
                <li><a href="#cap-vii-4" class="toc-link toc-l2"><span class="toc-num">7.4</span>KoboToolbox / ODK Collect</a></li>
                <li><a href="#cap-vii-5" class="toc-link toc-l2"><span class="toc-num">7.5</span>Outros Sistemas</a></li>
                <li><a href="#cap-vii-6" class="toc-link toc-l2"><span class="toc-num">7.6</span>Matriz Comparativa</a></li>
                <li><a href="#cap-vii-7" class="toc-link toc-l2"><span class="toc-num">7.7</span>Analise e Oportunidades</a></li>

                <li><a href="#fontes" class="toc-link toc-l1"><span class="toc-num">&sect;</span>Fontes e Referencias</a></li>
            </ol>
        </nav>

        {{-- Main Content --}}
        <article class="content-area">

            {{-- Document Header --}}
            <header class="doc-header fade-in">
                <div class="doc-coat">
                    <div class="doc-coat-line"></div>
                    <div class="doc-coat-emblem">P</div>
                    <div class="doc-coat-line"></div>
                </div>
                <div class="doc-institution">Roberto Murta</div>
                <div class="doc-subtitle">SIZEM</div>
                <h1 class="doc-title">Controle e Identificacao de<br>Moradores em Situacao de Rua</h1>
                <p class="doc-title-sub">Panorama mundial, marcos legais e fundamentos para<br>o desenvolvimento do sistema SIZEM</p>
                <div class="doc-meta">
                    <span class="doc-meta-item"><strong>Tipo:</strong> Documento de Discussao</span>
                    <span class="doc-meta-item"><strong>Data:</strong> Marco de 2026</span>
                    <span class="doc-meta-item"><strong>Versao:</strong> 1.0</span>
                </div>
            </header>

            {{-- ═══════════════════════════════════════ --}}
            {{-- TITULO I --}}
            {{-- ═══════════════════════════════════════ --}}
            <section class="titulo fade-in" id="titulo-i">
                <div class="titulo-header">
                    <span class="titulo-num">Titulo I</span>
                    <span class="titulo-rule"></span>
                </div>
                <h2 class="titulo-name">Dos Sistemas de Identificacao e Registro no Mundo</h2>
                <p class="titulo-desc">Abordagens internacionais para identificacao, contagem e acompanhamento de populacoes em situacao de rua.</p>

                {{-- Cap I.1 --}}
                <div class="capitulo" id="cap-i-1">
                    <div class="capitulo-header">Capitulo I &mdash; Secao 1</div>
                    <h3 class="capitulo-name">HMIS &mdash; Homeless Management Information System (Estados Unidos)</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 1&ordm;</span> &mdash; O sistema mais consolidado no mundo e o <strong>HMIS</strong> dos Estados Unidos, exigido pelo HUD (<em>Department of Housing and Urban Development</em>). Cada comunidade mantem um banco de dados local que registra informacoes sobre pessoas em situacao de rua que utilizam servicos.</p>
                    </div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 2&ordm;</span> &mdash; Sao caracteristicas fundamentais do HMIS:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;">A <strong>By-Name List</strong> (Lista Nominal): listas em tempo real com nome, dados pessoais e status de cada pessoa. Permite rastrear entrada e saida da situacao de rua e priorizar atendimento;</li>
                            <li data-num="II &mdash;">O <strong>Coordinated Entry</strong>: sistema de entrada coordenada onde qualquer ponto de atendimento acessa o mesmo banco de dados;</li>
                            <li data-num="III &mdash;">A disponibilizacao de <strong>dados em tempo real</strong>, em contraposicao as contagens pontuais (<em>Point-in-Time Count</em>).</li>
                        </ul>
                    </div>

                    <div class="callout callout-gold">
                        <div class="callout-title">Nota Tecnica</div>
                        <p>A qualidade dos dados melhora com entradas padronizadas e verificacoes para evitar erros e duplicacoes. Uma plataforma configuravel permite que todos na rede de provedores usem as mesmas avaliacoes.</p>
                    </div>
                </div>

                {{-- Cap I.2 --}}
                <div class="capitulo" id="cap-i-2">
                    <div class="capitulo-header">Capitulo I &mdash; Secao 2</div>
                    <h3 class="capitulo-name">Paises Nordicos (Suecia, Noruega, Dinamarca)</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 3&ordm;</span> &mdash; Suecia, Noruega e Dinamarca realizam contagens periodicas em <strong>duas etapas</strong>:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;">Primeiro, mapeiam todos os servicos de apoio existentes no territorio;</li>
                            <li data-num="II &mdash;">Em seguida, solicitam a esses servicos que conduzam uma pesquisa de seus usuarios durante uma semana especifica.</li>
                        </ul>
                        <p class="paragrafo-unico"><strong>Paragrafo unico.</strong> Sao incluidos nao apenas abrigos, mas centros de emprego, cafes comunitarios, servicos de saude e centros de tratamento de dependencia quimica.</p>
                    </div>
                </div>

                {{-- Cap I.3 --}}
                <div class="capitulo" id="cap-i-3">
                    <div class="capitulo-header">Capitulo I &mdash; Secao 3</div>
                    <h3 class="capitulo-name">Finlandia &mdash; Modelo Housing First</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 4&ordm;</span> &mdash; A Finlandia e referencia mundial com o modelo <strong>Housing First</strong> desde 2008, cuja principal diferenca e que a pessoa <strong>nao precisa se identificar com programa nenhum</strong> nem cumprir pre-requisitos para receber moradia.</p>
                    </div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 5&ordm;</span> &mdash; Sao resultados comprovados do modelo finlandes:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;">O contrato de locacao e <strong>permanente</strong>;</li>
                            <li data-num="II &mdash;">Reducao de <strong>76%</strong> em pessoas em albergues entre 2008 e 2017;</li>
                            <li data-num="III &mdash;">Economia de <strong>EUR 9.600 a 15.000 por ano por pessoa</strong> em gastos publicos;</li>
                            <li data-num="IV &mdash;">A Finlandia e o unico pais europeu com <strong>numeros decrescentes</strong> de pessoas em situacao de rua.</li>
                        </ul>
                    </div>

                    <div class="callout callout-forest">
                        <div class="callout-title">Referencia Internacional</div>
                        <p>A diferenca entre os projetos-piloto de outros paises e a politica finlandesa e que, na Finlandia, Housing First nao e um projeto &mdash; e uma <strong>mudanca integral da politica habitacional do pais</strong>. Escocia e Dinamarca adotaram expansoes similares.</p>
                    </div>
                </div>

                {{-- Cap I.4 --}}
                <div class="capitulo" id="cap-i-4">
                    <div class="capitulo-header">Capitulo I &mdash; Secao 4</div>
                    <h3 class="capitulo-name">Dados Administrativos (OCDE)</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 6&ordm;</span> &mdash; A OCDE recomenda o uso de <strong>dados administrativos cruzados</strong> (saude, justica, assistencia social, beneficios) para identificar populacoes ocultas.</p>
                        <p class="paragrafo-unico"><strong>Paragrafo unico.</strong> A limitacao deste metodo consiste em excluir quem nao esta em contato com servicos formais, nao sendo adequado para gerar compreensao abrangente da extensao da situacao de rua em areas com baixa cobertura de servicos.</p>
                    </div>
                </div>
            </section>

            {{-- ═══════════════════════════════════════ --}}
            {{-- TITULO II --}}
            {{-- ═══════════════════════════════════════ --}}
            <section class="titulo fade-in" id="titulo-ii">
                <div class="titulo-header">
                    <span class="titulo-num">Titulo II</span>
                    <span class="titulo-rule"></span>
                </div>
                <h2 class="titulo-name">Da Exigencia de Identificacao pelo Poder Publico</h2>
                <p class="titulo-desc">Barreiras documentais, marcos legais e principios de nao-condicionamento.</p>

                {{-- Cap II.1 --}}
                <div class="capitulo" id="cap-ii-1">
                    <div class="capitulo-header">Capitulo II &mdash; Secao 1</div>
                    <h3 class="capitulo-name">Das Barreiras Documentais</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 7&ordm;</span> &mdash; Pessoas em situacao de rua frequentemente <strong>nao possuem documentos de identificacao</strong> por dificuldade em manter documentos importantes. A falta de identificacao impede o acesso a emprego, moradia, beneficios e servicos.</p>
                    </div>
                </div>

                {{-- Cap II.2 --}}
                <div class="capitulo" id="cap-ii-2">
                    <div class="capitulo-header">Capitulo II &mdash; Secao 2</div>
                    <h3 class="capitulo-name">Do Framework Legal nos Estados Unidos</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 8&ordm;</span> &mdash; Diversos estados norte-americanos aprovaram leis para:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;"><strong>Eliminar ou reduzir taxas</strong> de emissao de documento de identidade para pessoas em situacao de rua;</li>
                            <li data-num="II &mdash;">Permitir <strong>formas alternativas de documentacao</strong>, como carta de provedor social ou abrigo, para comprovar residencia.</li>
                        </ul>
                    </div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 9&ordm;</span> &mdash; O <strong>Homeless Bill of Rights</strong>, tornado lei em Rhode Island, Connecticut e Illinois, afirma que pessoas em situacao de rua tem direitos iguais a:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;">Cuidados medicos e livre circulacao;</li>
                            <li data-num="II &mdash;">Voto e oportunidades de emprego;</li>
                            <li data-num="III &mdash;"><strong>Privacidade</strong> de informacoes pessoais.</li>
                        </ul>
                    </div>
                </div>

                {{-- Cap II.3 --}}
                <div class="capitulo" id="cap-ii-3">
                    <div class="capitulo-header">Capitulo II &mdash; Secao 3</div>
                    <h3 class="capitulo-name">Do Principio Geral</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 10.</span> &mdash; O orgao publico <strong>nao pode condicionar o atendimento</strong> a apresentacao de documentos de identificacao formal.</p>
                        <p class="paragrafo-unico"><strong>Paragrafo unico.</strong> O registro deve ser <strong>voluntario e nao-coercitivo</strong>, servindo como porta de entrada a direitos, nunca como mecanismo de controle ou vigilancia.</p>
                    </div>

                    <div class="callout callout-crimson">
                        <div class="callout-title">Atencao</div>
                        <p>Em 2015, o Departamento de Justica dos EUA apresentou manifestacao em tribunal federal argumentando que <strong>e inconstitucional criminalizar o ato de dormir em espacos publicos</strong> sem fornecer espacos adequados de abrigo na area.</p>
                    </div>
                </div>
            </section>

            {{-- ═══════════════════════════════════════ --}}
            {{-- TITULO III --}}
            {{-- ═══════════════════════════════════════ --}}
            <section class="titulo fade-in" id="titulo-iii">
                <div class="titulo-header">
                    <span class="titulo-num">Titulo III</span>
                    <span class="titulo-rule"></span>
                </div>
                <h2 class="titulo-name">Da Protecao de Dados e Etica</h2>
                <p class="titulo-desc">GDPR, LGPD e principios eticos aplicaveis ao tratamento de dados de populacoes vulneraveis.</p>

                {{-- Cap III.1 --}}
                <div class="capitulo" id="cap-iii-1">
                    <div class="capitulo-header">Capitulo III &mdash; Secao 1</div>
                    <h3 class="capitulo-name">Da GDPR e Populacoes Vulneraveis</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 11.</span> &mdash; Dados de pessoas em situacao de rua sao considerados <strong>altamente sensiveis</strong>, abrangendo informacoes sobre saude, etnia e situacao social.</p>
                    </div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 12.</span> &mdash; Organizacoes que apoiam pessoas em situacao de rua devem manter <strong>transparencia</strong> sobre o uso dos dados, sendo essencial para:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;">Garantir conformidade legal;</li>
                            <li data-num="II &mdash;">Construir e manter confianca com os usuarios dos servicos;</li>
                            <li data-num="III &mdash;">Empoderar os usuarios a exercer seus direitos.</li>
                        </ul>
                    </div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 13.</span> &mdash; Constituem categorias especiais de dados pessoais, exigindo protecao reforcada:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;">Origem racial ou etnica;</li>
                            <li data-num="II &mdash;">Dados de saude e biometria;</li>
                            <li data-num="III &mdash;">Opinioes politicas e crencas religiosas;</li>
                            <li data-num="IV &mdash;">Dados geneticos para identificacao de pessoa natural.</li>
                        </ul>
                    </div>
                </div>

                {{-- Cap III.2 --}}
                <div class="capitulo" id="cap-iii-2">
                    <div class="capitulo-header">Capitulo III &mdash; Secao 2</div>
                    <h3 class="capitulo-name">Dos Principios Eticos</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 14.</span> &mdash; Sao principios eticos inafastaveis no tratamento de dados de populacoes vulneraveis:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;"><strong>Consentimento informado:</strong> a pessoa deve compreender como seus dados serao utilizados;</li>
                            <li data-num="II &mdash;"><strong>Minimizacao de dados:</strong> coletar apenas o estritamente necessario;</li>
                            <li data-num="III &mdash;"><strong>Finalidade especifica:</strong> dados nao podem ser usados para criminalizacao ou controle policial.</li>
                        </ul>
                    </div>
                </div>
            </section>

            {{-- ═══════════════════════════════════════ --}}
            {{-- TITULO IV --}}
            {{-- ═══════════════════════════════════════ --}}
            <section class="titulo fade-in" id="titulo-iv">
                <div class="titulo-header">
                    <span class="titulo-num">Titulo IV</span>
                    <span class="titulo-rule"></span>
                </div>
                <h2 class="titulo-name">Da Migracao e Intermunicipalidade</h2>
                <p class="titulo-desc">Mobilidade territorial, contexto brasileiro e estrutura de atendimento.</p>

                {{-- Cap IV.1 --}}
                <div class="capitulo" id="cap-iv-1">
                    <div class="capitulo-header">Capitulo IV &mdash; Secao 1</div>
                    <h3 class="capitulo-name">Do Desafio da Mobilidade</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 15.</span> &mdash; Pessoas em situacao de rua frequentemente <strong>migram entre municipios</strong>, o que gera:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;">Dificuldade de acompanhamento longitudinal;</li>
                            <li data-num="II &mdash;">Duplicidade de cadastros entre cidades;</li>
                            <li data-num="III &mdash;">Perda de vinculo com servicos de referencia.</li>
                        </ul>
                    </div>
                </div>

                {{-- Cap IV.2 --}}
                <div class="capitulo" id="cap-iv-2">
                    <div class="capitulo-header">Capitulo IV &mdash; Secao 2</div>
                    <h3 class="capitulo-name">Do Contexto Brasileiro em Numeros</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 16.</span> &mdash; O cenario brasileiro apresenta as seguintes dimensoes:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;"><strong>62%</strong> da populacao de rua esta concentrada na regiao Sudeste;</li>
                            <li data-num="II &mdash;">O censo do IBGE usa <strong>domicilio como referencia</strong>, excluindo metodologicamente quem vive na rua;</li>
                            <li data-num="III &mdash;">O Ipea estima <strong>327.925 pessoas</strong> em situacao de rua no final de 2024 (crescimento de 25% em um ano);</li>
                            <li data-num="IV &mdash;">Apenas <strong>42% dos municipios</strong> registram a populacao de rua, revelando subcobertura persistente.</li>
                        </ul>
                    </div>
                </div>

                {{-- Cap IV.3 --}}
                <div class="capitulo" id="cap-iv-3">
                    <div class="capitulo-header">Capitulo IV &mdash; Secao 3</div>
                    <h3 class="capitulo-name">Da Estrutura Brasileira de Atendimento</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 17.</span> &mdash; A estrutura brasileira de atendimento a populacao em situacao de rua organiza-se pelos seguintes instrumentos:</p>

                        <div class="tabela-wrapper">
                            <table class="tabela">
                                <thead>
                                    <tr>
                                        <th>Instrumento</th>
                                        <th>Funcao</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="col-norma">CadUnico</td>
                                        <td>Cadastro federal para programas sociais. Desde 2010 inclui pessoas em situacao de rua.</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma">Centro POP</td>
                                        <td>Unidade municipal de referencia especializada (Protecao Social Especial de Media Complexidade).</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma">CIAMP-Rua</td>
                                        <td>Comite Intersetorial de Acompanhamento e Monitoramento da Politica Nacional.</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma">SUAS</td>
                                        <td>Sistema Unico de Assistencia Social &mdash; organiza os servicos por territorio.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Cap IV.4 --}}
                <div class="capitulo" id="cap-iv-4">
                    <div class="capitulo-header">Capitulo IV &mdash; Secao 4</div>
                    <h3 class="capitulo-name">Das Regras do CadUnico para Populacao de Rua</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 18.</span> &mdash; Sao regras especificas do CadUnico aplicaveis a populacao em situacao de rua:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;">A pessoa <strong>nao precisa de comprovante de residencia</strong> desde 2011 (inclusive para SUS);</li>
                            <li data-num="II &mdash;">E utilizado como endereco de referencia o <strong>endereco de um equipamento da rede socioassistencial</strong> (Centro POP, CREAS, abrigo);</li>
                            <li data-num="III &mdash;">Ha formulario suplementar especifico (Formulario Suplementar 2) para pessoa em situacao de rua;</li>
                            <li data-num="IV &mdash;">O cadastro e <strong>voluntario</strong> e serve como porta de entrada para Bolsa Familia, BPC e Tarifa Social.</li>
                        </ul>
                    </div>
                </div>
            </section>

            {{-- ═══════════════════════════════════════ --}}
            {{-- TITULO V --}}
            {{-- ═══════════════════════════════════════ --}}
            <section class="titulo fade-in" id="titulo-v">
                <div class="titulo-header">
                    <span class="titulo-num">Titulo V</span>
                    <span class="titulo-rule"></span>
                </div>
                <h2 class="titulo-name">Dos Casos de Uso e Referencias</h2>
                <p class="titulo-desc">Modelos internacionais comparados e posicionamento do sistema SIZEM.</p>

                {{-- Cap V.1 --}}
                <div class="capitulo" id="cap-v-1">
                    <div class="capitulo-header">Capitulo V &mdash; Secao 1</div>
                    <h3 class="capitulo-name">Dos Modelos Internacionais Comparados</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 19.</span> &mdash; Os principais modelos internacionais e sua aplicabilidade sao:</p>

                        <div class="tabela-wrapper">
                            <table class="tabela">
                                <thead>
                                    <tr>
                                        <th>Modelo</th>
                                        <th>Pais</th>
                                        <th>Aplicabilidade</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="col-norma">HMIS + By-Name List</td>
                                        <td>EUA</td>
                                        <td>Sistema integrado com lista nominal em tempo real. Modelo mais proximo do que o SIZEM pode implementar.</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma">Housing First</td>
                                        <td>Finlandia</td>
                                        <td>Principio de moradia sem pre-requisitos. Referencia de politica publica.</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma">Contagem em 2 etapas</td>
                                        <td>Escandinavia</td>
                                        <td>Mapeamento de servicos + pesquisa coordenada.</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma">CadUnico + Centro POP</td>
                                        <td>Brasil</td>
                                        <td>Infraestrutura existente que o SIZEM complementa com dados geoespaciais.</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma">Dados Cruzados</td>
                                        <td>OCDE</td>
                                        <td>Integracao de dados de saude, justica e assistencia social.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Cap V.2 --}}
                <div class="capitulo" id="cap-v-2">
                    <div class="capitulo-header">Capitulo V &mdash; Secao 2</div>
                    <h3 class="capitulo-name">Do Posicionamento do SIZEM</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 20.</span> &mdash; O SIZEM complementa o CadUnico ao adicionar a <strong>dimensao geoespacial</strong> (pontos georreferenciados, bairros, regionais) que permite:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;">Visualizar <strong>concentracao territorial</strong> de moradores;</li>
                            <li data-num="II &mdash;">Acompanhar <strong>mobilidade entre pontos</strong> ao longo do tempo;</li>
                            <li data-num="III &mdash;">Vincular vistorias a <strong>localizacao geografica especifica</strong>;</li>
                            <li data-num="IV &mdash;">Cruzar dados com <strong>camadas de bairros e regionais</strong> (PostGIS).</li>
                        </ul>
                    </div>

                    <div class="callout callout-gold">
                        <div class="callout-title">Contribuicao do Sistema</div>
                        <p>O SIZEM nao substitui nenhum sistema federal existente. Atua como <strong>ferramenta complementar de inteligencia territorial</strong>, fornecendo ao municipio de Belo Horizonte capacidade de gestao geoespacial da politica publica voltada a populacao em situacao de rua.</p>
                    </div>
                </div>
            </section>

            {{-- ═══════════════════════════════════════ --}}
            {{-- TITULO VI --}}
            {{-- ═══════════════════════════════════════ --}}
            <section class="titulo fade-in" id="titulo-vi">
                <div class="titulo-header">
                    <span class="titulo-num">Titulo VI</span>
                    <span class="titulo-rule"></span>
                </div>
                <h2 class="titulo-name">Da Legislacao Pertinente no Brasil</h2>
                <p class="titulo-desc">Base constitucional, legislacao estruturante, saude, assistencia social, cadastro e principios orientadores.</p>

                {{-- Cap VI.1 --}}
                <div class="capitulo" id="cap-vi-1">
                    <div class="capitulo-header">Capitulo VI &mdash; Secao 1</div>
                    <h3 class="capitulo-name">Da Base Constitucional</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 21.</span> &mdash; A protecao da populacao em situacao de rua encontra fundamento nos seguintes dispositivos da Constituicao Federal de 1988:</p>

                        <div class="tabela-wrapper">
                            <table class="tabela">
                                <thead>
                                    <tr>
                                        <th>Dispositivo</th>
                                        <th>Conteudo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="col-norma">Art. 1&ordm;, III</td>
                                        <td>Principio da <strong>dignidade da pessoa humana</strong> como fundamento da Republica.</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma">Art. 5&ordm;</td>
                                        <td>Direitos fundamentais: igualdade, liberdade de locomocao, inviolabilidade da intimidade e privacidade.</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma">Art. 6&ordm;</td>
                                        <td>Direitos sociais: educacao, saude, alimentacao, trabalho, <strong>moradia</strong> (EC n&ordm; 26/2000), lazer, seguranca, previdencia social, assistencia aos desamparados.</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma">Art. 203</td>
                                        <td>Assistencia social prestada a quem dela necessitar, independentemente de contribuicao. Garante o <strong>BPC</strong> (Art. 203, V).</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma">Art. 204</td>
                                        <td>Descentralizacao politico-administrativa da assistencia social e participacao da populacao na formulacao de politicas.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Cap VI.2 --}}
                <div class="capitulo" id="cap-vi-2">
                    <div class="capitulo-header">Capitulo VI &mdash; Secao 2</div>
                    <h3 class="capitulo-name">Da Legislacao Federal Estruturante</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 22.</span> &mdash; Constituem a legislacao federal estruturante para a populacao em situacao de rua:</p>

                        <div class="tabela-wrapper">
                            <table class="tabela">
                                <thead>
                                    <tr>
                                        <th>Norma</th>
                                        <th>Descricao</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="col-norma"><a class="ref-link" href="https://www.planalto.gov.br/ccivil_03/leis/l8742.htm" target="_blank">Lei n&ordm; 8.742/1993 (LOAS)</a></td>
                                        <td>Lei Organica da Assistencia Social. Organiza a assistencia como direito do cidadao e dever do Estado. Institui o SUAS e o BPC (1 salario minimo para idosos 65+ e PcD em vulnerabilidade).</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma"><a class="ref-link" href="https://www.planalto.gov.br/ccivil_03/_ato2007-2010/2009/decreto/d7053.htm" target="_blank">Decreto n&ordm; 7.053/2009</a></td>
                                        <td>Institui a <strong>Politica Nacional para a Populacao em Situacao de Rua</strong> e o CIAMP-Rua. Define o conceito legal de &ldquo;populacao em situacao de rua&rdquo;.</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma"><a class="ref-link" href="https://www.planalto.gov.br/ccivil_03/_ato2023-2026/2024/Lei/L14821.htm" target="_blank">Lei n&ordm; 14.821/2024 (PNTC PopRua)</a></td>
                                        <td>Institui a <strong>Politica Nacional de Trabalho Digno e Cidadania</strong> para a populacao em situacao de rua. Promove qualificacao profissional, elevacao de escolaridade, insercao no mercado de trabalho. Cria o &ldquo;Selo Amigo PopRua&rdquo;.</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma"><a class="ref-link" href="https://www.planalto.gov.br/ccivil_03/_ato2019-2022/2022/lei/L14489.htm" target="_blank">Lei n&ordm; 14.489/2022</a></td>
                                        <td><strong>Lei Padre Julio Lancelotti.</strong> Altera o Estatuto da Cidade para proibir arquitetura hostil em espacos publicos destinada a afastar pessoas em situacao de rua.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Cap VI.3 --}}
                <div class="capitulo" id="cap-vi-3">
                    <div class="capitulo-header">Capitulo VI &mdash; Secao 3</div>
                    <h3 class="capitulo-name">Da Saude</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 23.</span> &mdash; No ambito da saude, sao normas fundamentais:</p>

                        <div class="tabela-wrapper">
                            <table class="tabela">
                                <thead>
                                    <tr>
                                        <th>Norma</th>
                                        <th>Descricao</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="col-norma"><a class="ref-link" href="https://bvsms.saude.gov.br/bvs/saudelegis/gm/2012/prt0122_25_01_2012.html" target="_blank">Portaria MS n&ordm; 122/2011</a></td>
                                        <td>Define diretrizes para as <strong>Equipes de Consultorio na Rua (eCR)</strong> &mdash; equipes multiprofissionais itinerantes que atuam in loco. Tres modalidades (I, II e III, sendo a III com medico). Integram a Rede de Atencao Psicossocial.</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma">Portaria MS n&ordm; 940/2011</td>
                                        <td>Garante acesso ao SUS <strong>sem exigencia de comprovante de residencia</strong> &mdash; marco fundamental para a populacao de rua.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Cap VI.4 --}}
                <div class="capitulo" id="cap-vi-4">
                    <div class="capitulo-header">Capitulo VI &mdash; Secao 4</div>
                    <h3 class="capitulo-name">Da Assistencia Social (SUAS)</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 24.</span> &mdash; No ambito do Sistema Unico de Assistencia Social:</p>

                        <div class="tabela-wrapper">
                            <table class="tabela">
                                <thead>
                                    <tr>
                                        <th>Norma</th>
                                        <th>Descricao</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="col-norma"><a class="ref-link" href="https://www.mds.gov.br/webarquivos/publicacao/assistencia_social/Normativas/tipificacao.pdf" target="_blank">Resolucao CNAS n&ordm; 109/2009</a></td>
                                        <td><strong>Tipificacao Nacional dos Servicos Socioassistenciais.</strong> Define o Servico Especializado para Pessoas em Situacao de Rua (Protecao Social Especial de Media Complexidade &mdash; <strong>Centro POP</strong>) e os Servicos de Acolhimento Institucional (Alta Complexidade).</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma">Resolucao CNAS n&ordm; 9/2013</td>
                                        <td>Ratifica e reconhece as ocupacoes e areas de ocupacao profissionais do SUAS.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Cap VI.5 --}}
                <div class="capitulo" id="cap-vi-5">
                    <div class="capitulo-header">Capitulo VI &mdash; Secao 5</div>
                    <h3 class="capitulo-name">Do Cadastro e Identificacao</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 25.</span> &mdash; Sobre cadastro e identificacao documental:</p>

                        <div class="tabela-wrapper">
                            <table class="tabela">
                                <thead>
                                    <tr>
                                        <th>Norma</th>
                                        <th>Descricao</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="col-norma"><a class="ref-link" href="https://www.planalto.gov.br/ccivil_03/_ato2007-2010/2007/decreto/d6135.htm" target="_blank">Decreto n&ordm; 6.135/2007</a></td>
                                        <td>Institui o <strong>Cadastro Unico (CadUnico)</strong> para Programas Sociais do Governo Federal.</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma">Instrucao Normativa MDS (2010)</td>
                                        <td>Inclusao da populacao em situacao de rua no CadUnico com formulario suplementar especifico. Permite uso de <strong>endereco de equipamento socioassistencial</strong> como referencia.</td>
                                    </tr>
                                    <tr>
                                        <td class="col-norma">PL 901/2024 (CDH/Senado)</td>
                                        <td>Torna <strong>prioritario e gratuito</strong> o atendimento para emissao de documentos pessoais. Comprovacao por <strong>autodeclaracao</strong> da condicao de pessoa em situacao de rua.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Cap VI.6 --}}
                <div class="capitulo" id="cap-vi-6">
                    <div class="capitulo-header">Capitulo VI &mdash; Secao 6</div>
                    <h3 class="capitulo-name">Do Plano Ruas Visiveis (2023&ndash;2027)</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 26.</span> &mdash; O Plano &ldquo;Ruas Visiveis &mdash; Pelo direito ao futuro da populacao em situacao de rua&rdquo;, lancado pelo Governo Federal em dezembro de 2023, com orcamento de <strong>R$ 1 bilhao</strong> e coordenacao entre 11 ministerios, estrutura-se nos seguintes eixos:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;"><strong>Eixo 1</strong> &mdash; Moradia, acolhimento e cuidado;</li>
                            <li data-num="II &mdash;"><strong>Eixo 2</strong> &mdash; Trabalho, renda e escolaridade;</li>
                            <li data-num="III &mdash;"><strong>Eixo 3</strong> &mdash; Seguranca alimentar e nutricional;</li>
                            <li data-num="IV &mdash;"><strong>Eixo 4</strong> &mdash; Direitos humanos e cidadania;</li>
                            <li data-num="V &mdash;"><strong>Eixo 5</strong> &mdash; Producao e gestao de dados (inclui o <strong>Censo Nacional da Populacao em Situacao de Rua</strong>).</li>
                        </ul>
                    </div>
                </div>

                {{-- Cap VI.7 --}}
                <div class="capitulo" id="cap-vi-7">
                    <div class="capitulo-header">Capitulo VI &mdash; Secao 7</div>
                    <h3 class="capitulo-name">Dos Principios Legais Aplicaveis a Sistemas como o SIZEM</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 27.</span> &mdash; Da legislacao vigente derivam os seguintes principios orientadores para qualquer sistema de cadastro e acompanhamento:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;"><strong>Nao condicionamento de atendimento a documentos</strong> &mdash; O acesso a servicos (saude, assistencia) nao pode ser condicionado a identificacao formal (Portaria 940/2011, Decreto 7.053/2009);</li>
                            <li data-num="II &mdash;"><strong>Cadastro voluntario</strong> &mdash; O registro no CadUnico e em sistemas locais e sempre voluntario e nao-coercitivo;</li>
                            <li data-num="III &mdash;"><strong>Endereco de referencia</strong> &mdash; A pessoa pode usar o endereco de um equipamento socioassistencial (Centro POP, CREAS, abrigo);</li>
                            <li data-num="IV &mdash;"><strong>Autodeclaracao</strong> &mdash; A condicao de pessoa em situacao de rua e reconhecida por autodeclaracao (PL 901/2024);</li>
                            <li data-num="V &mdash;"><strong>Protecao de dados sensiveis</strong> &mdash; Dados de saude, etnia e situacao social exigem protecao reforcada (LGPD, Lei 13.709/2018);</li>
                            <li data-num="VI &mdash;"><strong>Proibicao de uso para criminalizacao</strong> &mdash; Dados nao podem ser utilizados para acoes repressivas ou de remocao forcada;</li>
                            <li data-num="VII &mdash;"><strong>Descentralizacao</strong> &mdash; A politica e implementada de forma descentralizada (Uniao + entes federativos aderentes).</li>
                        </ul>
                    </div>

                    <div class="callout callout-crimson">
                        <div class="callout-title">Nota</div>
                        <p>Os principios acima orientam o desenvolvimento de todas as funcionalidades do SIZEM, incluindo a coleta de dados em campo analisada no Titulo VII a seguir.</p>
                    </div>
                </div>
            </section>

            {{-- ═══════════════════════════════════════ --}}
            {{-- TITULO VII --}}
            {{-- ═══════════════════════════════════════ --}}
            <section class="titulo fade-in" id="titulo-vii">
                <div class="titulo-header">
                    <span class="titulo-num">Titulo VII</span>
                    <span class="titulo-rule"></span>
                </div>
                <h2 class="titulo-name">Dos Sistemas de Coleta de Dados em Campo</h2>
                <p class="titulo-desc">Analise comparativa de sistemas similares ao SIZEM para coleta movel, georreferenciamento e gestao de populacao em situacao de rua.</p>

                {{-- Cap VII.1 --}}
                <div class="capitulo" id="cap-vii-1">
                    <div class="capitulo-header">Capitulo VII &mdash; Secao 1</div>
                    <h3 class="capitulo-name">Clarity Human Services / Outreach Toolkit (Bitfocus)</h3>
                    <div class="capitulo-divider"></div>

                    <div class="sistema-card">
                        <div class="sistema-card-header">
                            <span class="sistema-card-name">Clarity Human Services</span>
                            <span class="sistema-card-badge badge-comercial">Comercial</span>
                        </div>
                        <div class="sistema-card-origin">Bitfocus, Inc. &mdash; Estados Unidos &mdash; desde 2003</div>
                        <p>Plataforma HMIS mais avancada do mercado, com modulo de <strong>outreach georreferenciado</strong> construido sobre Esri ArcGIS. E o sistema internacional que mais se aproxima funcionalmente do SIZEM.</p>
                        <ul class="feat-list">
                            <li>App movel para smartphones e tablets com funcionalidade completa em campo</li>
                            <li><strong>Clarity Outreach</strong>: modulo de mapeamento de acampamentos com localizacao GPS em tempo real</li>
                            <li>Case management integrado &mdash; equipes de campo registram atendimentos no local</li>
                            <li>Coordinated Entry integrado ao HMIS nacional (HUD)</li>
                            <li>Rastreamento de By-Name List com status de cada pessoa atendida</li>
                            <li>Analise geoespacial para tomada de decisao por gestores locais</li>
                        </ul>
                    </div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 28.</span> &mdash; O Clarity Human Services representa o estado da arte em sistemas integrados de coleta em campo, combinando HMIS, mapeamento geoespacial e case management em uma unica plataforma. Sua principal limitacao e o <strong>custo de licenciamento comercial</strong> e a dependencia de infraestrutura Esri.</p>
                    </div>
                </div>

                {{-- Cap VII.2 --}}
                <div class="capitulo" id="cap-vii-2">
                    <div class="capitulo-header">Capitulo VII &mdash; Secao 2</div>
                    <h3 class="capitulo-name">Counting Us</h3>
                    <div class="capitulo-divider"></div>

                    <div class="sistema-card">
                        <div class="sistema-card-header">
                            <span class="sistema-card-name">Counting Us</span>
                            <span class="sistema-card-badge badge-comercial">Comercial</span>
                        </div>
                        <div class="sistema-card-origin">Simtech Solutions &mdash; Estados Unidos &mdash; usado em mais de 50 regioes</div>
                        <p>Aplicativo movel desenvolvido especificamente para o <strong>Point-in-Time Count</strong> anual, a contagem oficial de pessoas em situacao de rua exigida pelo HUD.</p>
                        <ul class="feat-list">
                            <li>Formularios conformes ao HUD (pesquisa abrigada e desabrigada)</li>
                            <li>GPS captura localizacao exata de cada interacao em campo</li>
                            <li><strong>Funciona 100% offline</strong> em areas sem internet, com sincronizacao posterior</li>
                            <li>Observation Tally para contagem quando a pessoa nao pode ser abordada</li>
                            <li>Dados enviados a um Regional Command Center para validacao</li>
                            <li>Integracao opcional com HMIS via nome e data de nascimento</li>
                            <li>Ferramentas de gestao de voluntarios e equipes de contagem</li>
                        </ul>
                    </div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 29.</span> &mdash; O Counting Us destaca-se pela <strong>capacidade offline</strong> e pela simplicidade de operacao por voluntarios nao-especializados. Nao possui, contudo, funcionalidades de acompanhamento longitudinal ou case management.</p>
                    </div>
                </div>

                {{-- Cap VII.3 --}}
                <div class="capitulo" id="cap-vii-3">
                    <div class="capitulo-header">Capitulo VII &mdash; Secao 3</div>
                    <h3 class="capitulo-name">Censo PopRua do Rio de Janeiro (ArcGIS Survey123)</h3>
                    <div class="capitulo-divider"></div>

                    <div class="sistema-card">
                        <div class="sistema-card-header">
                            <span class="sistema-card-name">Censo PopRua Rio</span>
                            <span class="sistema-card-badge badge-governo">Governo</span>
                        </div>
                        <div class="sistema-card-origin">Instituto Pereira Passos / SMASHDR &mdash; Prefeitura do Rio de Janeiro &mdash; 2020, 2022, 2024</div>
                        <p><strong>O caso brasileiro mais proximo do SIZEM.</strong> Desenvolvido com Survey123 for ArcGIS dentro do Sistema Municipal de Informacoes Urbanas (SIURB).</p>
                        <ul class="feat-list">
                            <li>App movel de <strong>coleta georreferenciada</strong> com formularios customizados</li>
                            <li>Permite participacao de grande numero de colaboradores simultaneamente</li>
                            <li>Amplia a capilaridade espacial das observacoes sobre distribuicao da populacao</li>
                            <li>Resultados publicados em <strong>ArcGIS Hub</strong> com dashboards interativos</li>
                            <li>Identificou 7.865 pessoas em 2022 (+8,5% vs 2020)</li>
                            <li>Metodologia publicada e replicavel por outros municipios</li>
                        </ul>
                    </div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 30.</span> &mdash; O modelo do Rio de Janeiro demonstra a viabilidade de <strong>coleta georreferenciada em campo por equipes municipais</strong> no contexto brasileiro. Sua limitacao principal e a <strong>dependencia de licencas Esri</strong> e a ausencia de acompanhamento longitudinal entre os censos.</p>
                    </div>

                    <div class="callout callout-gold">
                        <div class="callout-title">Referencia Nacional</div>
                        <p>O censo do Rio e o unico no Brasil que utiliza coleta georreferenciada movel em larga escala, com resultados publicados em plataforma aberta. BH pode adotar estrategia similar utilizando a infraestrutura PostGIS ja existente no SIZEM.</p>
                    </div>
                </div>

                {{-- Cap VII.4 --}}
                <div class="capitulo" id="cap-vii-4">
                    <div class="capitulo-header">Capitulo VII &mdash; Secao 4</div>
                    <h3 class="capitulo-name">KoboToolbox / ODK Collect</h3>
                    <div class="capitulo-divider"></div>

                    <div class="sistema-card">
                        <div class="sistema-card-header">
                            <span class="sistema-card-name">KoboToolbox</span>
                            <span class="sistema-card-badge badge-opensource">Open Source</span>
                        </div>
                        <div class="sistema-card-origin">Harvard Humanitarian Initiative &mdash; Internacional &mdash; usado por UNHCR, UNICEF, MSF</div>
                        <p>Ferramenta <strong>gratuita e open-source</strong> mais utilizada no mundo para coleta de dados em ambientes desafiadores. Baseada no Open Data Kit (ODK).</p>
                        <ul class="feat-list">
                            <li><strong>100% offline</strong> com sincronizacao posterior via wifi/dados moveis</li>
                            <li>Formularios com GPS, fotos, audio, logica condicional, validacao em campo</li>
                            <li>Interface em multiplos idiomas, inclusive portugues</li>
                            <li>Seguranca de dados sensiveis incorporada (criptografia)</li>
                            <li>Exportacao para Excel, CSV, GeoJSON, integracao com GIS</li>
                            <li>Usado em contextos de populacoes deslocadas, refugiados e emergencias humanitarias</li>
                            <li>Hospedagem propria possivel (self-hosted) para controle total dos dados</li>
                        </ul>
                    </div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 31.</span> &mdash; O KoboToolbox e a <strong>alternativa mais viavel para integracao imediata</strong> com o SIZEM, por ser gratuito, open-source, funcionar offline e exportar dados georreferenciados que podem ser importados diretamente para o PostGIS.</p>
                    </div>
                </div>

                {{-- Cap VII.5 --}}
                <div class="capitulo" id="cap-vii-5">
                    <div class="capitulo-header">Capitulo VII &mdash; Secao 5</div>
                    <h3 class="capitulo-name">Outros Sistemas Relevantes</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 32.</span> &mdash; Alem dos sistemas principais, merecem registro:</p>
                    </div>

                    <div class="sistema-card">
                        <div class="sistema-card-header">
                            <span class="sistema-card-name">RDS Mobile</span>
                            <span class="sistema-card-badge badge-opensource">Open Source</span>
                        </div>
                        <div class="sistema-card-origin">University of Washington &mdash; Estados Unidos</div>
                        <p>App open-source baseado em <strong>Respondent-Driven Sampling</strong> (amostragem dirigida por respondentes), projetado especificamente para comunidades de dificil acesso. A metodologia RDS e indicada quando nao existe lista previa da populacao.</p>
                    </div>

                    <div class="sistema-card">
                        <div class="sistema-card-header">
                            <span class="sistema-card-name">Show The Way</span>
                            <span class="sistema-card-badge badge-comercial">Comercial</span>
                        </div>
                        <div class="sistema-card-origin">Estados Unidos</div>
                        <p>App complementar para equipes de assistencia social, com foco em <strong>dados qualitativos detalhados</strong>: habitos, experiencias individuais, dados demograficos, imagens e indicadores de vulnerabilidade com localizacao georreferenciada.</p>
                    </div>

                    <div class="sistema-card">
                        <div class="sistema-card-header">
                            <span class="sistema-card-name">WellSky HMIS</span>
                            <span class="sistema-card-badge badge-comercial">Comercial</span>
                        </div>
                        <div class="sistema-card-origin">WellSky (antigo ServicePoint) &mdash; Estados Unidos</div>
                        <p>Plataforma comercial de HMIS com modulo movel completo, <strong>Coordinated Entry</strong>, By-Name List e relatorios conformes ao HUD. Concorrente direto do Clarity.</p>
                    </div>

                    <div class="sistema-card">
                        <div class="sistema-card-header">
                            <span class="sistema-card-name">SurveyCTO</span>
                            <span class="sistema-card-badge badge-comercial">Comercial</span>
                        </div>
                        <div class="sistema-card-origin">Dobility, Inc. &mdash; Internacional</div>
                        <p>Plataforma de coleta segura de dados com <strong>criptografia de ponta a ponta</strong>, formularios offline e integracao com ferramentas de analise. Utilizado por Banco Mundial e organizacoes de pesquisa.</p>
                    </div>
                </div>

                {{-- Cap VII.6 --}}
                <div class="capitulo" id="cap-vii-6">
                    <div class="capitulo-header">Capitulo VII &mdash; Secao 6</div>
                    <h3 class="capitulo-name">Matriz Comparativa</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 33.</span> &mdash; A tabela abaixo compara as capacidades tecnicas dos sistemas analisados em relacao ao SIZEM:</p>

                        <div class="tabela-wrapper">
                            <table class="tabela tabela-matrix">
                                <thead>
                                    <tr>
                                        <th>Capacidade</th>
                                        <th>Clarity</th>
                                        <th>Counting&nbsp;Us</th>
                                        <th>Rio Survey123</th>
                                        <th>Kobo Toolbox</th>
                                        <th>WellSky</th>
                                        <th class="col-sizem">SIZEM</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Coleta movel em campo</td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td class="col-sizem"><span class="ico-parcial" title="Parcial">P</span></td>
                                    </tr>
                                    <tr>
                                        <td>Funciona offline</td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-parcial" title="Parcial">P</span></td>
                                        <td class="col-sizem"><span class="ico-nao" title="Nao">N</span></td>
                                    </tr>
                                    <tr>
                                        <td>Georreferenciamento GPS</td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-parcial" title="Parcial">P</span></td>
                                        <td class="col-sizem"><span class="ico-sim" title="Sim">S</span></td>
                                    </tr>
                                    <tr>
                                        <td>Camadas geograficas (bairros, regionais)</td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td class="col-sizem"><span class="ico-sim" title="Sim">S</span></td>
                                    </tr>
                                    <tr>
                                        <td>Consultas espaciais (PostGIS/GIS)</td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td class="col-sizem"><span class="ico-sim" title="Sim">S</span></td>
                                    </tr>
                                    <tr>
                                        <td>Vinculo com vistorias/inspecoes</td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td class="col-sizem"><span class="ico-sim" title="Sim">S</span></td>
                                    </tr>
                                    <tr>
                                        <td>Case management / acompanhamento</td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td class="col-sizem"><span class="ico-parcial" title="Parcial">P</span></td>
                                    </tr>
                                    <tr>
                                        <td>By-Name List / lista nominal</td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-parcial" title="Parcial">P</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td class="col-sizem"><span class="ico-parcial" title="Parcial">P</span></td>
                                    </tr>
                                    <tr>
                                        <td>Coordinated Entry</td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td class="col-sizem"><span class="ico-nao" title="Nao">N</span></td>
                                    </tr>
                                    <tr>
                                        <td>Dashboards e relatorios</td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-parcial" title="Parcial">P</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td class="col-sizem"><span class="ico-sim" title="Sim">S</span></td>
                                    </tr>
                                    <tr>
                                        <td>Captura de fotos/imagens</td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-parcial" title="Parcial">P</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-parcial" title="Parcial">P</span></td>
                                        <td class="col-sizem"><span class="ico-nao" title="Nao">N</span></td>
                                    </tr>
                                    <tr>
                                        <td>Open source</td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td><span class="ico-sim" title="Sim">S</span></td>
                                        <td><span class="ico-nao" title="Nao">N</span></td>
                                        <td class="col-sizem"><span class="ico-parcial" title="Parcial">P</span></td>
                                    </tr>
                                    <tr>
                                        <td>Custo</td>
                                        <td>Comercial</td>
                                        <td>Comercial</td>
                                        <td>Licenca Esri</td>
                                        <td><strong>Gratuito</strong></td>
                                        <td>Comercial</td>
                                        <td class="col-sizem"><strong>Interno</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p style="font-size: 0.78rem; color: var(--ink-faint); margin-top: 0.5rem; font-family: var(--sans);">
                            Legenda: <span class="ico-sim" style="font-size: 0.55rem; width: 16px; height: 16px; line-height: 16px;">S</span> Sim &nbsp;
                            <span class="ico-parcial" style="font-size: 0.55rem; width: 16px; height: 16px; line-height: 16px;">P</span> Parcial &nbsp;
                            <span class="ico-nao" style="font-size: 0.55rem; width: 16px; height: 16px; line-height: 16px;">N</span> Nao
                        </p>
                    </div>
                </div>

                {{-- Cap VII.7 --}}
                <div class="capitulo" id="cap-vii-7">
                    <div class="capitulo-header">Capitulo VII &mdash; Secao 7</div>
                    <h3 class="capitulo-name">Analise Comparativa e Oportunidades para o SIZEM</h3>
                    <div class="capitulo-divider"></div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 34.</span> &mdash; Da analise comparativa, identificam-se os seguintes <strong>diferenciais competitivos</strong> do SIZEM:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;"><strong>Infraestrutura geoespacial nativa</strong> &mdash; Uso de PostGIS com camadas de bairros, regionais e limite municipal, permitindo consultas espaciais complexas (ST_Contains, ST_DWithin, ST_Distance) que nenhum dos concorrentes open-source oferece nativamente;</li>
                            <li data-num="II &mdash;"><strong>Vinculo vistoria-ponto-morador</strong> &mdash; Modelo relacional que conecta locais (pontos), pessoas (moradores) e inspecoes (vistorias) com dimensao temporal, superando sistemas focados apenas em contagem;</li>
                            <li data-num="III &mdash;"><strong>Custo zero de licenciamento</strong> &mdash; Desenvolvido internamente com stack open-source (Laravel, PostgreSQL, PostGIS), sem dependencia de Esri ou licencas comerciais;</li>
                            <li data-num="IV &mdash;"><strong>Contextualizacao territorial</strong> &mdash; Dados cruzados com geo_bairros e geo_regionais permitem analises por territorio administrativo que o CadUnico e outros sistemas nao oferecem.</li>
                        </ul>
                    </div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 35.</span> &mdash; Da mesma analise, identificam-se as seguintes <strong>lacunas a serem enderecadas</strong>:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;"><strong>Coleta offline em campo</strong> &mdash; Principal lacuna funcional. Counting Us e KoboToolbox demonstram que esta capacidade e essencial para equipes em areas sem cobertura de dados. Solucao viavel: Progressive Web App (PWA) com Service Workers ou integracao com KoboToolbox como ferramenta de coleta;</li>
                            <li data-num="II &mdash;"><strong>App movel nativo</strong> &mdash; O SIZEM opera via web responsivo, enquanto Clarity e Counting Us possuem apps nativos otimizados para uso em campo com interface simplificada;</li>
                            <li data-num="III &mdash;"><strong>Captura de imagens</strong> &mdash; KoboToolbox e Clarity permitem registro fotografico georreferenciado em campo, funcionalidade ausente no SIZEM;</li>
                            <li data-num="IV &mdash;"><strong>Coordinated Entry</strong> &mdash; O modelo de entrada coordenada do HMIS americano, onde qualquer ponto de atendimento acessa o mesmo banco de dados, poderia ser adaptado ao contexto dos Centro POP e CREAS de BH;</li>
                            <li data-num="V &mdash;"><strong>Acompanhamento longitudinal completo</strong> &mdash; O case management do Clarity e WellSky permite historico completo de intervencoes por pessoa, funcionalidade parcialmente presente no SIZEM.</li>
                        </ul>
                    </div>

                    <div class="artigo">
                        <p><span class="artigo-num">Art. 36.</span> &mdash; Consideram-se os seguintes <strong>cenarios de evolucao</strong> do SIZEM:</p>
                        <ul class="incisos">
                            <li data-num="I &mdash;"><strong>Cenario de curto prazo</strong> &mdash; Integracao com KoboToolbox para coleta em campo: formularios Kobo alimentam o SIZEM via API/importacao GeoJSON, combinando a robustez offline do Kobo com a analise espacial do PostGIS;</li>
                            <li data-num="II &mdash;"><strong>Cenario de medio prazo</strong> &mdash; Desenvolvimento de PWA com Service Workers para operacao offline parcial, permitindo que equipes de vistoria registrem dados em campo e sincronizem ao retornar a areas com conectividade;</li>
                            <li data-num="III &mdash;"><strong>Cenario de longo prazo</strong> &mdash; Evolucao para plataforma de Coordinated Entry municipal, integrando dados de Centro POP, CREAS, Consultorios na Rua e CadUnico em um unico ponto de acesso georreferenciado.</li>
                        </ul>
                    </div>

                    <div class="callout callout-forest">
                        <div class="callout-title">Conclusao da Analise</div>
                        <p>O SIZEM ocupa um nicho unico: e o unico sistema brasileiro de gestao de populacao de rua com <strong>georreferenciamento nativo em PostGIS</strong>, <strong>vinculo vistoria-ponto-morador</strong> e <strong>custo zero de licenciamento</strong>. Seu principal gap frente aos sistemas internacionais e a <strong>coleta offline em campo</strong>, que pode ser enderecada no curto prazo via integracao com KoboToolbox.</p>
                    </div>
                </div>
            </section>

            {{-- ═══════════════════════════════════════ --}}
            {{-- FONTES --}}
            {{-- ═══════════════════════════════════════ --}}
            <footer class="doc-footer fade-in" id="fontes">
                <div class="doc-footer-label">Fontes e Referencias</div>

                <ul class="fontes-list">
                    <li><a href="https://www.planalto.gov.br/ccivil_03/_ato2007-2010/2009/decreto/d7053.htm" target="_blank">Decreto n&ordm; 7.053/2009 &mdash; Planalto</a></li>
                    <li><a href="https://www.planalto.gov.br/ccivil_03/_ato2023-2026/2024/Lei/L14821.htm" target="_blank">Lei n&ordm; 14.821/2024 &mdash; PNTC PopRua &mdash; Planalto</a></li>
                    <li><a href="https://www12.senado.leg.br/noticias/materias/2024/01/17/politica-nacional-para-populacao-em-situacao-de-rua-agora-e-lei" target="_blank">Politica Nacional agora e lei &mdash; Senado</a></li>
                    <li><a href="https://www12.senado.leg.br/noticias/materias/2024/07/03/morador-de-rua-tem-prioridade-na-emissao-de-docs-aprova-cdh" target="_blank">PL 901/2024 &mdash; Prioridade em documentos &mdash; Senado</a></li>
                    <li><a href="https://bvsms.saude.gov.br/bvs/saudelegis/gm/2012/prt0122_25_01_2012.html" target="_blank">Portaria MS n&ordm; 122/2011 &mdash; Consultorio na Rua</a></li>
                    <li><a href="https://www.mds.gov.br/webarquivos/publicacao/assistencia_social/Normativas/tipificacao.pdf" target="_blank">Resolucao CNAS n&ordm; 109/2009 &mdash; Tipificacao</a></li>
                    <li><a href="https://www2.camara.leg.br/legin/fed/lei/1993/lei-8742-7-dezembro-1993-363163-publicacaooriginal-1-pl.html" target="_blank">LOAS &mdash; Camara dos Deputados</a></li>
                    <li><a href="https://site.mppr.mp.br/direito/Pagina/Legislacao-Populacao-em-Situacao-de-Rua" target="_blank">Legislacao PopRua &mdash; MPPR</a></li>
                    <li><a href="https://www.gov.br/mds/pt-br/acoes-e-programas/suas/servicos-e-programas/populacao-em-situacao-de-rua-no-cadastro-unico" target="_blank">CadUnico PopRua &mdash; MDS</a></li>
                    <li><a href="https://www.mds.gov.br/webarquivos/arquivo/cadastro_unico/_Guia_Cadastramento_de_Pessoas_em_Situacao_de_Rua.pdf" target="_blank">Guia de Cadastramento &mdash; MDS</a></li>
                    <li><a href="https://www.hudexchange.info/programs/hmis/" target="_blank">HMIS &mdash; HUD Exchange (EUA)</a></li>
                    <li><a href="https://community.solutions/yes-theres-a-better-way-to-measure-homelessness-than-the-annual-point-in-time-count/" target="_blank">By-Name List &mdash; Community Solutions</a></li>
                    <li><a href="https://housingfirsteurope.eu/country/finland/" target="_blank">Housing First Europe &mdash; Finland</a></li>
                    <li><a href="https://oecdecoscope.blog/2021/12/13/finlands-zero-homeless-strategy-lessons-from-a-success-story/" target="_blank">Finland&rsquo;s Zero Homeless Strategy &mdash; OECD</a></li>
                    <li><a href="https://www.oecd.org/content/dam/oecd/en/publications/reports/2025/01/oecd-monitoring-framework-to-measure-homelessness_7b704e9d/3e98455b-en.pdf" target="_blank">OECD Monitoring Framework to Measure Homelessness</a></li>
                    <li><a href="https://www.gov.scot/publications/hidden-homelessness-international-evidence-review-exploring-ways-identifying-counting-hidden-homeless-populations/pages/5/" target="_blank">Hidden Homelessness &mdash; Gov.scot</a></li>
                    <li><a href="https://homelesslaw.org/wp-content/uploads/2019/03/Photo-ID-Barriers-Faced-by-Homeless-Persons-2004.pdf" target="_blank">Photo ID Barriers &mdash; National Homelessness Law Center</a></li>
                    <li><a href="https://en.wikipedia.org/wiki/Homeless_Bill_of_Rights" target="_blank">Homeless Bill of Rights &mdash; Wikipedia</a></li>
                    <li><a href="https://www.connection-at-stmartins.org.uk/wp-content/uploads/2021/03/Data-rights-and-people-facing-homelessness.pdf" target="_blank">Data Rights and People Experiencing Homelessness</a></li>
                    <li><a href="https://www.ipea.gov.br/portal/categorias/45-todas-as-noticias/noticias/13457-populacao-em-situacao-de-rua-supera-281-4-mil-pessoas-no-brasil" target="_blank">Populacao de rua no Brasil &mdash; Ipea</a></li>
                    <li><a href="https://agenciabrasil.ebc.com.br/en/direitos-humanos/noticia/2025-01/homeless-population-brazil-rises-25-one-year" target="_blank">Homeless population rises 25% &mdash; Agencia Brasil</a></li>
                    <li><a href="https://prefeitura.pbh.gov.br/assistencia-social/acolhimento/poprua" target="_blank">Acolhimento PopRua &mdash; PBH</a></li>
                    <li><a href="https://ojs.focopublicacoes.com.br/foco/article/view/7667" target="_blank">Direitos Fundamentais PopRua &mdash; Revista FOCO</a></li>
                    <li><a href="https://www.bitfocus.com/outreach-toolkit" target="_blank">Clarity Outreach Toolkit &mdash; Bitfocus</a></li>
                    <li><a href="https://www.bitfocus.com/hmis-software" target="_blank">Clarity Human Services HMIS &mdash; Bitfocus</a></li>
                    <li><a href="https://pointintime.info/countingus-mobile-app/" target="_blank">Counting Us Mobile App &mdash; PointInTime.info</a></li>
                    <li><a href="https://censorua-pcrj.hub.arcgis.com/" target="_blank">Censo PopRua &mdash; Prefeitura do Rio de Janeiro</a></li>
                    <li><a href="https://journals.openedition.org/confins/46680" target="_blank">Mapeamento da populacao em situacao de rua no Rio &mdash; Confins</a></li>
                    <li><a href="https://www.kobotoolbox.org/" target="_blank">KoboToolbox &mdash; Harvard Humanitarian Initiative</a></li>
                    <li><a href="https://capstone.ischool.uw.edu/2025/05/27/rds-mobile-an-open-source-app-for-homeless-data-collection-and-research/" target="_blank">RDS Mobile &mdash; University of Washington</a></li>
                    <li><a href="https://wellsky.com/hmis-software/" target="_blank">WellSky HMIS Software</a></li>
                    <li><a href="https://www.route-fifty.com/digital-government/2022/01/mobile-apps-help-community-annual-homeless-count/361099/" target="_blank">Mobile Apps Help Homeless Count &mdash; Route Fifty</a></li>
                    <li><a href="https://www.giscloud.com/blog/city-of-milwaukee-case-study-using-mdc-to-identify-homeless-population/" target="_blank">GIS Cloud &mdash; Milwaukee Case Study</a></li>
                    <li><a href="https://www.surveycto.com/" target="_blank">SurveyCTO &mdash; Secure Data Collection</a></li>
                </ul>
            </footer>

            <div class="doc-end-mark">
                <span>Fim do Documento</span>
            </div>

        </article>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Scroll fade-in animation
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, { threshold: 0.08 });

        document.querySelectorAll('.fade-in').forEach(function(el) {
            observer.observe(el);
        });

        // Active TOC link highlight on scroll
        const tocLinks = document.querySelectorAll('.toc-link');
        const sections = [];

        tocLinks.forEach(function(link) {
            var target = document.querySelector(link.getAttribute('href'));
            if (target) {
                sections.push({ el: target, link: link });
            }
        });

        function updateActiveToc() {
            var scrollY = window.scrollY + 120;
            var active = null;

            for (var i = sections.length - 1; i >= 0; i--) {
                if (sections[i].el.offsetTop <= scrollY) {
                    active = sections[i].link;
                    break;
                }
            }

            tocLinks.forEach(function(l) { l.style.background = ''; l.style.borderLeftColor = 'transparent'; l.style.color = ''; });

            if (active) {
                active.style.background = 'rgba(184, 134, 11, 0.08)';
                active.style.borderLeftColor = '#b8860b';
                active.style.color = '#1a1a2e';
            }
        }

        window.addEventListener('scroll', updateActiveToc);
        updateActiveToc();

        // Mobile TOC toggle
        var tocSidebar = document.querySelector('.toc-sidebar');
        var tocOverlay = document.getElementById('toc-overlay');
        var tocToggle = document.getElementById('toc-toggle');

        function openToc() {
            tocSidebar.classList.add('open');
            tocOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeToc() {
            tocSidebar.classList.remove('open');
            tocOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        tocToggle.addEventListener('click', function() {
            tocSidebar.classList.contains('open') ? closeToc() : openToc();
        });

        tocOverlay.addEventListener('click', closeToc);

        // Fechar menu ao clicar em um link do índice (mobile)
        tocLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 900) closeToc();
            });
        });
    });
    </script>
</body>
</html>
