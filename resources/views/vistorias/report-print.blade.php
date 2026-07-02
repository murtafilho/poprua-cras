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

        $temEquipe = $vistoria->participantes->count() > 0;
        $participantesPorTipo = $temEquipe
            ? $vistoria->participantes->groupBy(fn ($u) => \App\Enums\TipoEquipe::fromUser($u)->value)
            : collect();

        // A4 / processo: somente fotos marcadas como públicas
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
        if ($temEquipe) $secs['eqp'] = $n++;
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
        /* ============================================================
           Layout A4 compacto — base ABNT NBR 14724 com densidade otimizada
           Arial 11pt · espaçamento 1,2 · margens 2cm
           ============================================================ */
        @page {
            size: A4;
            margin: 2cm;

            @bottom-left {
                content: "Relatório de Zeladoria nº {{ $vistoria->id }}";
                font-family: Arial, Helvetica, sans-serif;
                font-size: 9pt;
                color: #555;
            }
            @bottom-right {
                content: counter(page) " de " counter(pages);
                font-family: Arial, Helvetica, sans-serif;
                font-size: 9pt;
                color: #555;
            }
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        html, body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.25;
            color: #000;
            background: #fff;
        }

        .sheet {
            max-width: 17cm; /* área útil = 21cm - 2cm - 2cm */
            margin: 0 auto;
        }

        /* Action bar (não imprime) */
        .no-print {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            display: flex;
            gap: 8px;
        }
        .no-print a, .no-print button {
            padding: 8px 16px;
            border: 1px solid #999;
            background: #fff;
            color: #000;
            font-family: Arial, sans-serif;
            font-size: 11pt;
            cursor: pointer;
            text-decoration: none;
            border-radius: 4px;
        }
        .no-print button { background: #000; color: #fff; border-color: #000; }

        /* ===== CABEÇALHO ===== */
        .head {
            text-align: center;
            margin-bottom: 12pt;
        }
        .head .orgao {
            font-size: 10pt;
            font-weight: bold;
            line-height: 1.3;
            text-transform: uppercase;
        }
        .head .divider {
            margin-top: 8pt;
            margin-bottom: 8pt;
            height: 1px;
            background: #000;
        }
        .head .titulo {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.2;
        }
        .head .subtitulo {
            margin-top: 3pt;
            font-size: 10pt;
            color: #333;
        }

        /* ===== PROTOCOLO ===== */
        .protocolo {
            margin-top: 12pt;
            margin-bottom: 14pt;
            font-size: 10pt;
            display: flex;
            gap: 24pt;
        }
        .protocolo .linha {
            display: inline-block;
            min-width: 5cm;
            border-bottom: 1px solid #000;
            margin-left: 6px;
        }
        .protocolo .linha-curta { min-width: 2cm; }

        /* ===== SEÇÕES PRIMÁRIAS ===== */
        section.bloco {
            margin-top: 12pt;
            page-break-inside: avoid;
        }
        section.bloco h2 {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 6pt;
            line-height: 1.3;
        }
        section.bloco h2 .num {
            display: inline-block;
            min-width: 16pt;
        }
        section.bloco .body {
            padding-left: 0;
        }

        /* ===== CAMPOS LABEL/VALOR ===== */
        dl.campos {
            display: grid;
            grid-template-columns: 6cm 1fr;
            row-gap: 2pt;
            column-gap: 10pt;
        }
        dl.campos dt {
            font-weight: bold;
        }
        dl.campos dt::after { content: ":"; }
        dl.campos dd {
            margin-left: 0;
        }
        dl.campos dd.vazio {
            color: #666;
            font-style: italic;
        }

        /* ===== FATORES (lista em 3 colunas compactas) ===== */
        ul.fatores {
            list-style: none;
            columns: 3;
            column-gap: 16pt;
        }
        ul.fatores li {
            padding: 1pt 0;
            font-size: 10.5pt;
            break-inside: avoid;
            -webkit-column-break-inside: avoid;
        }
        ul.fatores li::before {
            content: "☑ ";
            font-weight: bold;
        }

        /* ===== TEXTO LIVRE ===== */
        .texto-livre {
            text-indent: 0.8cm;
            text-align: justify;
            white-space: pre-wrap;
            line-height: 1.3;
        }

        /* ===== LISTAS ===== */
        ol.lista, ul.lista {
            padding-left: 1cm;
        }
        ol.lista li, ul.lista li {
            padding: 0;
        }

        /* ===== FOTOS (4 colunas, mais baixas) ===== */
        .fotos {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6pt;
        }
        .fotos figure {
            page-break-inside: avoid;
        }
        .fotos img {
            width: 100%;
            height: 3.4cm;
            object-fit: cover;
            display: block;
            border: 1px solid #ccc;
        }
        .fotos figcaption {
            margin-top: 2pt;
            font-size: 9pt;
            text-align: center;
            color: #333;
        }

        /* ===== ASSINATURA ===== */
        .assinaturas {
            margin-top: 28pt;
            display: flex;
            justify-content: center;
            page-break-inside: avoid;
        }
        .assinaturas .slot {
            text-align: center;
            font-size: 10pt;
            min-width: 8.5cm;
        }
        .assinaturas .linha-ass {
            border-bottom: 1px solid #000;
            height: 1.8cm;
            margin-bottom: 3pt;
        }
        .assinaturas .papel {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9.5pt;
        }
        .assinaturas .ident {
            color: #333;
            margin-top: 1pt;
            font-size: 9.5pt;
        }

        /* ===== VERIFICAÇÃO ===== */
        .verificacao {
            margin-top: 18pt;
            padding-top: 6pt;
            border-top: 1px solid #000;
            font-size: 9pt;
            line-height: 1.3;
        }
        .verificacao .codigo {
            font-family: 'Courier New', Courier, monospace;
            font-weight: bold;
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
        <button onclick="window.print()">Imprimir / Salvar PDF</button>
    </div>

    <div class="sheet">

        {{-- ========== CABEÇALHO ========== --}}
        <header class="head">
            <div class="orgao">
                Prefeitura Municipal de Belo Horizonte<br>
                Subsecretaria de Fiscalização — SUFIS<br>
                Serviço de Zeladoria Urbana
            </div>
            <div class="divider"></div>
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
                <dl class="campos">
                    <dt>Registro</dt>
                    <dd>{{ $vistoria->id }}</dd>

                    <dt>Data e hora da abordagem</dt>
                    <dd>{{ $vistoria->data_abordagem ? \Carbon\Carbon::parse($vistoria->data_abordagem)->format('d/m/Y') . ' às ' . \Carbon\Carbon::parse($vistoria->data_abordagem)->format('H:i') : '—' }}</dd>

                    <dt>Tipo de abordagem</dt>
                    <dd>{{ $vistoria->tipoAbordagem->tipo ?? '—' }}</dd>

                    <dt>Registrada por</dt>
                    <dd>{{ $vistoria->user->name ?? '—' }}{{ isset($vistoria->user->email) ? ' ('.$vistoria->user->email.')' : '' }}</dd>

                    <dt>Registro criado em</dt>
                    <dd>{{ $vistoria->created_at ? \Carbon\Carbon::parse($vistoria->created_at)->format('d/m/Y \à\s H:i') : '—' }}</dd>

                    @if($vistoria->updated_at && $vistoria->updated_at != $vistoria->created_at)
                        <dt>Última atualização</dt>
                        <dd>{{ \Carbon\Carbon::parse($vistoria->updated_at)->format('d/m/Y \à\s H:i') }}</dd>
                    @endif
                </dl>
            </div>
        </section>

        {{-- ========== 2. LOCALIZAÇÃO ========== --}}
        <section class="bloco">
            <h2><span class="num">{{ $secs['loc'] }}.</span> Localização</h2>
            <div class="body">
                <dl class="campos">
                    <dt>Endereço</dt>
                    @if(filled($logradouro) || filled($numero))
                        <dd>{{ trim($logradouro) }}{{ filled($numero) ? ', '.$numero : '' }}</dd>
                    @else
                        <dd class="vazio">não informado</dd>
                    @endif

                    <dt>Bairro</dt>
                    <dd>{{ $bairro ?? '—' }}</dd>

                    <dt>Regional</dt>
                    <dd>{{ $regional ?? '—' }}</dd>

                    @if($ponto?->lat && $ponto?->lng)
                        <dt>Coordenadas (lat, long)</dt>
                        <dd>{{ $ponto->lat }}, {{ $ponto->lng }}</dd>
                    @endif

                    <dt>Ponto de concentração</dt>
                    <dd>{{ $ponto?->id ? '#'.$ponto->id : '—' }}</dd>
                </dl>
            </div>
        </section>

        {{-- ========== EQUIPE PARTICIPANTES ========== --}}
        @if($temEquipe)
        <section class="bloco">
            <h2><span class="num">{{ $secs['eqp'] }}.</span> Equipe participantes</h2>
            <div class="body">
                <dl class="campos">
                    @foreach (\App\Enums\TipoEquipe::ordenados() as $tipo)
                        @if ($participantesPorTipo->has($tipo->value))
                            <dt>{{ $tipo->label() }}</dt>
                            <dd>{{ $participantesPorTipo[$tipo->value]->map(fn ($p) => $p->name ?? ('#'.$p->id))->join(', ') }}</dd>
                        @endif
                    @endforeach
                </dl>
            </div>
        </section>
        @endif

        {{-- ========== 3. RESULTADO ========== --}}
        @if($vistoria->resultadoAcao)
        <section class="bloco">
            <h2><span class="num">{{ $secs['res'] }}.</span> Resultado da ação</h2>
            <div class="body">
                {{ $vistoria->resultadoAcao->resultado }}
            </div>
        </section>
        @endif

        {{-- ========== 4. CONTAGEM ========== --}}
        @if($temContagem)
        <section class="bloco">
            <h2><span class="num">{{ $secs['cnt'] }}.</span> Contagem e perfil quantitativo</h2>
            <div class="body">
                <dl class="campos">
                    <dt>Pessoas abordadas</dt>
                    <dd>{{ $vistoria->quantidade_pessoas ?? 0 }}</dd>

                    <dt>Casais</dt>
                    <dd>{{ $vistoria->qtd_casais ?? 0 }}</dd>

                    <dt>Animais presentes</dt>
                    <dd>{{ $vistoria->animais ? 'Sim ('.($vistoria->qtd_animais ?? 0).')' : 'Não' }}</dd>

                    @if(filled($vistoria->movimento_migratorio))
                        <dt>Movimento migratório</dt>
                        <dd>{{ $vistoria->movimento_migratorio }}</dd>
                    @endif
                </dl>
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
                <dl class="campos">
                    <dt>Quantidade</dt>
                    <dd>{{ $vistoria->qtd_abrigos_provisorios ?? 0 }}</dd>

                    @if(count($tiposAbrigoSelecionados) > 0)
                        <dt>Tipos observados</dt>
                        <dd>{{ implode(', ', $tiposAbrigoSelecionados) }}</dd>
                    @endif

                    @if($vistoria->tipoAbrigoDesmontado)
                        <dt>Tipo desmontado</dt>
                        <dd>{{ $vistoria->tipoAbrigoDesmontado->tipo_abrigo }}</dd>
                    @endif
                </dl>
            </div>
        </section>
        @endif

        {{-- ========== 7. FISCALIZAÇÃO ========== --}}
        @if($temFiscalizacao || ($vistoria->qtd_kg ?? 0) > 0)
        <section class="bloco">
            <h2><span class="num">{{ $secs['fis'] }}.</span> Ações fiscalizatórias e materiais</h2>
            <div class="body">
                <dl class="campos">
                    <dt>Condução por forças de segurança</dt>
                    <dd>
                        {{ $vistoria->conducao_forcas_seguranca ? 'Sim' : 'Não' }}@if($vistoria->conducao_forcas_seguranca && filled($vistoria->conducao_forcas_observacao)) — {{ $vistoria->conducao_forcas_observacao }}@endif
                    </dd>

                    <dt>Apreensão fiscal</dt>
                    <dd>{{ $vistoria->apreensao_fiscal ? 'Sim' : 'Não' }}</dd>

                    <dt>Auto de fiscalização</dt>
                    <dd>
                        {{ $vistoria->auto_fiscalizacao_aplicado ? 'Aplicado' : 'Não aplicado' }}@if($vistoria->auto_fiscalizacao_aplicado && filled($vistoria->auto_fiscalizacao_numero)) — nº {{ $vistoria->auto_fiscalizacao_numero }}@endif
                    </dd>

                    @if(filled($vistoria->material_apreendido))
                        <dt>Material apreendido</dt>
                        <dd>{{ $vistoria->material_apreendido }}</dd>
                    @endif

                    @if(filled($vistoria->material_descartado))
                        <dt>Material descartado</dt>
                        <dd>{{ $vistoria->material_descartado }}</dd>
                    @endif

                    @if(($vistoria->qtd_kg ?? 0) > 0)
                        <dt>Material recolhido</dt>
                        <dd>{{ $vistoria->qtd_kg }} kg</dd>
                    @endif
                </dl>
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
                    <p style="margin-bottom: 6pt;"><strong>{{ $secs['pes'] }}.1 Nomes citados durante a abordagem</strong></p>
                    <div class="texto-livre" style="margin-bottom: 12pt;">{{ $vistoria->nomes_pessoas }}</div>
                @endif

                @if($vistoria->moradoresEntrada->count() > 0)
                    <p style="margin-bottom: 6pt;"><strong>{{ $secs['pes'] }}.{{ filled($vistoria->nomes_pessoas) ? '2' : '1' }} Moradores cadastrados ({{ $vistoria->moradoresEntrada->count() }})</strong></p>
                    <ul class="lista">
                        @foreach($vistoria->moradoresEntrada as $hist)
                            @if($hist->morador)
                                <li>{{ $hist->morador->nome_social }}@if(filled($hist->morador->apelido)) — “{{ $hist->morador->apelido }}”@endif</li>
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
                            <figcaption>Foto {{ $i + 1 }} de {{ $fotos->count() }}</figcaption>
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
