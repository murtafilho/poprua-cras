@extends('layouts.app')

@section('title', 'Dashboard')

@section('header')
    <a href="{{ route('dashboard') }}" class="flex items-center gap-3" style="color: #FFFFFF;">
        <x-application-logo class="mobile-header-logo" />
        <span class="mobile-header-title">{{ config('app.brand', 'SIZEM') }}</span>
    </a>
    <div class="flex items-center gap-2">
        <a href="{{ route('profile.edit') }}" class="btn btn-ghost btn-icon" style="color: rgba(255,255,255,0.8);">
            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
        </a>
        <form method="POST" action="{{ route('logout') }}" class="inline">
            @csrf
            <button type="submit" class="btn btn-ghost btn-icon" style="color: rgba(255,255,255,0.8);" x-on:click="if (!confirm('{{ __('Deseja sair?') }}')) $event.preventDefault()">
                <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
            </button>
        </form>
    </div>
@endsection

@section('content')
    <div class="page-content" style="display: flex; flex-direction: column; height: 100%;">
        <div class="container" style="flex: 1; display: flex; flex-direction: column;">
            {{-- Indicadores --}}
            <div class="grid grid-cols-3 grid-mobile-1 gap-3" style="margin-top: var(--space-4);">
                <div class="card" style="text-align: center;">
                    <div class="card-body">
                        <p class="text-muted" style="font-size: var(--text-xs); margin-bottom: var(--space-1);">Total de Pontos</p>
                        <p style="font-size: var(--text-2xl); font-weight: var(--font-bold); color: var(--accent-primary);">{{ number_format($totalPontos, 0, ',', '.') }}</p>
                    </div>
                </div>
                <div class="card" style="text-align: center;">
                    <div class="card-body">
                        <p class="text-muted" style="font-size: var(--text-xs); margin-bottom: var(--space-1);">Pontos Vistoriados</p>
                        <p style="font-size: var(--text-2xl); font-weight: var(--font-bold); color: var(--status-info);">{{ number_format($totais->pontos_vistoriados, 0, ',', '.') }}</p>
                    </div>
                </div>
                <div class="card" style="text-align: center;">
                    <div class="card-body">
                        <p class="text-muted" style="font-size: var(--text-xs); margin-bottom: var(--space-1);">Total de Vistorias</p>
                        <p style="font-size: var(--text-2xl); font-weight: var(--font-bold); color: var(--status-success);">{{ number_format($totais->vistorias, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            {{-- Grafico: Evolucao do Fenomeno --}}
            <div class="card" style="margin-top: var(--space-4); flex: 1; display: flex; flex-direction: column;">
                <div class="card-body" style="flex: 1; display: flex; flex-direction: column;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-4); flex-wrap: wrap; gap: var(--space-2);">
                        <h3 style="font-size: var(--text-sm); font-weight: var(--font-semibold); display: flex; align-items: center; gap: var(--space-2);">
                            <svg style="width: 18px; height: 18px; color: var(--accent-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Evolucao do Fenomeno
                        </h3>

                        {{-- Filtro por resultado --}}
                        <div style="display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap;">
                            <label class="text-muted" style="font-size: var(--text-xs);">Filtrar:</label>
                            <div style="display: flex; gap: var(--space-1); flex-wrap: wrap;">
                                <button type="button" class="btn btn-sm chart-filter active" data-series="all" style="font-size: 11px;">Todos</button>
                                <button type="button" class="btn btn-sm chart-filter" data-series="ativos" style="font-size: 11px; border-color: #8b5cf6; color: #8b5cf6;">Ativos</button>
                                <button type="button" class="btn btn-sm chart-filter" data-series="persiste" style="font-size: 11px; border-color: #ef4444; color: #ef4444;">Persiste</button>
                                <button type="button" class="btn btn-sm chart-filter" data-series="impactado_parcial" style="font-size: 11px; border-color: #f59e0b; color: #f59e0b;">Impactado</button>
                                <button type="button" class="btn btn-sm chart-filter" data-series="deixou_ocorrer" style="font-size: 11px; border-color: #22c55e; color: #22c55e;">Extinto</button>
                                <button type="button" class="btn btn-sm chart-filter" data-series="ausente" style="font-size: 11px; border-color: #94a3b8; color: #94a3b8;">Ausente</button>
                                <button type="button" class="btn btn-sm chart-filter" data-series="nao_constatado" style="font-size: 11px; border-color: #3b82f6; color: #3b82f6;">Nao constatado</button>
                                <button type="button" class="btn btn-sm chart-filter" data-series="conformidade" style="font-size: 11px; border-color: #10b981; color: #10b981;">Conformidade</button>
                                <button type="button" class="btn btn-sm chart-filter" data-series="sem_vistoria" style="font-size: 11px; border-color: #94a3b8; color: #64748b;">Sem vistoria</button>
                            </div>
                        </div>
                    </div>

                    <div style="position: relative; flex: 1; min-height: 300px;">
                        <canvas id="chart-evolucao"></canvas>
                    </div>
                </div>
            </div>

            {{-- Legenda: Como cada linha e calculada --}}
            <div style="margin-top: var(--space-4); display: flex; flex-direction: column; gap: var(--space-4);">

                {{-- Regra geral --}}
                <div class="card" style="border-left: 3px solid var(--accent-primary);">
                    <div class="card-body" style="padding: var(--space-3);">
                        <p style="font-weight: var(--font-semibold); font-size: var(--text-sm); margin-bottom: var(--space-1);">Como ler o grafico</p>
                        <p class="text-muted" style="font-size: var(--text-xs); line-height: 1.5;">Cada ponto possui um unico status por mes, definido pelo <strong>resultado da ultima vistoria</strong> (por data_abordagem) ate o final daquele mes. Se nao recebe nova vistoria, mantem o status anterior. Os status sao <strong>mutuamente excludentes</strong> — um ponto so aparece em uma categoria por vez. A linha "Pontos Ativos" e a soma de 4 desses status.</p>
                    </div>
                </div>

                {{-- Grupo: Ativos (somam para Pontos Ativos) --}}
                <div class="card">
                    <div class="card-body" style="padding: var(--space-3);">
                        <p style="font-weight: var(--font-semibold); font-size: var(--text-sm); margin-bottom: var(--space-1); display: flex; align-items: center; gap: var(--space-2);"><span style="width: 16px; height: 3px; background: #8b5cf6; border-radius: 2px; flex-shrink: 0;"></span> Pontos Ativos (agregado)</p>
                        <p class="text-muted" style="font-size: var(--text-xs); line-height: 1.5; margin-bottom: var(--space-3);">Soma das 4 categorias abaixo. Se esta linha sobe, o fenomeno esta crescendo; se desce, esta sob controle.</p>

                        <div style="display: flex; flex-direction: column; gap: var(--space-2); padding-left: var(--space-3); border-left: 1px solid var(--border-primary);">
                            <div style="display: flex; align-items: flex-start; gap: var(--space-2);">
                                <span style="width: 12px; height: 3px; background: #ef4444; border-radius: 2px; margin-top: 7px; flex-shrink: 0;"></span>
                                <div>
                                    <p style="font-weight: var(--font-medium); font-size: var(--text-xs);">Persiste</p>
                                    <p class="text-muted" style="font-size: var(--text-xs); line-height: 1.4;">Fenomeno continua inalterado. A acao de fiscalizacao nao produziu efeito.</p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: var(--space-2);">
                                <span style="width: 12px; height: 3px; background: #f59e0b; border-radius: 2px; margin-top: 7px; flex-shrink: 0;"></span>
                                <div>
                                    <p style="font-weight: var(--font-medium); font-size: var(--text-xs);">Impactado Parcialmente</p>
                                    <p class="text-muted" style="font-size: var(--text-xs); line-height: 1.4;">A acao produziu efeito mas o fenomeno nao foi eliminado.</p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: var(--space-2);">
                                <span style="width: 12px; height: 3px; background: #94a3b8; border-radius: 2px; margin-top: 7px; flex-shrink: 0;"></span>
                                <div>
                                    <p style="font-weight: var(--font-medium); font-size: var(--text-xs);">PSR Ausente</p>
                                    <p class="text-muted" style="font-size: var(--text-xs); line-height: 1.4;">Nao havia pessoas no momento da vistoria, mas o ponto permanece ativo.</p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: flex-start; gap: var(--space-2);">
                                <span style="width: 12px; height: 3px; background: #10b981; border-radius: 2px; margin-top: 7px; flex-shrink: 0;"></span>
                                <div>
                                    <p style="font-weight: var(--font-medium); font-size: var(--text-xs);">Em Conformidade</p>
                                    <p class="text-muted" style="font-size: var(--text-xs); line-height: 1.4;">Regularizado conforme Portaria Conjunta, mas permanece sob monitoramento.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Grupo: Extintos (subtraem do total) --}}
                <div>
                    <p style="font-size: var(--text-xs); font-weight: var(--font-semibold); color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--space-2);">
                        Fenomeno resolvido — subtraem do total
                    </p>
                    <div class="grid grid-cols-2 grid-mobile-1 gap-3">
                        <div class="card">
                            <div class="card-body" style="padding: var(--space-3);">
                                <p style="font-weight: var(--font-semibold); font-size: var(--text-sm); margin-bottom: var(--space-1); display: flex; align-items: center; gap: var(--space-2);"><span style="width: 12px; height: 3px; background: #22c55e; border-radius: 2px; flex-shrink: 0;"></span> Deixou de Ocorrer</p>
                                <p class="text-muted" style="font-size: var(--text-xs); line-height: 1.5;">O fenomeno foi eliminado. O ponto sai da contagem de ativos.</p>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-body" style="padding: var(--space-3);">
                                <p style="font-weight: var(--font-semibold); font-size: var(--text-sm); margin-bottom: var(--space-1); display: flex; align-items: center; gap: var(--space-2);"><span style="width: 12px; height: 3px; background: #3b82f6; border-radius: 2px; flex-shrink: 0;"></span> Nao Constatado</p>
                                <p class="text-muted" style="font-size: var(--text-xs); line-height: 1.5;">Nao ha evidencia de fenomeno no local. O ponto sai da contagem de ativos.</p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Grupo: Indeterminado --}}
                <div>
                    <p style="font-size: var(--text-xs); font-weight: var(--font-semibold); color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: var(--space-2);">
                        Indeterminado — nao contabilizado como ativo nem extinto
                    </p>
                    <div class="grid grid-cols-2 grid-mobile-1 gap-3">
                        <div class="card">
                            <div class="card-body" style="padding: var(--space-3);">
                                <p style="font-weight: var(--font-semibold); font-size: var(--text-sm); margin-bottom: var(--space-1); display: flex; align-items: center; gap: var(--space-2);"><span style="width: 12px; height: 3px; background: #cbd5e1; border-radius: 2px; flex-shrink: 0;"></span> Sem Resultado</p>
                                <p class="text-muted" style="font-size: var(--text-xs); line-height: 1.5;">Pontos cuja vistoria nao possui resultado registrado. Nao e possivel classificar.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Interpretacao e Projecoes --}}
            <div class="card" style="margin-top: var(--space-4);">
                <div class="card-body">
                    <h3 style="font-size: var(--text-sm); font-weight: var(--font-semibold); margin-bottom: var(--space-4); display: flex; align-items: center; gap: var(--space-2);">
                        <svg style="width: 18px; height: 18px; color: var(--status-info);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Interpretacao e Projecoes
                    </h3>

                    <div style="font-size: var(--text-sm); color: var(--text-secondary); line-height: 1.7;">

                        <h4 style="font-size: var(--text-sm); font-weight: var(--font-semibold); color: var(--text-primary); margin-bottom: var(--space-2);">Panorama Atual (Marco/2026)</h4>
                        <p style="margin-bottom: var(--space-3);">
                            O sistema monitora <strong>2.705 pontos</strong> de concentracao de populacao em situacao de rua em Belo Horizonte, com <strong>41.633 vistorias</strong> realizadas desde dezembro de 2018. Do total de pontos com vistoria:
                        </p>
                        <ul style="margin-bottom: var(--space-4); padding-left: var(--space-5);">
                            <li><span class="badge badge-danger" style="font-size: 10px;">476 pontos</span> com fenomeno que <strong>persiste</strong> (17,8%)</li>
                            <li><span class="badge badge-warning" style="font-size: 10px;">710 pontos</span> <strong>impactados parcialmente</strong> (26,5%)</li>
                            <li><span class="badge badge-success" style="font-size: 10px;">1.003 pontos</span> onde o fenomeno <strong>deixou de ocorrer</strong> (37,5%)</li>
                            <li><span class="badge badge-info" style="font-size: 10px;">241 pontos</span> com fenomeno <strong>nao constatado</strong> (9,0%)</li>
                            <li><span class="badge" style="font-size: 10px; background: var(--bg-tertiary);">158 pontos</span> <strong>em conformidade</strong> (5,9%)</li>
                            <li><span class="badge badge-default" style="font-size: 10px;">56 pontos</span> com <strong>PSR ausente</strong> no momento (2,1%)</li>
                        </ul>

                        <h4 style="font-size: var(--text-sm); font-weight: var(--font-semibold); color: var(--text-primary); margin-bottom: var(--space-2);">Tendencias Observadas (2020-2026)</h4>

                        <p style="margin-bottom: var(--space-2);"><strong style="color: var(--status-danger);">Queda na taxa de extincao:</strong></p>
                        <p style="margin-bottom: var(--space-3);">
                            A taxa de pontos extintos por semestre caiu de <strong>52,7%</strong> (1o sem/2020) para <strong>25,5%</strong> (1o sem/2025). Isso indica que os pontos remanescentes sao mais resistentes as acoes de fiscalizacao — possivelmente ligados a fixacao antiga, estruturas consolidadas ou cenas de uso caracterizadas.
                        </p>

                        <p style="margin-bottom: var(--space-2);"><strong style="color: #ef4444;">Estabilizacao do fenomeno "persiste":</strong></p>
                        <p style="margin-bottom: var(--space-3);">
                            A taxa de pontos com fenomeno persistente oscila entre <strong>41% e 51%</strong> desde o 2o semestre de 2021, indicando um nucleo duro de pontos que nao respondem as abordagens tradicionais. Em 2025, houve leve alta para 48-51%, sugerindo recrudescimento.
                        </p>

                        <p style="margin-bottom: var(--space-2);"><strong style="color: #f59e0b;">Crescimento de "impactado parcialmente":</strong></p>
                        <p style="margin-bottom: var(--space-3);">
                            Desde 2022, "impactado parcialmente" ultrapassou "persiste" como resultado mais frequente, representando entre <strong>35-48%</strong> dos pontos vistoriados. Isso sugere que as acoes produzem efeito temporario mas insuficiente para eliminar o fenomeno.
                        </p>

                        <p style="margin-bottom: var(--space-2);"><strong style="color: #22c55e;">Reducao de novos pontos extintos:</strong></p>
                        <p style="margin-bottom: var(--space-3);">
                            Em 2019, <strong>600 pontos</strong> deixaram de ocorrer. Em 2025, apenas <strong>106</strong>. A base de pontos "faceis de resolver" ja foi esgotada. Os pontos restantes exigem abordagens mais complexas e articuladas.
                        </p>

                        <h4 style="font-size: var(--text-sm); font-weight: var(--font-semibold); color: var(--text-primary); margin-top: var(--space-4); margin-bottom: var(--space-2);">Projecoes para 2030</h4>

                        <p style="margin-bottom: var(--space-3);">
                            Considerando as tendencias atuais e mantido o modelo operacional vigente:
                        </p>

                        <div style="display: flex; flex-direction: column; gap: var(--space-3); margin-bottom: var(--space-4);">
                            <div style="padding: var(--space-3); background: var(--bg-tertiary); border-radius: var(--radius-md); border-left: 3px solid #ef4444;">
                                <p style="font-weight: var(--font-semibold); color: var(--text-primary); margin-bottom: var(--space-1);">Cenario 1 — Manutencao do status quo</p>
                                <p>Sem mudanca na estrategia, projeta-se que <strong>~1.400 pontos</strong> permanecam ativos (persiste + impactado) em 2030, com taxa de extincao estabilizada em ~20%. O fenomeno continuara presente em nivel similar ao atual, com crescimento vegetativo de novos pontos compensando parcialmente as extincoes.</p>
                            </div>

                            <div style="padding: var(--space-3); background: var(--bg-tertiary); border-radius: var(--radius-md); border-left: 3px solid #f59e0b;">
                                <p style="font-weight: var(--font-semibold); color: var(--text-primary); margin-bottom: var(--space-1);">Cenario 2 — Intensificacao focada</p>
                                <p>Com abordagem diferenciada para os ~476 pontos "persiste" (articulacao intersetorial, assistencia social, saude mental), e possivel reduzir este grupo em <strong>30-40%</strong> ate 2030, baixando para ~300 pontos persistentes. Requer investimento em equipes multidisciplinares e acompanhamento longitudinal.</p>
                            </div>

                            <div style="padding: var(--space-3); background: var(--bg-tertiary); border-radius: var(--radius-md); border-left: 3px solid #22c55e;">
                                <p style="font-weight: var(--font-semibold); color: var(--text-primary); margin-bottom: var(--space-1);">Cenario 3 — Transformacao estrutural</p>
                                <p>Com politicas habitacionais, programas de transferencia de renda e rede de acolhimento expandida, o total de pontos ativos poderia cair para <strong>~600-800</strong> em 2030 (reducao de 40-50%). Este cenario depende de fatores externos ao controle da SUFIS: politica habitacional municipal, conjuntura economica e capacidade da rede de assistencia social.</p>
                            </div>
                        </div>

                        <h4 style="font-size: var(--text-sm); font-weight: var(--font-semibold); color: var(--text-primary); margin-bottom: var(--space-2);">Indicadores-Chave a Monitorar</h4>
                        <ul style="padding-left: var(--space-5);">
                            <li><strong>Taxa de extincao mensal:</strong> se cair abaixo de 15%, indica esgotamento do modelo atual</li>
                            <li><strong>Pontos "persiste" com fixacao antiga:</strong> nucleo mais critico, requer abordagem diferenciada</li>
                            <li><strong>Novos pontos por mes:</strong> media de 13 novos pontos/mes em 2025 — se acelerar, o fenomeno esta se expandindo</li>
                            <li><strong>Razao impactado/persiste:</strong> atualmente 1,49 — quanto maior, mais os pontos respondem as acoes (mesmo que parcialmente)</li>
                            <li><strong>Tempo medio entre vistorias:</strong> pontos com intervalo maior que 90 dias perdem acompanhamento efetivo</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>window.DASHBOARD_DADOS = @json($dadosMensais);</script>
@vite('resources/js/dashboard.js')
@endpush
