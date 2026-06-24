<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatório de Zeladoria nº {{ $vistoria->id }} — POPRUA / SUFIS</title>

    @php
        $verificationHash = strtoupper(substr(hash('sha256', $vistoria->id . '|' . ($vistoria->updated_at ?? $vistoria->created_at)), 0, 12));
        $verificationCode = substr($verificationHash, 0, 4) . '-' . substr($verificationHash, 4, 4) . '-' . substr($verificationHash, 8, 4);

        $ponto = $vistoria->ponto;
        $endAtu = $ponto?->enderecoAtualizado;
        $logradouro = trim(($endAtu->SIGLA_TIPO_LOGRADOURO ?? '') . ' ' . ($endAtu->NOME_LOGRADOURO ?? ''));
        $numero = $endAtu->NUMERO_IMOVEL ?? $ponto?->numero ?? '';
        $bairro = $endAtu->NOME_BAIRRO_OFICIAL ?? null;
        $regional = $endAtu->NOME_REGIONAL ?? null;

        $caracteristicas = [
            ['campo' => 'casal', 'label' => 'Casal', 'extra' => $vistoria->qtd_casais ? "({$vistoria->qtd_casais})" : null],
            ['campo' => 'num_reduzido', 'label' => 'Número reduzido'],
            ['campo' => 'catador_reciclados', 'label' => 'Catador de reciclados'],
            ['campo' => 'resistencia', 'label' => 'Resistência'],
            ['campo' => 'fixacao_antiga', 'label' => 'Fixação antiga'],
            ['campo' => 'excesso_objetos', 'label' => 'Excesso de objetos'],
            ['campo' => 'trafico_ilicitos', 'label' => 'Tráfico / ilícitos'],
            ['campo' => 'crianca_adolescente', 'label' => 'Criança / adolescente'],
            ['campo' => 'idosos', 'label' => 'Idosos'],
            ['campo' => 'gestante', 'label' => 'Gestante'],
            ['campo' => 'lgbtqiapn', 'label' => 'LGBTQIAPN+'],
            ['campo' => 'cena_uso_caracterizada', 'label' => 'Cena de uso caracterizada'],
            ['campo' => 'deficiente', 'label' => 'Deficiente'],
            ['campo' => 'agrupamento_quimico', 'label' => 'Agrupamento químico'],
            ['campo' => 'saude_mental', 'label' => 'Saúde mental'],
            ['campo' => 'animais', 'label' => 'Animais', 'extra' => $vistoria->qtd_animais ? "({$vistoria->qtd_animais})" : null],
        ];
        $fatoresAtivos = collect($caracteristicas)->filter(fn($c) => (bool) $vistoria->{$c['campo']});

        $encaminhamentos = collect([
            $vistoria->encaminhamento1, $vistoria->encaminhamento2, $vistoria->encaminhamento3,
            $vistoria->encaminhamento4, $vistoria->encaminhamento5, $vistoria->encaminhamento6,
        ])->filter();

        $fotos = $vistoria->getMedia('fotos')->filter(fn($m) => (bool) $m->getCustomProperty('publica', false))->values();

        $temFiscalizacao = $vistoria->conducao_forcas_seguranca || $vistoria->apreensao_fiscal
            || $vistoria->auto_fiscalizacao_aplicado
            || filled($vistoria->material_apreendido) || filled($vistoria->material_descartado);
        $temAbrigos = ($vistoria->qtd_abrigos_provisorios > 0) || count($tiposAbrigoSelecionados) > 0 || $vistoria->tipoAbrigoDesmontado;
        $temContagem = ($vistoria->quantidade_pessoas ?? 0) > 0 || ($vistoria->qtd_casais ?? 0) > 0 || $vistoria->animais || filled($vistoria->movimento_migratorio);

        $secs = [];
        $n = 1;
        $secs['id']  = $n++;
        $secs['loc'] = $n++;
        if ($vistoria->resultadoAcao) $secs['res'] = $n++;
        if ($temContagem) $secs['cnt'] = $n++;
        if ($fatoresAtivos->count() > 0) $secs['fat'] = $n++;
        if ($temAbrigos) $secs['abr'] = $n++;
        if ($temFiscalizacao || ($vistoria->qtd_kg ?? 0) > 0) $secs['fis'] = $n++;
        if ($encaminhamentos->count() > 0) $secs['enc'] = $n++;
        if (filled($vistoria->nomes_pessoas) || $vistoria->moradoresEntrada->count() > 0) $secs['pes'] = $n++;
        if (filled($vistoria->observacao)) $secs['obs'] = $n++;
        if ($fotos->count() > 0) $secs['fot'] = $n++;
    @endphp

    <style>
        @page {
            size: A4;
            margin: 2cm;

            @bottom-left {
                content: "Relatório de Zeladoria nº {{ $vistoria->id }}";
                font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
                font-size: 8pt;
                color: #9ca3af;
            }
            @bottom-right {
                content: counter(page) " / " counter(pages);
                font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
                font-size: 8pt;
                color: #9ca3af;
            }
        }

        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #1f2937;
            background: #fff;
            -webkit-font-smoothing: antialiased;
        }

        .sheet {
            max-width: 17cm;
            margin: 0 auto;
        }

        /* Barra de ações (não imprime) */
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 8px;
        }
        .no-print a, .no-print button {
            padding: 8px 20px;
            border: 1px solid #d1d5db;
            background: #fff;
            color: #374151;
            font-family: inherit;
            font-size: 10pt;
            cursor: pointer;
            text-decoration: none;
            border-radius: 6px;
            transition: all .15s;
        }
        .no-print a:hover { background: #f9fafb; }
        .no-print button {
            background: #111827;
            color: #fff;
            border-color: #111827;
        }
        .no-print button:hover { background: #1f2937; }

        /* ===== CABEÇALHO ===== */
        .head {
            text-align: center;
            padding-bottom: 14pt;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 14pt;
        }
        .head .orgao {
            font-size: 9pt;
            font-weight: 600;
            line-height: 1.4;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.5px;
        }
        .head .titulo {
            margin-top: 10pt;
            font-size: 14pt;
            font-weight: 700;
            color: #111827;
            letter-spacing: -0.2px;
        }
        .head .subtitulo {
            margin-top: 3pt;
            font-size: 9pt;
            color: #9ca3af;
        }

        /* ===== PROTOCOLO ===== */
        .protocolo {
            margin-bottom: 16pt;
            font-size: 9pt;
            color: #6b7280;
            display: flex;
            gap: 24pt;
        }
        .protocolo .linha {
            display: inline-block;
            min-width: 5cm;
            border-bottom: 1px solid #d1d5db;
            margin-left: 4px;
        }
        .protocolo .linha-curta { min-width: 2cm; }

        /* ===== SEÇÕES ===== */
        section.bloco {
            margin-bottom: 16pt;
            page-break-inside: avoid;
        }
        section.bloco h2 {
            font-size: 10pt;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #374151;
            padding-bottom: 4pt;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 8pt;
        }
        section.bloco h2 .num {
            display: inline-block;
            min-width: 16pt;
            color: #9ca3af;
            font-weight: 600;
        }

        /* ===== TABELAS DE DADOS ===== */
        table.dados {
            width: 100%;
            border-collapse: collapse;
        }
        table.dados tr {
            border-bottom: 1px solid #f3f4f6;
        }
        table.dados tr:last-child {
            border-bottom: none;
        }
        table.dados th {
            text-align: left;
            font-weight: 500;
            color: #6b7280;
            padding: 4pt 10pt 4pt 0;
            width: 5.5cm;
            vertical-align: top;
            font-size: 9.5pt;
        }
        table.dados td {
            padding: 4pt 0;
            color: #1f2937;
            vertical-align: top;
        }
        table.dados td.vazio {
            color: #9ca3af;
            font-style: italic;
        }

        /* ===== FATORES (chips) ===== */
        .fatores {
            display: flex;
            flex-wrap: wrap;
            gap: 6pt;
            list-style: none;
        }
        .fatores li {
            font-size: 9pt;
            padding: 2pt 8pt;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 3pt;
            color: #374151;
        }

        /* ===== RESULTADO DESTAQUE ===== */
        .resultado-destaque {
            font-size: 10.5pt;
            font-weight: 600;
            color: #1f2937;
            padding: 6pt 0;
        }

        /* ===== TEXTO LIVRE ===== */
        .texto-livre {
            text-align: justify;
            white-space: pre-wrap;
            line-height: 1.55;
            color: #374151;
        }

        /* ===== LISTAS ===== */
        ol.lista, ul.lista {
            padding-left: 1cm;
        }
        ol.lista li, ul.lista li {
            padding: 2pt 0;
            color: #374151;
        }

        /* ===== FOTOS ===== */
        .fotos {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8pt;
        }
        .fotos figure {
            page-break-inside: avoid;
        }
        .fotos img {
            width: 100%;
            height: 4cm;
            object-fit: cover;
            display: block;
            border: 1px solid #e5e7eb;
            border-radius: 4pt;
        }
        .fotos figcaption {
            margin-top: 3pt;
            font-size: 8pt;
            text-align: center;
            color: #9ca3af;
        }

        /* ===== ASSINATURA ===== */
        .assinaturas {
            margin-top: 32pt;
            display: flex;
            justify-content: center;
            page-break-inside: avoid;
        }
        .assinaturas .slot {
            text-align: center;
            min-width: 8.5cm;
        }
        .assinaturas .linha-ass {
            border-bottom: 1px solid #d1d5db;
            height: 1.8cm;
            margin-bottom: 4pt;
        }
        .assinaturas .papel {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 9pt;
            color: #374151;
            letter-spacing: 0.3px;
        }
        .assinaturas .ident {
            color: #6b7280;
            margin-top: 2pt;
            font-size: 9pt;
        }

        /* ===== VERIFICAÇÃO ===== */
        .verificacao {
            margin-top: 20pt;
            padding-top: 8pt;
            border-top: 1px solid #e5e7eb;
            font-size: 8pt;
            line-height: 1.5;
            color: #9ca3af;
        }
        .verificacao .codigo {
            font-family: 'SF Mono', 'Cascadia Code', 'Fira Code', 'Consolas', monospace;
            font-weight: 600;
            color: #6b7280;
            letter-spacing: 0.5px;
        }

        @media print {
            .no-print { display: none !important; }
            .sheet { max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <a href="{{ route('vistorias.show', $vistoria) }}">Voltar</a>
        <button x-on:click="window.print()">Imprimir / Salvar PDF</button>
    </div>

    <div class="sheet">

        {{-- ========== CABEÇALHO ========== --}}
        <header class="head">
            <div class="orgao">
                Prefeitura Municipal de Belo Horizonte<br>
                Subsecretaria de Fiscalização — SUFIS<br>
                Serviço de Zeladoria Urbana
            </div>
            <div class="titulo">
                Relatório de Zeladoria nº {{ $vistoria->id }}/{{ \Carbon\Carbon::parse($vistoria->data_abordagem ?? $vistoria->created_at)->format('Y') }}
            </div>
            <div class="subtitulo">
                Emitido em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}
            </div>
        </header>

        {{-- ========== PROTOCOLO ========== --}}
        <div class="protocolo">
            <span>Expediente nº <span class="linha"></span></span>
            <span>Folha nº <span class="linha linha-curta"></span></span>
        </div>

        {{-- ========== 1. IDENTIFICAÇÃO ========== --}}
        <section class="bloco">
            <h2><span class="num">{{ $secs['id'] }}.</span> Identificação</h2>
            <div class="body">
                <table class="dados">
                    <tr>
                        <th>Registro</th>
                        <td>{{ $vistoria->id }}</td>
                    </tr>
                    <tr>
                        @php
                            $dataAbordagem = $vistoria->data_abordagem ? \Carbon\Carbon::parse($vistoria->data_abordagem) : null;
                            $horaAbordagem = $dataAbordagem?->format('H:i');
                            $temHora = $horaAbordagem && $horaAbordagem !== '00:00';
                        @endphp
                        <th>{{ $temHora ? 'Data e hora da abordagem' : 'Data da abordagem' }}</th>
                        <td>{{ $dataAbordagem ? ($dataAbordagem->format('d/m/Y') . ($temHora ? ' às ' . $horaAbordagem : '')) : '—' }}</td>
                    </tr>
                    <tr>
                        <th>Tipo de abordagem</th>
                        <td>{{ $vistoria->tipoAbordagem->tipo ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Registrada por</th>
                        <td>{{ $vistoria->user->name ?? '—' }}{{ isset($vistoria->user->email) ? ' ('.$vistoria->user->email.')' : '' }}</td>
                    </tr>
                    <tr>
                        <th>Registro criado em</th>
                        <td>{{ $vistoria->created_at ? \Carbon\Carbon::parse($vistoria->created_at)->format('d/m/Y \à\s H:i') : '—' }}</td>
                    </tr>
                    @if($vistoria->updated_at && $vistoria->updated_at != $vistoria->created_at)
                    <tr>
                        <th>Última atualização</th>
                        <td>{{ \Carbon\Carbon::parse($vistoria->updated_at)->format('d/m/Y \à\s H:i') }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </section>

        {{-- ========== 2. LOCALIZAÇÃO ========== --}}
        <section class="bloco">
            <h2><span class="num">{{ $secs['loc'] }}.</span> Localização</h2>
            <div class="body">
                <table class="dados">
                    <tr>
                        <th>Endereço</th>
                        @if(filled($logradouro) || filled($numero))
                            <td>{{ trim($logradouro) }}{{ filled($numero) ? ', '.$numero : '' }}</td>
                        @else
                            <td class="vazio">não informado</td>
                        @endif
                    </tr>
                    <tr>
                        <th>Bairro</th>
                        <td>{{ $bairro ?? '—' }}</td>
                    </tr>
                    <tr>
                        <th>Regional</th>
                        <td>{{ $regional ?? '—' }}</td>
                    </tr>
                    @if($ponto?->lat && $ponto?->lng)
                    <tr>
                        <th>Coordenadas (lat, long)</th>
                        <td>{{ $ponto->lat }}, {{ $ponto->lng }}</td>
                    </tr>
                    @endif
                    <tr>
                        <th>Ponto de concentração</th>
                        <td>{{ $ponto?->id ? '#'.$ponto->id : '—' }}</td>
                    </tr>
                </table>
            </div>
        </section>

        {{-- ========== 3. RESULTADO ========== --}}
        @if($vistoria->resultadoAcao)
        <section class="bloco">
            <h2><span class="num">{{ $secs['res'] }}.</span> Resultado da ação</h2>
            <div class="body">
                <div class="resultado-destaque">{{ $vistoria->resultadoAcao->resultado }}</div>
            </div>
        </section>
        @endif

        {{-- ========== 4. CONTAGEM ========== --}}
        @if($temContagem)
        <section class="bloco">
            <h2><span class="num">{{ $secs['cnt'] }}.</span> Contagem e perfil quantitativo</h2>
            <div class="body">
                <table class="dados">
                    <tr>
                        <th>Pessoas abordadas</th>
                        <td>{{ $vistoria->quantidade_pessoas ?? 0 }}</td>
                    </tr>
                    <tr>
                        <th>Casais</th>
                        <td>{{ $vistoria->qtd_casais ?? 0 }}</td>
                    </tr>
                    <tr>
                        <th>Animais presentes</th>
                        <td>{{ $vistoria->animais ? 'Sim ('.($vistoria->qtd_animais ?? 0).')' : 'Não' }}</td>
                    </tr>
                    @if(filled($vistoria->movimento_migratorio))
                    <tr>
                        <th>Movimento migratório</th>
                        <td>{{ $vistoria->movimento_migratorio }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </section>
        @endif

        {{-- ========== 5. FATORES ========== --}}
        @if($fatoresAtivos->count() > 0)
        <section class="bloco">
            <h2><span class="num">{{ $secs['fat'] }}.</span> Fatores de complexidade observados</h2>
            <div class="body">
                <ul class="fatores">
                    @foreach($fatoresAtivos as $c)
                        <li>{{ $c['label'] }}@if(!empty($c['extra'])) {{ $c['extra'] }}@endif</li>
                    @endforeach
                </ul>
            </div>
        </section>
        @endif

        {{-- ========== 6. ABRIGOS ========== --}}
        @if($temAbrigos)
        <section class="bloco">
            <h2><span class="num">{{ $secs['abr'] }}.</span> Abrigos provisórios</h2>
            <div class="body">
                <table class="dados">
                    <tr>
                        <th>Quantidade</th>
                        <td>{{ $vistoria->qtd_abrigos_provisorios ?? 0 }}</td>
                    </tr>
                    @if(count($tiposAbrigoSelecionados) > 0)
                    <tr>
                        <th>Tipos observados</th>
                        <td>{{ implode(', ', $tiposAbrigoSelecionados) }}</td>
                    </tr>
                    @endif
                    @if($vistoria->tipoAbrigoDesmontado)
                    <tr>
                        <th>Tipo desmontado</th>
                        <td>{{ $vistoria->tipoAbrigoDesmontado->tipo_abrigo }}</td>
                    </tr>
                    @endif
                </table>
            </div>
        </section>
        @endif

        {{-- ========== 7. FISCALIZAÇÃO ========== --}}
        @if($temFiscalizacao || ($vistoria->qtd_kg ?? 0) > 0)
        <section class="bloco">
            <h2><span class="num">{{ $secs['fis'] }}.</span> Ações fiscalizatórias e materiais</h2>
            <div class="body">
                <table class="dados">
                    <tr>
                        <th>Condução por forças de segurança</th>
                        <td>{{ $vistoria->conducao_forcas_seguranca ? 'Sim' : 'Não' }}@if($vistoria->conducao_forcas_seguranca && filled($vistoria->conducao_forcas_observacao)) — {{ $vistoria->conducao_forcas_observacao }}@endif</td>
                    </tr>
                    <tr>
                        <th>Recolhimento de Inservíveis</th>
                        <td>{{ $vistoria->apreensao_fiscal ? 'Sim' : 'Não' }}</td>
                    </tr>
                    <tr>
                        <th>Auto de fiscalização</th>
                        <td>{{ $vistoria->auto_fiscalizacao_aplicado ? 'Aplicado' : 'Não aplicado' }}@if($vistoria->auto_fiscalizacao_aplicado && filled($vistoria->auto_fiscalizacao_numero)) — nº {{ $vistoria->auto_fiscalizacao_numero }}@endif</td>
                    </tr>
                    @if(filled($vistoria->material_apreendido))
                    <tr>
                        <th>Material apreendido</th>
                        <td>{{ $vistoria->material_apreendido }}</td>
                    </tr>
                    @endif
                    @if(filled($vistoria->material_descartado))
                    <tr>
                        <th>Material descartado</th>
                        <td>{{ $vistoria->material_descartado }}</td>
                    </tr>
                    @endif
                    @if(($vistoria->qtd_kg ?? 0) > 0)
                    <tr>
                        <th>Material recolhido</th>
                        <td>{{ $vistoria->qtd_kg }} kg</td>
                    </tr>
                    @endif
                </table>
            </div>
        </section>
        @endif

        {{-- ========== 8. ENCAMINHAMENTOS ========== --}}
        @if($encaminhamentos->count() > 0)
        <section class="bloco">
            <h2><span class="num">{{ $secs['enc'] }}.</span> Encaminhamentos realizados</h2>
            <div class="body">
                <ol class="lista">
                    @foreach($encaminhamentos as $enc)
                        <li>{{ $enc->encaminhamento }}</li>
                    @endforeach
                </ol>
            </div>
        </section>
        @endif

        {{-- ========== 9. PESSOAS ========== --}}
        @if(filled($vistoria->nomes_pessoas) || $vistoria->moradoresEntrada->count() > 0)
        <section class="bloco">
            <h2><span class="num">{{ $secs['pes'] }}.</span> Pessoas identificadas</h2>
            <div class="body">
                @if(filled($vistoria->nomes_pessoas))
                    <p style="margin-bottom: 6pt; font-weight: 600; color: #374151;">{{ $secs['pes'] }}.1 Nomes citados durante a abordagem</p>
                    <div class="texto-livre" style="margin-bottom: 12pt;">{{ $vistoria->nomes_pessoas }}</div>
                @endif

                @if($vistoria->moradoresEntrada->count() > 0)
                    <p style="margin-bottom: 6pt; font-weight: 600; color: #374151;">{{ $secs['pes'] }}.{{ filled($vistoria->nomes_pessoas) ? '2' : '1' }} Moradores cadastrados ({{ $vistoria->moradoresEntrada->count() }})</p>
                    <ul class="lista">
                        @foreach($vistoria->moradoresEntrada as $hist)
                            @if($hist->morador)
                                <li>{{ $hist->morador->nome_social }}@if(filled($hist->morador->apelido)) — "{{ $hist->morador->apelido }}"@endif</li>
                            @endif
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
        @endif

        {{-- ========== 10. OBSERVAÇÕES ========== --}}
        @if(filled($vistoria->observacao))
        <section class="bloco">
            <h2><span class="num">{{ $secs['obs'] }}.</span> Observações do agente</h2>
            <div class="body">
                <div class="texto-livre">{{ $vistoria->observacao }}</div>
            </div>
        </section>
        @endif

        {{-- ========== 11. FOTOS ========== --}}
        @if($fotos->count() > 0)
        <section class="bloco">
            <h2><span class="num">{{ $secs['fot'] }}.</span> Registro fotográfico ({{ $fotos->count() }})</h2>
            <div class="body">
                <div class="fotos">
                    @foreach($fotos as $i => $foto)
                        <figure>
                            <img src="{{ $foto->getUrl('preview') }}" alt="Foto {{ $i + 1 }}">
                            <figcaption>Foto {{ $i + 1 }} de {{ $fotos->count() }}@if($foto->getCustomProperty('legenda')) — {{ $foto->getCustomProperty('legenda') }}@endif</figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

        {{-- ========== ASSINATURA ========== --}}
        <div class="assinaturas">
            <div class="slot">
                <div class="linha-ass"></div>
                <div class="papel">Responsável</div>
                <div class="ident">{{ $vistoria->user->name ?? '—' }}</div>
            </div>
        </div>

        {{-- ========== VERIFICAÇÃO ========== --}}
        <div class="verificacao">
            Documento gerado eletronicamente pelo sistema POPRUA v2 em {{ now()->format('d/m/Y \à\s H:i') }}.<br>
            Código de verificação: <span class="codigo">{{ $verificationCode }}</span>
            &nbsp;·&nbsp; ID interno: {{ $vistoria->id }}
            &nbsp;·&nbsp; Geração: {{ now()->format('YmdHis') }}
        </div>

    </div>
</body>
</html>
