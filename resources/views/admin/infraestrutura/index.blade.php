@extends('layouts.app')

@section('title', 'Infraestrutura')

@push('styles')
<style>
    .infra-kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--space-3); }
    .infra-kpi-label { font-size: var(--text-xs); color: var(--text-muted); margin-bottom: var(--space-1); }
    .infra-kpi-value { font-size: var(--text-lg); font-weight: var(--font-bold); line-height: 1.2; color: var(--accent-primary); }
    .infra-kpi-sub { font-size: var(--text-xs); color: var(--text-secondary); margin-top: 2px; }
    .infra-section { margin-bottom: var(--space-3); }
    .infra-section > summary {
        list-style: none;
        cursor: pointer;
        padding: var(--space-3) var(--space-4);
        font-weight: var(--font-semibold);
        font-size: var(--text-sm);
        background: var(--bg-tertiary);
        border: 1px solid var(--border-primary);
        border-radius: var(--card-radius);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: var(--space-2);
        user-select: none;
    }
    .infra-section > summary::-webkit-details-marker { display: none; }
    .infra-section[open] > summary {
        border-radius: var(--card-radius) var(--card-radius) 0 0;
        border-bottom-color: transparent;
    }
    .infra-section-body {
        border: 1px solid var(--border-primary);
        border-top: none;
        border-radius: 0 0 var(--card-radius) var(--card-radius);
        padding: var(--space-4);
        background: var(--bg-primary);
    }
    .infra-section-body > :first-child { margin-top: 0; }
    .infra-section-body h4 {
        font-size: var(--text-sm);
        font-weight: var(--font-semibold);
        margin: var(--space-4) 0 var(--space-2);
        color: var(--text-primary);
    }
    .infra-section-body h4:first-child { margin-top: 0; }
    .infra-chevron { width: 18px; height: 18px; color: var(--text-muted); transition: transform .15s ease; flex-shrink: 0; }
    .infra-section[open] .infra-chevron { transform: rotate(90deg); }
    .infra-cmd {
        display: block;
        font-family: var(--font-mono, ui-monospace, monospace);
        font-size: var(--text-xs);
        background: var(--bg-tertiary);
        border: 1px solid var(--border-primary);
        border-radius: var(--radius-md);
        padding: var(--space-2) var(--space-3);
        margin: var(--space-2) 0;
        overflow-x: auto;
        white-space: pre-wrap;
        word-break: break-all;
        color: var(--text-primary);
    }
    .infra-list { margin: 0; padding-left: var(--space-5); font-size: var(--text-sm); }
    .infra-list li { margin-bottom: var(--space-2); }
    .infra-list li:last-child { margin-bottom: 0; }
    .infra-meta { font-size: var(--text-xs); color: var(--text-muted); margin-top: var(--space-3); }
    @media (max-width: 767px) { .infra-kpis { grid-template-columns: repeat(2, 1fr); } }
</style>
@endpush

@section('header')
    <div class="mobile-header-content">
        <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <span class="mobile-header-title">Infraestrutura</span>
        <div style="width: 44px;"></div>
    </div>
@endsection

@section('content')
    <div class="page-content">

        {{-- Cabeçalho --}}
        <div class="card mb-4">
            <div class="card-body">
                <h2 style="margin: 0 0 var(--space-2);">SIZEM BH — Infraestrutura</h2>
                <p style="margin: 0 0 var(--space-2); color: var(--text-secondary); font-size: var(--text-sm); line-height: 1.5;">
                    Sistema Integrado de Zeladoria Municipal. Fork do POPRUA Geo, focado em zeladoria urbana e integração com CRAS.
                    Repositório <code>poprua-cras</code> · host <code>vlcp-sufis01</code> (SUFIS/PBH).
                </p>
                <p style="margin: 0;">
                    <a href="{{ $prodUrl }}" class="link" target="_blank" rel="noopener">{{ $prodUrl }}</a>
                    <span class="text-muted" style="font-size: var(--text-xs); margin-left: var(--space-2);">
                        Apache → FastCGI <code>127.0.0.1:9086</code>
                    </span>
                </p>
                <p class="infra-meta" style="margin-bottom: 0;">Atualizado em {{ $geradoEm }} (America/Sao_Paulo)</p>
            </div>
        </div>

        {{-- KPIs da stack --}}
        <div class="infra-kpis mb-4">
            <div class="card" style="text-align: center;">
                <div class="card-body" style="padding: var(--space-3);">
                    <p class="infra-kpi-label">PHP</p>
                    <p class="infra-kpi-value">{{ $phpVersion }}</p>
                    <p class="infra-kpi-sub">runtime</p>
                </div>
            </div>
            <div class="card" style="text-align: center;">
                <div class="card-body" style="padding: var(--space-3);">
                    <p class="infra-kpi-label">Laravel</p>
                    <p class="infra-kpi-value">{{ $versoes['laravel'] }}</p>
                    <p class="infra-kpi-sub">framework</p>
                </div>
            </div>
            <div class="card" style="text-align: center;">
                <div class="card-body" style="padding: var(--space-3);">
                    <p class="infra-kpi-label">PostgreSQL</p>
                    <p class="infra-kpi-value">17</p>
                    <p class="infra-kpi-sub">PostGIS 3.5</p>
                </div>
            </div>
            <div class="card" style="text-align: center;">
                <div class="card-body" style="padding: var(--space-3);">
                    <p class="infra-kpi-label">Redis</p>
                    <p class="infra-kpi-value">7</p>
                    <p class="infra-kpi-sub">cache · sessão · fila</p>
                </div>
            </div>
        </div>

        {{-- Containers --}}
        <details class="infra-section">
            <summary>
                <span>Containers Docker (produção)</span>
                <svg class="infra-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </summary>
            <div class="infra-section-body">
                <p class="text-muted" style="font-size: var(--text-sm); margin-top: 0;">
                    Rede <code>poprua-cras</code>. Código em
                    <code>/var/www/html/joomla_sufis/ginfi/poprua-cras</code> (bind mount).
                    Compose canônico na raiz do repositório.
                </p>
                <div class="table-container">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Container</th>
                                <th>Porta (host)</th>
                                <th>Função</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td><code>php84-poprua-cras</code></td><td>9086</td><td>PHP-FPM 8.4 (Laravel)</td></tr>
                            <tr><td><code>pg17-poprua-cras</code></td><td>5434</td><td>PostgreSQL 17 + PostGIS 3.5</td></tr>
                            <tr><td><code>redis-poprua-cras</code></td><td>6380</td><td>Cache, sessão e fila</td></tr>
                            <tr><td><code>queue-poprua-cras</code></td><td>—</td><td>Worker da fila Redis (<code>media-conversions</code>)</td></tr>
                            <tr><td><code>ssh-poprua-cras</code></td><td>2226</td><td>Sidecar SSH (<code>ssh sufis-poprua-cras</code>)</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        {{-- Stack tecnológica --}}
        <details class="infra-section">
            <summary>
                <span>Stack tecnológica</span>
                <svg class="infra-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </summary>
            <div class="infra-section-body">
                <div class="table-container">
                    <table class="table table-striped">
                        <thead>
                            <tr><th>Camada</th><th>Tecnologia</th><th>Versão</th></tr>
                        </thead>
                        <tbody>
                            <tr><td>Backend</td><td>Laravel Breeze · Sanctum · Spatie (Permission, MediaLibrary, Activity Log, Backup)</td><td>—</td></tr>
                            <tr><td>Backend</td><td>DomPDF · proj4php</td><td>{{ $versoes['dompdf'] }} · {{ $versoes['proj4php'] }}</td></tr>
                            <tr><td>Frontend</td><td>Blade · Alpine.js · Vite</td><td>{{ $versoes['alpinejs'] }} · {{ $versoes['vite'] }}</td></tr>
                            <tr><td>Frontend</td><td>Leaflet · MarkerCluster · Chart.js · Flatpickr · Axios</td><td>{{ $versoes['leaflet'] }} · {{ $versoes['chartjs'] }}</td></tr>
                            <tr><td>PWA</td><td>Service Worker + IndexedDB (fotos offline)</td><td>—</td></tr>
                            <tr><td>Qualidade</td><td>PHPUnit · PHPStan · Pint · Playwright</td><td>{{ $versoes['phpunit'] }} · {{ $versoes['phpstan'] }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="text-muted" style="font-size: var(--text-xs); margin-bottom: 0;">
                    Especificação completa: <code>docs/ESPECIFICACAO_TECNOLOGIA_E_INFRAESTRUTURA.md</code> ·
                    PDF: <code>php artisan docs:stack-pdf</code>
                </p>
            </div>
        </details>

        {{-- Deploy --}}
        <details class="infra-section">
            <summary>
                <span>Deploy e publicação</span>
                <svg class="infra-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </summary>
            <div class="infra-section-body">
                <ul class="infra-list">
                    <li><strong>Canal:</strong> push em <code>main</code> → GitHub Actions → runner self-hosted em <code>vlcp-sufis01</code> → <code>docker/deploy.sh</code></li>
                    <li><strong>Fallback:</strong> <code>bash poprua deploy</code> (manual) ou cron de polling a cada 3 min</li>
                    <li><strong>Validação:</strong> <code>docker/smoke-test.sh</code> (FastCGI, URL pública, banco, logs)</li>
                </ul>
            </div>
        </details>

        {{-- Comandos --}}
        <details class="infra-section">
            <summary>
                <span>Comandos principais (produção)</span>
                <svg class="infra-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </summary>
            <div class="infra-section-body">
                <p class="text-muted" style="font-size: var(--text-sm); margin-top: 0;">
                    Artisan, Composer e testes rodam dentro do container via <code>docker exec</code>.
                </p>

                <h4>Prefixo padrão</h4>
                <code class="infra-cmd">EXEC="sudo docker exec -u root -w /var/www/html/joomla_sufis/ginfi/poprua-cras php84-poprua-cras"</code>

                <h4>Migrations</h4>
                <ul class="infra-list">
                    <li><code class="infra-cmd" style="display: inline; padding: 2px 6px;">$EXEC php artisan migrate --no-interaction</code></li>
                    <li><code class="infra-cmd" style="display: inline; padding: 2px 6px;">$EXEC php artisan migrate:status</code></li>
                </ul>

                <h4>Cache</h4>
                <ul class="infra-list">
                    <li><code class="infra-cmd" style="display: inline; padding: 2px 6px;">$EXEC php artisan cache:clear</code></li>
                    <li><code class="infra-cmd" style="display: inline; padding: 2px 6px;">$EXEC php artisan config:clear</code></li>
                    <li><code class="infra-cmd" style="display: inline; padding: 2px 6px;">$EXEC php artisan view:clear</code></li>
                </ul>

                <h4>Testes e qualidade</h4>
                <ul class="infra-list">
                    <li><code class="infra-cmd" style="display: inline; padding: 2px 6px;">$EXEC php artisan test</code></li>
                    <li><code class="infra-cmd" style="display: inline; padding: 2px 6px;">$EXEC vendor/bin/pint --dirty</code></li>
                    <li><code class="infra-cmd" style="display: inline; padding: 2px 6px;">$EXEC vendor/bin/phpstan analyse</code></li>
                </ul>

                <h4>Frontend</h4>
                <p class="text-muted" style="font-size: var(--text-sm);">Node não está no container PHP — usar script auxiliar:</p>
                <code class="infra-cmd">sudo bash docker/build-frontend.sh</code>

                <h4>Composer e fila</h4>
                <ul class="infra-list">
                    <li><code class="infra-cmd" style="display: inline; padding: 2px 6px;">$EXEC composer install --no-interaction</code></li>
                    <li><code class="infra-cmd" style="display: inline; padding: 2px 6px;">sudo docker logs -f queue-poprua-cras</code></li>
                </ul>

                <h4>Shell interativo</h4>
                <ul class="infra-list">
                    <li><code class="infra-cmd" style="display: inline; padding: 2px 6px;">sudo docker exec -it -u root php84-poprua-cras bash</code></li>
                    <li><code class="infra-cmd" style="display: inline; padding: 2px 6px;">ssh sufis-poprua-cras</code> (alias SSH, porta 2226)</li>
                </ul>
            </div>
        </details>

        {{-- Backup --}}
        <details class="infra-section">
            <summary>
                <span>Backup do banco</span>
                <svg class="infra-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </summary>
            <div class="infra-section-body">
                <p style="font-size: var(--text-sm); margin-top: 0;">
                    Backup automático do PostgreSQL todo dia às <strong>03:00</strong>, retenção de <strong>14 dias</strong>.
                </p>
                <ul class="infra-list">
                    <li>Script: <code>/opt/docker/poprua-cras/backup.sh</code></li>
                    <li>Cron: <code>/etc/cron.d/poprua-cras-backup</code></li>
                    <li>Destino: <code>/opt/docker/poprua-cras/backups/</code></li>
                    <li>Log: <code>/opt/docker/poprua-cras/backups/backup.log</code></li>
                    <li>Formato: <code>pg_dump -Fc</code> (custom, comprimido)</li>
                </ul>

                <h4>Backup manual</h4>
                <code class="infra-cmd">sudo /opt/docker/poprua-cras/backup.sh</code>

                <h4>Restaurar</h4>
                <code class="infra-cmd">sudo docker exec -i pg17-poprua-cras pg_restore -U poprua_cras -d poprua_cras --clean --if-exists &lt; /opt/docker/poprua-cras/backups/ARQUIVO.dump</code>
            </div>
        </details>

        {{-- Dados geoespaciais --}}
        <details class="infra-section">
            <summary>
                <span>Dados geoespaciais (PostGIS)</span>
                <svg class="infra-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </summary>
            <div class="infra-section-body">
                <p class="text-muted" style="font-size: var(--text-sm); margin-top: 0;">
                    Todas as geometrias usam SRID <strong>4326</strong> (WGS84) com índice GIST.
                </p>
                <div class="table-container">
                    <table class="table table-striped">
                        <thead>
                            <tr><th>Tabela</th><th>Geometria</th></tr>
                        </thead>
                        <tbody>
                            <tr><td><code>pontos</code></td><td>POINT</td></tr>
                            <tr><td><code>endereco_atualizados</code></td><td>POINT</td></tr>
                            <tr><td><code>geo_bairros</code></td><td>MULTIPOLYGON</td></tr>
                            <tr><td><code>geo_regionais</code></td><td>MULTIPOLYGON</td></tr>
                            <tr><td><code>geo_limite_municipio</code></td><td>GEOMETRY</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </details>

        {{-- Documentação --}}
        <details class="infra-section">
            <summary>
                <span>Documentação no repositório</span>
                <svg class="infra-chevron" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </summary>
            <div class="infra-section-body">
                <ul class="infra-list">
                    <li><code>CLAUDE.md</code> — guia operacional (dev e deploy)</li>
                    <li><code>docs/ESPECIFICACAO_TECNOLOGIA_E_INFRAESTRUTURA.md</code> — especificação de colocation</li>
                    <li><code>docs/ARQUITETURA_DOCKER.md</code> — arquitetura Docker e rede</li>
                    <li><code>docs/API.md</code> — referência da API REST</li>
                    <li><code>docs/adr/</code> — Architecture Decision Records</li>
                </ul>
            </div>
        </details>

    </div>
@endsection
