<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Stack Tecnológica — SIZEM BH</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1a1a1a; padding: 28px 32px; line-height: 1.45; }
        h1 { font-size: 18px; color: #003366; margin-bottom: 4px; }
        h2 { font-size: 13px; color: #003366; margin: 18px 0 8px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        h3 { font-size: 11px; color: #333; margin: 12px 0 6px; }
        .meta { font-size: 9px; color: #555; margin-bottom: 16px; }
        .meta p { margin-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #bbb; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #e8eef4; font-weight: bold; font-size: 9px; }
        td { font-size: 9px; }
        ul { margin: 4px 0 8px 16px; font-size: 9px; }
        li { margin-bottom: 2px; }
        .footer { margin-top: 20px; font-size: 8px; color: #777; text-align: center; border-top: 1px solid #ddd; padding-top: 8px; }
        .diagram { font-family: DejaVu Sans Mono, monospace; font-size: 7px; background: #f5f5f5; padding: 8px; border: 1px solid #ddd; white-space: pre-line; margin: 8px 0; }
    </style>
</head>
<body>
    <h1>SIZEM BH — Stack Tecnológica</h1>
    <div class="meta">
        <p><strong>Sistema Integrado de Zeladoria Municipal</strong> · Prefeitura de Belo Horizonte / SUFIS</p>
        <p>Repositório: poprua-cras · Documento gerado em {{ $geradoEm }}</p>
        <p>Produção: https://sufis.pbh.gov.br/ginfi/poprua-cras/public</p>
    </div>

    <h2>1. Visão geral</h2>
    <p style="font-size: 9px; margin-bottom: 8px;">
        Aplicação web para gestão de zeladorias urbanas em campo: mapa georreferenciado, registro de vistorias com fotos
        (modo offline), cadastro de moradores e relatórios. Arquitetura monólito Laravel com camada de serviços,
        PostgreSQL + PostGIS e Redis para cache, sessão e filas.
    </p>

    <h2>2. Backend</h2>
    <table>
        <thead>
            <tr><th style="width:28%">Tecnologia</th><th style="width:14%">Versão</th><th>Função</th></tr>
        </thead>
        <tbody>
            <tr><td>PHP</td><td>8.4</td><td>Runtime da aplicação</td></tr>
            <tr><td>Laravel</td><td>{{ $versoes['laravel'] }}</td><td>Framework web (MVC, ORM, filas, autenticação)</td></tr>
            <tr><td>PostgreSQL</td><td>17 (produção)</td><td>Banco de dados relacional</td></tr>
            <tr><td>PostGIS</td><td>3.5 (produção)</td><td>Extensão espacial (geometrias, SRID 4326)</td></tr>
            <tr><td>Redis</td><td>7</td><td>Sessões, cache e filas assíncronas</td></tr>
            <tr><td>Laravel Breeze</td><td>{{ $versoes['breeze'] }}</td><td>Autenticação web</td></tr>
            <tr><td>Laravel Sanctum</td><td>{{ $versoes['sanctum'] }}</td><td>Autenticação API (tokens / SPA)</td></tr>
            <tr><td>Spatie Permission</td><td>{{ $versoes['permission'] }}</td><td>Controle de acesso (RBAC)</td></tr>
            <tr><td>Spatie MediaLibrary</td><td>{{ $versoes['medialibrary'] }}</td><td>Upload e gestão de fotos</td></tr>
            <tr><td>Spatie Activity Log</td><td>{{ $versoes['activitylog'] }}</td><td>Auditoria de alterações</td></tr>
            <tr><td>Spatie Laravel Backup</td><td>{{ $versoes['backup'] }}</td><td>Backup da aplicação e banco</td></tr>
            <tr><td>DomPDF (barryvdh)</td><td>{{ $versoes['dompdf'] }}</td><td>Geração de relatórios PDF</td></tr>
            <tr><td>proj4php</td><td>{{ $versoes['proj4php'] }}</td><td>Conversão de projeções cartográficas</td></tr>
        </tbody>
    </table>

    <h2>3. Frontend</h2>
    <table>
        <thead>
            <tr><th style="width:28%">Tecnologia</th><th style="width:14%">Versão</th><th>Função</th></tr>
        </thead>
        <tbody>
            <tr><td>Blade</td><td>Laravel</td><td>Templates server-side</td></tr>
            <tr><td>Alpine.js</td><td>{{ $versoes['alpinejs'] }}</td><td>Interatividade no HTML</td></tr>
            <tr><td>Leaflet</td><td>{{ $versoes['leaflet'] }}</td><td>Mapas interativos</td></tr>
            <tr><td>Leaflet MarkerCluster</td><td>{{ $versoes['markercluster'] }}</td><td>Agrupamento de marcadores</td></tr>
            <tr><td>Chart.js</td><td>{{ $versoes['chartjs'] }}</td><td>Gráficos no dashboard</td></tr>
            <tr><td>Flatpickr</td><td>{{ $versoes['flatpickr'] }}</td><td>Seletor de datas</td></tr>
            <tr><td>Axios</td><td>{{ $versoes['axios'] }}</td><td>Requisições HTTP assíncronas</td></tr>
            <tr><td>Vite</td><td>{{ $versoes['vite'] }}</td><td>Bundler de assets</td></tr>
            <tr><td>Service Worker (PWA)</td><td>—</td><td>Cache offline e sincronização de fotos</td></tr>
        </tbody>
    </table>

    <h2>4. Infraestrutura de produção</h2>
    <table>
        <thead>
            <tr><th style="width:28%">Componente</th><th style="width:22%">Tecnologia</th><th>Descrição</th></tr>
        </thead>
        <tbody>
            <tr><td>Servidor</td><td>vlcp-sufis01</td><td>Host Debian, rede institucional PBH</td></tr>
            <tr><td>Orquestração</td><td>Docker Compose</td><td>Stack: app, queue, db, redis, init-perms, ssh</td></tr>
            <tr><td>Container app</td><td>serversideup/php:8.4-fpm-nginx</td><td>PHP-FPM + Nginx (php84-poprua-cras)</td></tr>
            <tr><td>Container banco</td><td>postgis/postgis:17-3.5</td><td>PostgreSQL + PostGIS (pg17-poprua-cras)</td></tr>
            <tr><td>Container cache</td><td>redis:7-alpine</td><td>Redis (redis-poprua-cras)</td></tr>
            <tr><td>Proxy web</td><td>Apache 2.4</td><td>FastCGI → 127.0.0.1:9086</td></tr>
            <tr><td>Deploy</td><td>GitHub Actions</td><td>Self-hosted runner + docker/deploy.sh</td></tr>
        </tbody>
    </table>

    <h2>5. Arquitetura de fotografias</h2>
    <p style="font-size: 9px; margin-bottom: 6px;">
        Fluxo offline-first: compactação WebP no navegador (máx. 1920 px) → fila IndexedDB → Service Worker → API REST →
        Spatie MediaLibrary → fila Redis <code>media-conversions</code> → derivações WebP (thumb 300×300, preview 800×600).
    </p>
    <ul>
        <li>Coleções: <strong>fotos</strong> em Vistoria e Morador</li>
        <li>Armazenamento: <code>storage/app/public/</code> via MediaLibrary</li>
        <li>Worker: container <strong>queue-poprua-cras</strong></li>
    </ul>

    <h2>6. Padrões arquiteturais</h2>
    <ul>
        <li>Controllers finos; lógica em <code>app/Services/</code></li>
        <li>Validação via Form Requests; autorização via Policies</li>
        <li>Dados geoespaciais em SRID 4326 (WGS 84)</li>
        <li>Domínio central: Ponto → Vistoria (zeladoria) → Morador</li>
    </ul>

    <h2>7. Qualidade e testes</h2>
    <table>
        <thead>
            <tr><th>Ferramenta</th><th>Versão</th><th>Uso</th></tr>
        </thead>
        <tbody>
            <tr><td>PHPUnit</td><td>{{ $versoes['phpunit'] }}</td><td>Testes automatizados</td></tr>
            <tr><td>PHPStan / Larastan</td><td>{{ $versoes['phpstan'] }} / {{ $versoes['larastan'] }}</td><td>Análise estática</td></tr>
            <tr><td>Laravel Pint</td><td>{{ $versoes['pint'] }}</td><td>Formatação PHP</td></tr>
            <tr><td>Playwright</td><td>{{ $versoes['playwright'] }}</td><td>Testes end-to-end (opcional)</td></tr>
        </tbody>
    </table>

    <div class="footer">
        SIZEM BH — Stack Tecnológica · Documento institucional · {{ $geradoEm }}
    </div>
</body>
</html>
