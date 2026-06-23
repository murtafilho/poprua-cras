<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class Sprint11Controller extends Controller
{
    /**
     * Roadmap da Sprint 11 — Levantamento de alteracoes do sistema de Zeladoria
     * (GFAES/PBH, 23/03/2026). Fonte: docs/AUDITORIA_Zeladoria.md.
     *
     * Cada fase tem: codigo (1.x), titulo, descricao curta, status,
     * andamento (0-100), esforco restante em horas, e notas.
     *
     * Re-auditado contra o codigo em 2026-06-23 (status/andamento/notas
     * confrontados com a implementacao real, nao com o auto-relato anterior).
     */
    public function __invoke(): View
    {
        $fases = [
            [
                'codigo' => '1.1',
                'titulo' => 'Inclusao de Participantes da Vistoria',
                'descricao' => 'Registrar Supervisores, Coordenadores, GCM, SLU e Agentes de Campo presentes em cada vistoria.',
                'status' => 'implementado',
                'andamento' => 90,
                'esforco_restante' => 3,
                'notas' => 'Pivot vistoria_participantes refatorado p/ users (MembroEquipe/membros_equipe dropados). Sync no store E update + validacao por permissao "participar de equipes vistoria". Falta: agrupar/rotular participantes por categoria (Supervisor/GCM/SLU/Agente) na UI — hoje lista flat.',
            ],
            [
                'codigo' => '1.2',
                'titulo' => 'Salvamento Parcial por Etapa',
                'descricao' => 'Botao "Salvar" em cada etapa ou autosave para evitar perda de dados durante o preenchimento.',
                'status' => 'parcial',
                'andamento' => 60,
                'esforco_restante' => 8,
                'notas' => 'Autosave CLIENT-SIDE ja existe (vistoria-form.js: localStorage, debounce 1-2s, retomada por aba, expira 24h). Falta versao server-side (tabela vistorias_rascunhos + PATCH) p/ retomada cross-device; fotos nao entram no rascunho.',
            ],
            [
                'codigo' => '1.3',
                'titulo' => 'Data e Horario Previstos da Zeladoria',
                'descricao' => 'Campo data/periodo previstos quando tipo de abordagem for "Comunicacao de Zeladoria"; relatorios filtrados por data/periodo/supervisor/regional/endereco.',
                'status' => 'parcial',
                'andamento' => 75,
                'esforco_restante' => 3,
                'notas' => 'Campos `data_prevista_zeladoria`/`periodo_zeladoria` + rota `/vistorias/roteiro` OK. ATENCAO: a condicional "so p/ tipo Comunicacao" e codigo morto (toggleZeladoriaCampos aponta p/ #zeladoria-campos inexistente); hoje os campos seguem o toggle houve_comunicado. Falta tambem export Excel.',
            ],
            [
                'codigo' => '1.4',
                'titulo' => 'Galeria de Fotos em Dispositivos Moveis',
                'descricao' => 'Botoes distintos "Tirar foto" (camera) e "Anexar arquivo" (galeria/explorer), com legenda descritiva por foto.',
                'status' => 'parcial',
                'andamento' => 90,
                'esforco_restante' => 1,
                'notas' => 'Inputs `camera-input-back` (capture) e `gallery-input` + legendas_fotos validadas em Store E UpdateVistoriaRequest. ASSIMETRIA: store() persiste a legenda (withCustomProperties), mas update() sobe as fotos novas SEM aplicar legenda. Endpoint PATCH .../legenda existe p/ edicao avulsa. Falta alinhar update() ao store().',
            ],
            [
                'codigo' => '1.5',
                'titulo' => 'Filtro de Busca por Supervisor',
                'descricao' => 'Adicionar campo "Supervisor" na busca avancada de vistorias.',
                'status' => 'implementado',
                'andamento' => 100,
                'esforco_restante' => 0,
                'notas' => 'Filtro `supervisor` aceito pelo FormRequest; left join com `users` no VistoriaController; UI presente em vistorias/index.blade.php.',
            ],
            [
                'codigo' => '1.6',
                'titulo' => 'Filtro por Data Prevista + Export PDF',
                'descricao' => 'Filtros data_prevista_inicio/fim + exportacao em PDF do roteiro de vistorias.',
                'status' => 'implementado',
                'andamento' => 95,
                'esforco_restante' => 0.25,
                'notas' => 'PDF NATIVO ja implementado: exportarRoteiro com Barryvdh\DomPDF (format=pdf, A4 landscape); pacote barryvdh/laravel-dompdf ja consta em composer.json/lock. Ha tambem HTML imprimivel. Resta so garantir composer install no ambiente.',
            ],
            [
                'codigo' => '1.7',
                'titulo' => 'Ajustar Localizacao (Ponto) da Vistoria',
                'descricao' => 'Permitir editar o ponto/coordenadas apos o cadastro inicial.',
                'status' => 'implementado',
                'andamento' => 95,
                'esforco_restante' => 0.5,
                'notas' => 'Endpoint `PATCH /api/pontos/{id}/coordenadas` + fluxo ajustarMode no mapa.js OK; botao "Ajustar localizacao" (ajustar=1) em pontos/show e pontos/index. Falta so o atalho direto em vistorias/show (hoje o ajuste e alcancado via tela do Ponto).',
            ],
            [
                'codigo' => '1.8',
                'titulo' => 'Finalizacao de Vistoria',
                'descricao' => 'Botao "Finalizar Vistoria" que bloqueia edicoes; complementacao posterior so mediante justificativa.',
                'status' => 'implementado',
                'andamento' => 90,
                'esforco_restante' => 3,
                'notas' => 'Campos `finalizada/finalizada_em/finalizada_por` + botao Finalizar + complementar() exige justificativa (min 10). Bloqueio CORRIGIDO: VistoriaPolicy::update agora checa finalizada/cancelada ANTES do owner, entao o dono nao edita mais vistoria finalizada (complementacao so via complementar()). Falta so historico estruturado (hoje a justificativa vai p/ texto livre em observacao).',
            ],
            [
                'codigo' => '1.9',
                'titulo' => 'Lavacao + Tipo de Protocolo',
                'descricao' => 'Perguntas: houve lavacao (sim/nao) e tipo de protocolo (chuva/frio/normal) para subsidiar dados estatisticos.',
                'status' => 'parcial',
                'andamento' => 50,
                'esforco_restante' => 3.5,
                'notas' => '`houve_lavratura` + `tipo_protocolo` (chuva/frio/normal) implementados. PENDENTE o requisito real de "lavacao" (limpeza com agua): `houve_lavacao` NAO existe — criar campo distinto (migration/model/request/UI), pois lavratura (auto formal) != lavacao.',
            ],
            [
                'codigo' => '1.10',
                'titulo' => 'Bug: horario "00:00" em vistorias',
                'descricao' => 'Sistema exibe horario 00:00 em vistorias mesmo quando preenchido.',
                'status' => 'corrigido',
                'andamento' => 100,
                'esforco_restante' => 0,
                'notas' => 'Mascara "00:00" agora nas 4 superficies: vistorias/show, index (e minhas-vistorias, que reusa index), report e report-print. Quando a hora e 00:00 exibe so a data (label "Data"). (98% sao legados pre-2026 sem hora.)',
            ],
            [
                'codigo' => '1.11',
                'titulo' => 'Bug: 404 ao abrir relatorio pelo mapa',
                'descricao' => 'Ao clicar em "Ver relatorio" no popup do mapa, retorna 404.',
                'status' => 'corrigido',
                'andamento' => 100,
                'esforco_restante' => 0,
                'notas' => 'Corrigido: mapa.js usa APP_BASE em todas as URLs, incl. abrirRelatorio (iframe.src=${APP_BASE}/vistorias/X/relatorio). Reconferido apos a refatoracao que removeu checkCrosshairOverPoint — abrirRelatorio integro.',
            ],
        ];

        $totalEsforco = (float) array_sum(array_column($fases, 'esforco_restante'));
        $totalFases = count($fases);
        $totalAndamento = (int) round(array_sum(array_column($fases, 'andamento')) / $totalFases);

        $statusCount = [
            'implementado' => 0,
            'corrigido' => 0,
            'parcial' => 0,
            'pendente' => 0,
        ];
        foreach ($fases as $f) {
            $statusCount[$f['status']]++;
        }

        return view('admin.sprint11.index', [
            'fases' => $fases,
            'totalEsforco' => $totalEsforco,
            'totalFases' => $totalFases,
            'totalAndamento' => $totalAndamento,
            'statusCount' => $statusCount,
            'dataLevantamento' => '23/03/2026',
            'emissor' => 'Aldo Alves — GFAES',
        ]);
    }
}
