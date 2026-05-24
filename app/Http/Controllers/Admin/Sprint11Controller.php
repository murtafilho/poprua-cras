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
     */
    public function __invoke(): View
    {
        $fases = [
            [
                'codigo' => '1.1',
                'titulo' => 'Inclusao de Participantes da Vistoria',
                'descricao' => 'Registrar Supervisores, Coordenadores, GCM, SLU e Agentes de Campo presentes em cada vistoria.',
                'status' => 'implementado',
                'andamento' => 80,
                'esforco_restante' => 2,
                'notas' => 'Pivot vistoria_participantes + step 1 do wizard OK. Model MembroEquipe removido (tabela dropada). Participantes agora sao users com role.',
            ],
            [
                'codigo' => '1.2',
                'titulo' => 'Salvamento Parcial por Etapa',
                'descricao' => 'Botao "Salvar" em cada etapa ou autosave para evitar perda de dados durante o preenchimento.',
                'status' => 'pendente',
                'andamento' => 0,
                'esforco_restante' => 7,
                'notas' => 'Maior item pendente. Sugerido: tabela vistorias_rascunhos + endpoint PATCH + debounce no front + retomada ao reabrir create.',
            ],
            [
                'codigo' => '1.3',
                'titulo' => 'Data e Horario Previstos da Zeladoria',
                'descricao' => 'Campo data/periodo previstos quando tipo de abordagem for "Comunicacao de Zeladoria"; relatorios filtrados por data/periodo/supervisor/regional/endereco.',
                'status' => 'implementado',
                'andamento' => 85,
                'esforco_restante' => 2,
                'notas' => 'Campos `data_prevista_zeladoria`/`periodo_zeladoria` + rota `/vistorias/roteiro` OK. Falta: condicional UI (so para tipo Comunicacao) e export Excel.',
            ],
            [
                'codigo' => '1.4',
                'titulo' => 'Galeria de Fotos em Dispositivos Moveis',
                'descricao' => 'Botoes distintos "Tirar foto" (camera) e "Anexar arquivo" (galeria/explorer), com legenda descritiva por foto.',
                'status' => 'implementado',
                'andamento' => 100,
                'esforco_restante' => 0,
                'notas' => 'Inputs `camera-input-back` (capture=environment) e `gallery-input` (sem capture) existem em vistorias/create.blade.php. Legendas validadas em StoreVistoriaRequest e persistidas via Spatie MediaLibrary.',
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
                'andamento' => 75,
                'esforco_restante' => 1,
                'notas' => 'Filtros e rota `vistorias.roteiro` existem. Export hoje e HTML imprimivel (Ctrl+P). Para PDF nativo, instalar barryvdh/laravel-dompdf.',
            ],
            [
                'codigo' => '1.7',
                'titulo' => 'Ajustar Localizacao (Ponto) da Vistoria',
                'descricao' => 'Permitir editar o ponto/coordenadas apos o cadastro inicial.',
                'status' => 'implementado',
                'andamento' => 90,
                'esforco_restante' => 0.25,
                'notas' => 'Endpoint `PATCH /api/pontos/{id}/coordenadas` + fluxo no mapa.js OK. Falta apenas link "Ajustar localizacao" mais visivel em vistorias/show.blade.php.',
            ],
            [
                'codigo' => '1.8',
                'titulo' => 'Finalizacao de Vistoria',
                'descricao' => 'Botao "Finalizar Vistoria" que bloqueia edicoes; complementacao posterior so mediante justificativa.',
                'status' => 'implementado',
                'andamento' => 70,
                'esforco_restante' => 4,
                'notas' => 'Campos `finalizada/finalizada_em/finalizada_por` + VistoriaPolicy::update bloqueia se finalizada. Falta: fluxo de complementacao com justificativa + historico.',
            ],
            [
                'codigo' => '1.9',
                'titulo' => 'Lavacao + Tipo de Protocolo',
                'descricao' => 'Perguntas: houve lavacao (sim/nao) e tipo de protocolo (chuva/frio/normal) para subsidiar dados estatisticos.',
                'status' => 'implementado',
                'andamento' => 80,
                'esforco_restante' => 0.5,
                'notas' => '`houve_lavratura` + `tipo_protocolo` (chuva/frio/normal) ja existem. Atencao semantica: PDF pede "lavacao" (limpeza com agua), nao "lavratura" (auto formal). Criar campo distinto `houve_lavacao`.',
            ],
            [
                'codigo' => '1.10',
                'titulo' => 'Bug: horario "00:00" em vistorias',
                'descricao' => 'Sistema exibe horario 00:00 em vistorias mesmo quando preenchido.',
                'status' => 'corrigido',
                'andamento' => 100,
                'esforco_restante' => 0,
                'notas' => 'Corrigido nesta sprint. Causa: 41.664 de 42.435 vistorias (98%) sao dados legados pre-2026 sem hora; sistema novo salva hora corretamente. Mascarado "00:00" nas 4 views (show, index, minhas, report) — quando a hora e 00:00 a UI exibe apenas a data e o label muda de "Data/Hora" para "Data".',
            ],
            [
                'codigo' => '1.11',
                'titulo' => 'Bug: 404 ao abrir relatorio pelo mapa',
                'descricao' => 'Ao clicar em "Ver relatorio" no popup do mapa, retorna 404.',
                'status' => 'corrigido',
                'andamento' => 100,
                'esforco_restante' => 0,
                'notas' => 'Corrigido no commit 420e754. Causa: mapa.js usava URL absoluta /vistorias/X/relatorio que em prod (APP_URL com prefixo /ginfi/...) caia no Joomla. Fix: aplicar APP_BASE como nas outras chamadas.',
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
