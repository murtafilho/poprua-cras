@extends('layouts.app')

@section('title', 'Projeção de Uso')

@section('header')
    <h1 class="page-title">Projeção de Uso</h1>
@endsection

@push('styles')
<style>
    .sp-doc { max-width: 52rem; }
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
        margin-bottom: var(--space-4);
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
    .sp-section p, .sp-section li {
        font-size: var(--text-sm);
        color: var(--text-secondary);
        line-height: 1.6;
    }
    .sp-section ul { margin: var(--space-2) 0 var(--space-3) var(--space-5); }
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
        border-left: 4px solid var(--accent-primary);
        background: var(--bg-tertiary);
        padding: var(--space-3);
        border-radius: 0 var(--radius-md) var(--radius-md) 0;
        font-size: var(--text-sm);
        margin: var(--space-3) 0;
    }
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
        $ultimaProjecao = collect($dados['projecaoAnual'])->last();
    @endphp

    <div class="page-content">
        <div class="container sp-doc">
            <p class="sp-intro">
                Projeção de crescimento do SIZEM para os próximos cinco anos, com base no ritmo operacional
                pós-migração, adoção progressiva de fotografias em zeladorias e cadastro de moradores.
            </p>
            <div class="sp-meta">
                <span>Sistema: SIZEM — Zeladoria Urbana / CRAS</span>
                <span>Atualizado em: {{ $dados['geradoEm'] }}</span>
            </div>

            <div class="card sp-section">
                <div class="card-body">
                    <h2>1. Premissas de projeção</h2>
                    <div class="sp-table-wrap">
                        <table class="sp-table">
                            <thead>
                                <tr><th>Premissa</th><th>Ano 1</th><th>Ano 3</th><th>Ano 5 (pleno)</th></tr>
                            </thead>
                            <tbody>
                                <tr><td>Pontos ativos (estoque)</td><td class="num">~3.500</td><td class="num">~4.500</td><td class="num">~5.500</td></tr>
                                <tr><td>Vistorias/ano</td><td class="num">~4.000</td><td class="num">~10.000</td><td class="num">~14.000</td></tr>
                                <tr><td>Fotos/vistoria (média)</td><td class="num">8</td><td class="num">12</td><td class="num">13</td></tr>
                                <tr><td>Fotos de moradores/ano</td><td class="num">~800</td><td class="num">~4.000</td><td class="num">~8.000</td></tr>
                                <tr class="highlight">
                                    <td>Meta operacional de fotos/vistoria</td>
                                    <td colspan="3" style="text-align:center"><strong>10 – 15 fotografias</strong> por zeladoria concluída</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p>Cada fotografia gera três derivações no servidor (original, miniatura e preview), com volume médio de ~430 KB por conjunto.</p>
                </div>
            </div>

            <div class="card sp-section">
                <div class="card-body">
                    <h2>2. Volume projetado (5 anos)</h2>
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
                        * Mídia acumulada estimada a partir do acumulado de fotos × 430 KB.
                        Base atual: {{ number_format($totais['fotografias'], 0, ',', '.') }} fotografias já armazenadas.
                    </p>
                </div>
            </div>

            <div class="card sp-section">
                <div class="card-body">
                    <h2>3. Cenários alternativos (ano 5 — operação plena)</h2>
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
                            <strong>Cenário referência (5 anos):</strong>
                            ~{{ number_format($ultimaProjecao['acumulado'] - $totais['fotografias'], 0, ',', '.') }} fotografias novas ·
                            ~<strong>{{ number_format($ultimaProjecao['midiaGb'], 0, ',', '.') }} GB</strong> de mídia acumulada.
                        </div>
                    @endif
                </div>
            </div>

            <div class="card sp-section">
                <div class="card-body">
                    <h2>4. Necessidades de infraestrutura</h2>
                    <p>Com o crescimento projetado de zeladorias, consultas geoespaciais e armazenamento de fotografias, recomenda-se a segregação da infraestrutura em ambientes dedicados, conforme padrões de provisionamento da Prefeitura:</p>
                    <ul>
                        <li><strong>Banco de dados dedicado</strong> — PostgreSQL com extensão geoespacial, separado da camada de aplicação, com política de backup e recuperação adequada ao volume de dados transacionais e consultas de mapa.</li>
                        <li><strong>Armazenamento de arquivos dedicado</strong> — serviço separado para as fotografias e derivações, evitando contenção de disco com o banco e permitindo backup e expansão independentes.</li>
                        <li><strong>Aplicação</strong> — manter servidor web, processamento e filas de conversão de mídia desacoplados dos demais componentes.</li>
                    </ul>
                    @if ($ultimaProjecao)
                        <p>O cenário referência indica necessidade de capacidade para ~<strong>{{ number_format($ultimaProjecao['midiaGb'], 0, ',', '.') }} GB</strong> de mídia acumulada ao final do quinto ano, além do crescimento contínuo de registros de pontos, vistorias e moradores.</p>
                    @endif
                </div>
            </div>

            <p class="sp-footer-note">
                SIZEM · Projeção de uso · Dados gerados em {{ $dados['geradoEm'] }}
            </p>
        </div>
    </div>
@endsection
