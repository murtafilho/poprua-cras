@extends('layouts.app')

@section('title', 'Stack e Projeção')

@section('header')
    <h1 class="page-title">Stack e Projeção</h1>
@endsection

@push('styles')
<style>
    .sp-doc { max-width: 68rem; }
    .sp-intro {
        font-size: var(--text-sm);
        color: var(--text-secondary);
        line-height: 1.6;
        margin-bottom: var(--space-4);
    }
    .sp-meta {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-4);
        font-size: var(--text-xs);
        color: var(--text-muted);
        margin-top: var(--space-2);
    }
    .sp-section { margin-bottom: var(--space-4); }
    .sp-section h2 {
        font-size: var(--text-base);
        font-weight: var(--font-semibold);
        color: var(--accent-primary);
        border-bottom: 1px solid var(--border-primary);
        padding-bottom: var(--space-2);
        margin-bottom: var(--space-3);
    }
    .sp-section h3 {
        font-size: var(--text-sm);
        font-weight: var(--font-semibold);
        color: var(--text-primary);
        margin: var(--space-4) 0 var(--space-2);
    }
    .sp-section p, .sp-section li {
        font-size: var(--text-sm);
        color: var(--text-secondary);
        line-height: 1.6;
    }
    .sp-section ul { margin: var(--space-2) 0 var(--space-3) var(--space-5); }
    .sp-kpi-row {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(9rem, 1fr));
        gap: var(--space-2);
        margin: var(--space-3) 0;
    }
    .sp-kpi {
        background: var(--bg-tertiary);
        border-radius: var(--radius-md);
        padding: var(--space-3);
        text-align: center;
    }
    .sp-kpi .v {
        font-size: var(--text-lg);
        font-weight: var(--font-bold);
        color: var(--accent-primary);
        line-height: 1.2;
    }
    .sp-kpi .l {
        font-size: var(--text-xs);
        color: var(--text-muted);
        margin-top: var(--space-1);
        line-height: 1.3;
    }
    .sp-kpi.green .v { color: var(--accent-success, #006633); }
    .sp-stack-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
        gap: var(--space-2);
    }
    .sp-stack-item {
        border: 1px solid var(--border-primary);
        border-radius: var(--radius-md);
        padding: var(--space-2) var(--space-3);
        font-size: var(--text-xs);
    }
    .sp-stack-item strong {
        display: block;
        color: var(--accent-primary);
        font-size: var(--text-sm);
    }
    .sp-stack-item span { color: var(--text-muted); }
    .sp-pipeline {
        font-family: var(--font-mono, ui-monospace, monospace);
        font-size: var(--text-xs);
        background: #1e2836;
        color: #cdd9e5;
        padding: var(--space-3);
        border-radius: var(--radius-md);
        overflow-x: auto;
        line-height: 1.5;
        white-space: pre-wrap;
    }
    .sp-table-wrap { overflow-x: auto; margin: var(--space-3) 0; }
    .sp-table {
        width: 100%;
        border-collapse: collapse;
        font-size: var(--text-xs);
    }
    .sp-table thead th {
        background: var(--accent-primary);
        color: #fff;
        padding: var(--space-2) var(--space-3);
        text-align: left;
        white-space: nowrap;
    }
    .sp-table tbody td {
        padding: var(--space-2) var(--space-3);
        border-bottom: 1px solid var(--border-primary);
    }
    .sp-table tbody tr:nth-child(even) { background: var(--bg-tertiary); }
    .sp-table .num { text-align: right; font-variant-numeric: tabular-nums; }
    .sp-table tr.total { background: var(--bg-tertiary) !important; font-weight: var(--font-semibold); }
    .sp-table tr.highlight { background: #edf7f0 !important; }
    .sp-callout {
        border-left: 4px solid var(--accent-success, #006633);
        background: #edf7f0;
        padding: var(--space-3);
        border-radius: 0 var(--radius-md) var(--radius-md) 0;
        font-size: var(--text-sm);
        margin: var(--space-3) 0;
    }
    .sp-callout.purple { border-color: #9333ea; background: #f5f0ff; }
    .sp-callout.warn { border-color: #e6c200; background: #fff8e6; }
    .sp-legend {
        display: flex;
        flex-wrap: wrap;
        gap: var(--space-3);
        font-size: var(--text-xs);
        color: var(--text-muted);
        margin-bottom: var(--space-2);
    }
    .sp-legend i {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 2px;
        vertical-align: middle;
        margin-right: 4px;
    }
    .sp-chart-box {
        position: relative;
        height: 320px;
        margin: var(--space-3) 0;
    }
    .sp-chart-box.tall { height: 380px; }
    .sp-footer-note {
        text-align: center;
        font-size: var(--text-xs);
        color: var(--text-muted);
        padding: var(--space-4) 0 var(--space-2);
    }
</style>
@endpush

@section('content')
    @php
        $totais = $dados['totais'];
        $fotoStats = $dados['fotoStats'];
        $pontosSemestre = $dados['pontosSemestre'];
        $totalOrganico = collect($pontosSemestre)->sum('organico');
        $totalEtl = collect($pontosSemestre)->sum('etl');
        $ultimaProjecao = collect($dados['projecaoAnual'])->last();
    @endphp

    <div class="page-content">
        <div class="container sp-doc">
            <p class="sp-intro">
                Descrição da stack da aplicação, dados históricos de vistorias e pontos por semestre,
                comportamento atual de fotografias e projeção de crescimento para 5 anos.
            </p>
            <div class="sp-meta">
                <span>Sistema: SIZEM — Zeladoria Urbana / CRAS</span>
                <span>Dados gerados em: {{ $dados['geradoEm'] }}</span>
            </div>

            {{-- 1. Stack --}}
            <div class="card sp-section">
                <div class="card-body">
                    <h2>1. Stack tecnológica da aplicação</h2>
                    <p>Monólito Laravel com camada de serviços, banco geoespacial, cache/filas Redis e frontend Blade + Alpine + PWA offline-first para fotografias em campo.</p>

                    <h3>Backend</h3>
                    <div class="sp-stack-grid">
                        <div class="sp-stack-item"><strong>PHP 8.4</strong><span>Runtime · PHP-FPM</span></div>
                        <div class="sp-stack-item"><strong>Laravel 12</strong><span>Framework MVC, ORM, filas, auth</span></div>
                        <div class="sp-stack-item"><strong>PostgreSQL 17</strong><span>Banco relacional</span></div>
                        <div class="sp-stack-item"><strong>PostGIS 3.5</strong><span>Geometrias SRID 4326</span></div>
                        <div class="sp-stack-item"><strong>Redis 7</strong><span>Sessão, cache, filas</span></div>
                        <div class="sp-stack-item"><strong>Spatie MediaLibrary 11</strong><span>Fotos · conversões WebP</span></div>
                        <div class="sp-stack-item"><strong>Spatie Permission 6</strong><span>RBAC</span></div>
                        <div class="sp-stack-item"><strong>Laravel Sanctum 4</strong><span>API (upload fotos)</span></div>
                        <div class="sp-stack-item"><strong>DomPDF 3</strong><span>Relatórios PDF</span></div>
                    </div>

                    <h3>Frontend</h3>
                    <div class="sp-stack-grid">
                        <div class="sp-stack-item"><strong>Blade + Alpine.js 3</strong><span>UI server-side reativa</span></div>
                        <div class="sp-stack-item"><strong>Leaflet 1.9</strong><span>Mapas e marcadores</span></div>
                        <div class="sp-stack-item"><strong>Vite 7 + Node 22</strong><span>Build de assets</span></div>
                        <div class="sp-stack-item"><strong>Service Worker</strong><span>PWA · cache offline</span></div>
                        <div class="sp-stack-item"><strong>IndexedDB</strong><span>Fila local de fotos</span></div>
                        <div class="sp-stack-item"><strong>Chart.js 4</strong><span>Dashboard gestão</span></div>
                    </div>

                    <h3>Pipeline de fotografias</h3>
                    <div class="sp-pipeline">Câmera/galeria → compactação WebP no cliente (máx. 1920 px)
→ IndexedDB (offline) → POST /api/vistorias/fotos ou /api/moradores/fotos
→ Spatie MediaLibrary (storage/app/public/)
→ fila Redis media-conversions → thumb 300×300 + preview 800×600 (WebP)</div>

                    <div class="sp-table-wrap">
                        <table class="sp-table">
                            <thead>
                                <tr><th>Derivação</th><th>Formato</th><th class="num">Tamanho médio</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>Original (pós-compactação cliente)</td><td>WebP/JPEG</td><td class="num">~300 KB</td></tr>
                                <tr><td><code>thumb</code></td><td>WebP 300×300</td><td class="num">~30 KB</td></tr>
                                <tr><td><code>preview</code></td><td>WebP 800×600</td><td class="num">~100 KB</td></tr>
                                <tr class="total"><td>Total por fotografia no servidor</td><td>3 arquivos</td><td class="num">~430 KB</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- 2. Contexto --}}
            <div class="card sp-section">
                <div class="card-body">
                    <h2>2. Contexto dos dados analíticos</h2>
                    <p>O banco consolida três origens distintas, que devem ser interpretadas separadamente nos gráficos:</p>
                    <ul>
                        <li><strong>Legado POPRUA Geo (2019–2025):</strong> vistorias históricas de abordagem social, com volume estável de ~2.000–2.400 registros/semestre.</li>
                        <li><strong>Migração ETL (jan–fev/2026):</strong> carga única de vistorias (07/01/2026) e pontos (24/02/2026) do sistema legado.</li>
                        <li><strong>Operação SIZEM CRAS (pós-migração):</strong> cadastro orgânico de pontos, zeladorias e moradores no novo fluxo de zeladoria urbana.</li>
                    </ul>
                    <div class="sp-kpi-row">
                        <div class="sp-kpi"><div class="v">{{ number_format($totais['vistorias'], 0, ',', '.') }}</div><div class="l">vistorias (total acumulado)</div></div>
                        <div class="sp-kpi"><div class="v">{{ number_format($totais['pontos'], 0, ',', '.') }}</div><div class="l">pontos cadastrados</div></div>
                        <div class="sp-kpi"><div class="v">{{ number_format($totais['moradores'], 0, ',', '.') }}</div><div class="l">moradores (SIZEM)</div></div>
                        <div class="sp-kpi green"><div class="v">{{ number_format($totais['vistoriasPosEtl'], 0, ',', '.') }}</div><div class="l">vistorias pós-ETL (2026)</div></div>
                        <div class="sp-kpi green"><div class="v">{{ number_format($totais['pontosOrganicosPosEtl'], 0, ',', '.') }}</div><div class="l">pontos orgânicos pós-ETL</div></div>
                        <div class="sp-kpi"><div class="v">{{ number_format($totais['fotografias'], 0, ',', '.') }}</div><div class="l">fotografias armazenadas</div></div>
                    </div>
                </div>
            </div>

            {{-- 3. Vistorias --}}
            <div class="card sp-section">
                <div class="card-body">
                    <h2>3. Vistorias (zeladorias) por semestre</h2>
                    <p>Série histórica do legado Geo (2019–2025) e operação SIZEM em 2026, excluindo o dia de carga ETL (07/01/2026).</p>

                    <div class="sp-legend">
                        <span><i style="background:#6b7280"></i> Legado POPRUA Geo</span>
                        <span><i style="background:#184186"></i> Operação SIZEM (orgânico)</span>
                    </div>

                    <div class="sp-chart-box"><canvas id="chartVistorias"></canvas></div>

                    <div class="sp-table-wrap">
                        <table class="sp-table">
                            <thead>
                                <tr><th>Semestre</th><th class="num">Vistorias</th><th>Origem</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($dados['vistoriasSemestre'] as $row)
                                    <tr>
                                        <td>{{ $row['semestre'] }}</td>
                                        <td class="num">{{ number_format($row['total'], 0, ',', '.') }}</td>
                                        <td>{{ $row['origem'] === 'legado' ? 'POPRUA Geo (legado)' : 'SIZEM orgânico (sem bulk ETL 07/01)' }}</td>
                                    </tr>
                                @endforeach
                                <tr class="total">
                                    <td>Operação pós-ETL (desde 08/01/2026)</td>
                                    <td class="num">{{ number_format($totais['vistoriasPosEtl'], 0, ',', '.') }}</td>
                                    <td>Desde 08/01/2026</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <h3>Tendência mensal — operação SIZEM (pós 08/01/2026)</h3>
                    <div class="sp-chart-box"><canvas id="chartVistoriasMes"></canvas></div>

                    <div class="sp-callout">
                        <strong>Leitura:</strong> após a migração, o ritmo orgânico de zeladorias intensificou a partir de mar/2026.
                        Projeções futuras devem usar a operação orgânica como base, não o volume ETL.
                    </div>
                </div>
            </div>

            {{-- 4. Pontos --}}
            <div class="card sp-section">
                <div class="card-body">
                    <h2>4. Pontos georreferenciados por semestre</h2>
                    <p>Cadastro de pontos físicos (endereços com coordenadas). O mapeamento SIZEM intensificou a partir de 2024; a migração ETL consolidou a base legada em fev/2026.</p>

                    <div class="sp-chart-box"><canvas id="chartPontos"></canvas></div>

                    <div class="sp-table-wrap">
                        <table class="sp-table">
                            <thead>
                                <tr>
                                    <th>Semestre</th>
                                    <th class="num">Cadastro orgânico</th>
                                    <th class="num">Migração ETL</th>
                                    <th class="num">Total semestre</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($pontosSemestre as $row)
                                    <tr>
                                        <td>{{ $row['semestre'] }}</td>
                                        <td class="num">{{ number_format($row['organico'], 0, ',', '.') }}</td>
                                        <td class="num">{{ $row['etl'] ? number_format($row['etl'], 0, ',', '.') : '—' }}</td>
                                        <td class="num">{{ number_format($row['organico'] + $row['etl'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                                <tr class="total">
                                    <td>Total acumulado</td>
                                    <td class="num">{{ number_format($totalOrganico, 0, ',', '.') }}</td>
                                    <td class="num">{{ number_format($totalEtl, 0, ',', '.') }}</td>
                                    <td class="num">{{ number_format($totais['pontos'], 0, ',', '.') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="sp-callout purple">
                        <strong>ETL fev/2026:</strong> carga única de pontos importados em 24/02/2026.
                        Cadastro orgânico adicional pós-março/2026: <strong>{{ number_format($totais['pontosOrganicosPosEtl'], 0, ',', '.') }} pontos</strong>.
                    </div>
                </div>
            </div>

            {{-- 5. Fotografias --}}
            <div class="card sp-section">
                <div class="card-body">
                    <h2>5. Fotografias — situação atual e nova versão</h2>

                    <h3>5.1 Comportamento atual (dados reais)</h3>
                    <div class="sp-kpi-row">
                        <div class="sp-kpi">
                            <div class="v">{{ number_format($fotoStats['media'], 1, ',', '.') }}</div>
                            <div class="l">média de fotos/vistoria<br>(com ao menos 1 foto)</div>
                        </div>
                        <div class="sp-kpi">
                            <div class="v">{{ number_format($fotoStats['comFoto'], 0, ',', '.') }}</div>
                            <div class="l">vistorias com fotografia</div>
                        </div>
                        <div class="sp-kpi">
                            <div class="v">{{ $fotoStats['pctDezOuMais'] }}%</div>
                            <div class="l">vistorias com ≥ 10 fotos</div>
                        </div>
                        <div class="sp-kpi">
                            <div class="v">{{ number_format($fotoStats['morador'], 0, ',', '.') }}</div>
                            <div class="l">fotos de moradores</div>
                        </div>
                    </div>

                    <div class="sp-chart-box"><canvas id="chartDistFotos"></canvas></div>

                    <p>Distribuição atual concentrada em 1–9 fotos por vistoria (legado Geo).
                        Apenas <strong>{{ number_format($fotoStats['comDezOuMais'], 0, ',', '.') }} vistorias ({{ $fotoStats['pctDezOuMais'] }}%)</strong>
                        já atingem o patamar de 10+ fotos previsto para a nova versão.</p>

                    <h3>5.2 Premissas da nova versão SIZEM</h3>
                    <div class="sp-callout">
                        <strong>Fotografias de zeladorias (vistorias):</strong> com upload offline-first, compactação WebP e fluxo dedicado na aba Fotos, a operação tende a atingir média de <strong>10 a 15 fotografias por vistoria</strong>.
                    </div>
                    <div class="sp-callout warn">
                        <strong>Fotografias individuais de moradores:</strong> além das fotos vinculadas ao ponto/vistoria, está previsto cadastro de <strong>fotografias individuais por morador</strong> (coleção <code>fotos</code> no Spatie MediaLibrary).
                    </div>

                    <h3>5.3 Fotografias registradas por mês</h3>
                    <div class="sp-chart-box"><canvas id="chartFotosMes"></canvas></div>
                </div>
            </div>

            {{-- 6. Projeção --}}
            <div class="card sp-section">
                <div class="card-body">
                    <h2>6. Projeção de uso — horizonte 5 anos</h2>
                    <p>Projeção baseada no ritmo orgânico pós-ETL, adoção progressiva de 10–15 fotos/vistoria e inclusão de fotos individuais de moradores.</p>

                    <h3>6.1 Premissas de projeção</h3>
                    <div class="sp-table-wrap">
                        <table class="sp-table">
                            <thead><tr><th>Premissa</th><th>Ano 1</th><th>Ano 3</th><th>Ano 5 (pleno)</th></tr></thead>
                            <tbody>
                                <tr><td>Pontos ativos (estoque)</td><td class="num">~3.500</td><td class="num">~4.500</td><td class="num">~5.500</td></tr>
                                <tr><td>Vistorias/ano</td><td class="num">~4.000</td><td class="num">~10.000</td><td class="num">~14.000</td></tr>
                                <tr><td>Fotos/vistoria (média)</td><td class="num">8</td><td class="num">12</td><td class="num">13</td></tr>
                                <tr><td>Fotos de moradores/ano</td><td class="num">~800</td><td class="num">~4.000</td><td class="num">~8.000</td></tr>
                                <tr class="highlight"><td>Faixa fotos/vistoria (meta operacional)</td><td colspan="3" style="text-align:center"><strong>10 – 15 fotografias</strong> por zeladoria concluída</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <h3>6.2 Volume de fotografias projetado</h3>
                    <div class="sp-chart-box tall"><canvas id="chartProjFotos"></canvas></div>

                    <div class="sp-table-wrap">
                        <table class="sp-table">
                            <thead>
                                <tr>
                                    <th>Ano</th>
                                    <th class="num">Vistorias</th>
                                    <th class="num">Fotos/vistoria</th>
                                    <th class="num">Fotos vistorias</th>
                                    <th class="num">Fotos moradores</th>
                                    <th class="num">Total fotos/ano</th>
                                    <th class="num">Acumulado</th>
                                    <th class="num">Mídia acum.*</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dados['projecaoAnual'] as $row)
                                    <tr>
                                        <td>{{ $row['ano'] }}</td>
                                        <td class="num">{{ number_format($row['vistorias'], 0, ',', '.') }}</td>
                                        <td class="num">{{ $row['fotosPorVistoria'] }}</td>
                                        <td class="num">{{ number_format($row['fotosVistorias'], 0, ',', '.') }}</td>
                                        <td class="num">{{ number_format($row['fotosMoradores'], 0, ',', '.') }}</td>
                                        <td class="num"><strong>{{ number_format($row['totalAno'], 0, ',', '.') }}</strong></td>
                                        <td class="num">{{ number_format($row['acumulado'], 0, ',', '.') }}</td>
                                        <td class="num">~{{ number_format($row['midiaGb'], 1, ',', '.') }} GB</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p style="font-size:var(--text-xs);color:var(--text-muted)">
                        * Mídia acumulada = acumulado de fotos × 430 KB (original + thumb + preview).
                        Base legado existente: {{ number_format($totais['fotografias'], 0, ',', '.') }} fotografias.
                    </p>

                    <h3>6.3 Cenários alternativos (ano 5 — operação plena)</h3>
                    <div class="sp-table-wrap">
                        <table class="sp-table">
                            <thead>
                                <tr>
                                    <th>Cenário</th>
                                    <th class="num">Vistorias/ano</th>
                                    <th class="num">Fotos/vistoria</th>
                                    <th class="num">Fotos moradores</th>
                                    <th class="num">Total fotos/ano</th>
                                    <th class="num">Mídia/ano</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($dados['cenariosAno5'] as $cenario)
                                    <tr @class(['highlight' => $cenario['nome'] === 'Referência'])>
                                        <td>@if ($cenario['nome'] === 'Referência')<strong>{{ $cenario['nome'] }}</strong>@else{{ $cenario['nome'] }}@endif</td>
                                        <td class="num">{{ number_format($cenario['vistorias'], 0, ',', '.') }}</td>
                                        <td class="num">{{ number_format($cenario['fotosPorVistoria'], 1, ',', '.') }}</td>
                                        <td class="num">{{ number_format($cenario['fotosMoradores'], 0, ',', '.') }}</td>
                                        <td class="num">{{ number_format($cenario['totalFotos'], 0, ',', '.') }}</td>
                                        <td class="num">~{{ $cenario['midiaGb'] }} GB</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if ($ultimaProjecao)
                        <div class="sp-callout">
                            <strong>Consolidação 5 anos (cenário referência):</strong>
                            ~{{ number_format($ultimaProjecao['acumulado'] - $totais['fotografias'], 0, ',', '.') }} fotografias novas acumuladas ·
                            ~<strong>{{ number_format($ultimaProjecao['midiaGb'], 0, ',', '.') }} GB</strong> de mídia total.
                        </div>
                    @endif
                </div>
            </div>

            {{-- 7. Recomendação de infraestrutura --}}
            <div class="card sp-section">
                <div class="card-body">
                    <h2>7. Recomendação de infraestrutura — servidores dedicados</h2>
                    <p>Com o crescimento projetado de zeladorias, fotografias e consultas geoespaciais, recomenda-se a segregação da infraestrutura em servidores dedicados a partir do <strong>Ano 2</strong>.</p>

                    <h3>7.1 Servidor de banco de dados dedicado</h3>
                    <p>O PostgreSQL com PostGIS executa queries geoespaciais (ST_DWithin, ST_MakeEnvelope) sobre milhares de pontos com índices GIST. À medida que a base de pontos e vistorias cresce, essas consultas competem por CPU e memória com a aplicação.</p>

                    <div class="sp-table-wrap">
                        <table class="sp-table">
                            <thead>
                                <tr><th>Aspecto</th><th>Situação atual (container único)</th><th>Recomendação (servidor dedicado)</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>Engine</td><td>PostgreSQL 17 + PostGIS 3.5 no mesmo host da aplicação</td><td>Instância dedicada (VM ou managed — ex.: RDS, Cloud SQL)</td></tr>
                                <tr><td>CPU / RAM</td><td>Compartilhada com PHP-FPM, Redis e Nginx</td><td>4 vCPU / 16 GB RAM (ajustar shared_buffers, work_mem)</td></tr>
                                <tr><td>Armazenamento</td><td>Disco do container (SSD genérico)</td><td>SSD NVMe, IOPS provisionado p/ índices GIST</td></tr>
                                <tr><td>Backup</td><td>pg_dump via cron no container</td><td>WAL archiving + point-in-time recovery (PITR)</td></tr>
                                <tr><td>Réplica de leitura</td><td>Não disponível</td><td>Read replica para dashboards e relatórios PDF pesados</td></tr>
                                <tr><td>Conexão</td><td>Socket local / localhost</td><td>Rede privada (VPC) via PgBouncer (pool de conexões)</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="sp-callout">
                        <strong>Gatilho sugerido:</strong> migrar o banco quando as queries de mapa ou geração de relatório ultrapassarem 500 ms (p95), ou quando a base de mídia comprometer I/O do disco compartilhado.
                    </div>

                    <h3>7.2 Servidor / serviço de arquivos dedicado</h3>
                    <p>As fotografias são o maior vetor de crescimento de armazenamento. Cada foto gera 3 derivações (~430 KB total); a projeção de 5 anos aponta <strong>{{ number_format($ultimaProjecao['midiaGb'] ?? 0, 0, ',', '.') }} GB</strong> de mídia acumulada. Manter arquivos no mesmo volume do banco e da aplicação gera contenção de I/O e dificulta backup granular.</p>

                    <div class="sp-table-wrap">
                        <table class="sp-table">
                            <thead>
                                <tr><th>Aspecto</th><th>Situação atual (storage local)</th><th>Recomendação (storage dedicado)</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>Localização</td><td>storage/app/public/ no volume Docker</td><td>NFS montado ou object storage (S3, MinIO, Azure Blob)</td></tr>
                                <tr><td>Capacidade</td><td>Limitada ao disco do host</td><td>Escalável sob demanda (object storage ilimitado)</td></tr>
                                <tr><td>Redundância</td><td>Sem redundância (backup manual)</td><td>Replicação automática + versionamento de objetos</td></tr>
                                <tr><td>CDN</td><td>Servido pelo Nginx do container</td><td>CDN edge (CloudFront, Cloudflare R2) para thumbs/previews</td></tr>
                                <tr><td>Custo estimado (Ano 5)</td><td>—</td><td>~R$ 50–80/mês (S3 Standard, {{ number_format($ultimaProjecao['midiaGb'] ?? 0, 0, ',', '.') }} GB)</td></tr>
                                <tr><td>Integração Laravel</td><td>Filesystem driver <code>local</code></td><td>Filesystem driver <code>s3</code> (Spatie MediaLibrary suporta nativamente)</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="sp-callout warn">
                        <strong>Benefício imediato:</strong> separar o storage de mídia permite backup incremental das fotos sem impactar pg_dump, reduz I/O no disco do banco e viabiliza servir thumbnails via CDN — melhorando a experiência em campo (3G/4G).
                    </div>

                    <h3>7.3 Arquitetura alvo sugerida</h3>
                    <div class="sp-pipeline">┌─────────────┐     ┌──────────────────────┐     ┌───────────────────────┐
│  Aplicação   │────▸│  Banco de Dados      │     │  Armazenamento        │
│  PHP-FPM     │     │  PostgreSQL + PostGIS │     │  de Arquivos          │
│  + Nginx     │     │  (servidor dedicado)  │     │  (S3/MinIO/NFS)       │
│  + Redis     │     │                      │     │                       │
└──────┬───────┘     │  • 4 vCPU / 16 GB    │     │  • Object storage     │
       │             │  • SSD NVMe          │     │  • CDN para thumbs    │
       │             │  • PITR + réplica    │     │  • Backup incremental │
       ├────────────▸│  • PgBouncer         │     │  • Driver s3 Laravel  │
       │             └──────────────────────┘     └───────────────────────┘
       │                                                    ▲
       └────────────────────────────────────────────────────┘
                    upload via Spatie MediaLibrary</div>
                </div>
            </div>

            {{-- 8. Síntese --}}
            <div class="card sp-section">
                <div class="card-body">
                    <h2>8. Síntese</h2>
                    <ul>
                        <li>A aplicação SIZEM opera sobre stack <strong>Laravel 12 / PHP 8.4 / PostgreSQL+PostGIS / Redis</strong>, com fotos offline-first via PWA e Spatie MediaLibrary.</li>
                        <li>O histórico de vistorias (2019–2025) reflete operação POPRUA Geo (~2.000–2.400/semestre); a operação SIZEM pós-migração registra crescimento orgânico em 2026.</li>
                        <li>Pontos mapeados: crescimento orgânico acelerado a partir de 2024, sobre base ETL de fev/2026.</li>
                        <li>Fotografias hoje: média <strong>{{ number_format($fotoStats['media'], 1, ',', '.') }} fotos/vistoria</strong> (legado); a nova versão tende a <strong>10–15 fotos/vistoria</strong>, mais fotos individuais de moradores.</li>
                        @if ($ultimaProjecao)
                            <li>Projeção 5 anos (referência): ~<strong>{{ number_format($ultimaProjecao['acumulado'], 0, ',', '.') }} fotos</strong> acumuladas · ~<strong>{{ number_format($ultimaProjecao['midiaGb'], 0, ',', '.') }} GB</strong> de armazenamento de mídia.</li>
                        @endif
                    </ul>
                </div>
            </div>

            <p class="sp-footer-note">
                SIZEM · Stack e projeção de uso · Dados extraídos em tempo real de {{ config('database.connections.pgsql.database', 'poprua_cras') }}
            </p>
        </div>
    </div>
@endsection

@push('scripts')
<script type="application/json" id="stack-projecao-data">@json($dados['chartPayload'])</script>
@vite('resources/js/stack-projecao.js')
@endpush
