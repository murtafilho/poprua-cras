@extends('layouts.app')

@section('title', 'Infraestrutura')

@section('header')
    <div class="mobile-header-content">
        <a href="{{ route('admin.users.index') }}" class="btn btn-ghost btn-icon" style="margin-left: -8px;">
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

        <div class="card mb-4">
            <div class="card-body">
                <h2 style="margin-top: 0;">POPRUA CRAS</h2>
                <p>Sistema derivado do POPRUA Geo, focado na integracao com CRAS (Centro de Referencia de Assistencia Social).</p>
                <p class="text-muted">Stack: Laravel 12, PHP 8.4, PostgreSQL 17 + PostGIS 3.5, Redis 7, Vite + Alpine + Leaflet.</p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h3 style="margin-top: 0;">Containers Docker</h3>
                <p class="text-muted">Rede <code>poprua-cras</code> no host <code>vlcp-sufis01</code>. Codigo em <code>/var/www/html/joomla_sufis/ginfi/poprua-cras</code> (bind mount).</p>
                <div class="table-container">
                    <table class="table table-striped">
                        <thead>
                            <tr><th>Container</th><th>Porta Host</th><th>Funcao</th></tr>
                        </thead>
                        <tbody>
                            <tr><td><code>php84-poprua-cras</code></td><td>9086</td><td>PHP-FPM 8.4 (Laravel)</td></tr>
                            <tr><td><code>pg17-poprua-cras</code></td><td>5434</td><td>PostgreSQL 17 + PostGIS 3.5</td></tr>
                            <tr><td><code>redis-poprua-cras</code></td><td>6380</td><td>Cache, sessao e fila</td></tr>
                            <tr><td><code>queue-poprua-cras</code></td><td>—</td><td>Worker da queue Redis</td></tr>
                            <tr><td><code>ssh-poprua-cras</code></td><td>2226</td><td>Sidecar SSH</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h3 style="margin-top: 0;">URL de Producao</h3>
                <p>
                    <a href="https://sufis.pbh.gov.br/ginfi/poprua-cras/public" class="link" target="_blank" rel="noopener">
                        https://sufis.pbh.gov.br/ginfi/poprua-cras/public
                    </a>
                </p>
                <p class="text-muted">Servida via Apache do host com proxy FastCGI para o container PHP-FPM (<code>php84-poprua-cras.conf</code>).</p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h3 style="margin-top: 0;">Comandos Principais</h3>
                <p class="text-muted">Todos os comandos artisan, composer e npm rodam dentro do container via <code>docker exec</code>.</p>

                <h4>Prefixo Padrao</h4>
                <p><code>EXEC="sudo docker exec -u root -w /var/www/html/joomla_sufis/ginfi/poprua-cras php84-poprua-cras"</code></p>

                <h4>Migrations</h4>
                <ul>
                    <li><code>$EXEC php artisan migrate --no-interaction</code></li>
                    <li><code>$EXEC php artisan migrate:status</code></li>
                </ul>

                <h4>Cache</h4>
                <ul>
                    <li><code>$EXEC php artisan cache:clear</code></li>
                    <li><code>$EXEC php artisan config:clear</code></li>
                    <li><code>$EXEC php artisan view:clear</code></li>
                </ul>

                <h4>Testes e Qualidade</h4>
                <ul>
                    <li><code>$EXEC php artisan test</code></li>
                    <li><code>$EXEC vendor/bin/pint --dirty</code></li>
                    <li><code>$EXEC vendor/bin/phpstan analyse</code></li>
                </ul>

                <h4>Frontend</h4>
                <p class="text-muted">Node nao esta no container PHP — usar script auxiliar:</p>
                <p><code>sudo bash docker/build-frontend.sh</code></p>

                <h4>Composer e Queue</h4>
                <ul>
                    <li><code>$EXEC composer install --no-interaction</code></li>
                    <li><code>sudo docker logs -f queue-poprua-cras</code></li>
                </ul>

                <h4>Shell Interativo</h4>
                <ul>
                    <li><code>sudo docker exec -it -u root php84-poprua-cras bash</code></li>
                    <li><code>ssh sufis-poprua-cras</code> (alias para porta 2226)</li>
                </ul>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h3 style="margin-top: 0;">Backup do Banco</h3>
                <p>Backup automatico do PostgreSQL todo dia as <strong>03:00</strong>, com retencao de <strong>14 dias</strong>.</p>
                <ul>
                    <li>Script: <code>/opt/docker/poprua-cras/backup.sh</code></li>
                    <li>Cron: <code>/etc/cron.d/poprua-cras-backup</code></li>
                    <li>Destino: <code>/opt/docker/poprua-cras/backups/</code></li>
                    <li>Log: <code>/opt/docker/poprua-cras/backups/backup.log</code></li>
                    <li>Formato: <code>pg_dump -Fc</code> (custom, comprimido)</li>
                </ul>

                <h4>Backup Manual</h4>
                <p><code>sudo /opt/docker/poprua-cras/backup.sh</code></p>

                <h4>Restaurar</h4>
                <p><code>sudo docker exec -i pg17-poprua-cras pg_restore -U poprua_cras -d poprua_cras --clean --if-exists &lt; /opt/docker/poprua-cras/backups/ARQUIVO.dump</code></p>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h3 style="margin-top: 0;">Dados Geoespaciais</h3>
                <p class="text-muted">Todas as geometrias usam SRID 4326 (WGS84) com indice GIST.</p>
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
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <h3 style="margin-top: 0;">Documentacao</h3>
                <ul style="margin: 0;">
                    <li><code>CLAUDE.md</code> — guia para Claude Code (raiz do repo)</li>
                    <li><code>docs/ARQUITETURA_DOCKER.md</code> — arquitetura Docker e rede</li>
                    <li><code>docs/API.md</code> — referencia da API REST</li>
                </ul>
            </div>
        </div>

    </div>
@endsection
