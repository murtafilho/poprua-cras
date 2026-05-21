<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $totalPontos = Cache::remember('dashboard:total_pontos', 1800, fn () => DB::table('pontos')->count());

        $totais = Cache::remember('dashboard:totais', 1800, fn () => DB::table('vistorias')
            ->selectRaw('COUNT(*) as vistorias')
            ->selectRaw('COUNT(DISTINCT ponto_id) as pontos_vistoriados')
            ->whereNull('deleted_at')
            ->first());

        $dadosMensais = Cache::remember('dashboard:dados_mensais', 1800, fn () => DB::select("
            WITH meses AS (
                SELECT generate_series(
                    date_trunc('month', MIN(data_abordagem)),
                    date_trunc('month', CURRENT_DATE),
                    '1 month'::interval
                )::date AS mes
                FROM vistorias
                WHERE deleted_at IS NULL AND data_abordagem >= '2017-01-01'
            ),
            ponto_primeiro AS (
                SELECT ponto_id, MIN(data_abordagem)::date AS primeira
                FROM vistorias
                WHERE deleted_at IS NULL AND ponto_id IS NOT NULL
                GROUP BY ponto_id
            ),
            ultima_vistoria_mes AS (
                SELECT DISTINCT ON (ponto_id, date_trunc('month', data_abordagem))
                    ponto_id,
                    date_trunc('month', data_abordagem)::date AS mes,
                    resultado_acao_id
                FROM vistorias
                WHERE deleted_at IS NULL AND ponto_id IS NOT NULL
                ORDER BY ponto_id, date_trunc('month', data_abordagem), data_abordagem DESC, id DESC
            ),
            status_no_mes AS (
                SELECT
                    m.mes,
                    pp.ponto_id,
                    lat.resultado_acao_id AS resultado
                FROM meses m
                CROSS JOIN ponto_primeiro pp
                LEFT JOIN LATERAL (
                    SELECT uvm.resultado_acao_id
                    FROM ultima_vistoria_mes uvm
                    WHERE uvm.ponto_id = pp.ponto_id AND uvm.mes <= m.mes
                    ORDER BY uvm.mes DESC
                    LIMIT 1
                ) lat ON true
                WHERE pp.primeira <= (m.mes + INTERVAL '1 month' - INTERVAL '1 day')::date
            )
            SELECT
                to_char(mes, 'YYYY-MM') AS mes,
                COUNT(*) AS total_existentes,
                COUNT(*) FILTER (WHERE resultado = 1) AS persiste,
                COUNT(*) FILTER (WHERE resultado = 2) AS impactado_parcial,
                COUNT(*) FILTER (WHERE resultado = 3) AS deixou_ocorrer,
                COUNT(*) FILTER (WHERE resultado = 4) AS ausente,
                COUNT(*) FILTER (WHERE resultado = 5) AS nao_constatado,
                COUNT(*) FILTER (WHERE resultado = 6) AS conformidade,
                COUNT(*) FILTER (WHERE resultado IS NULL) AS sem_vistoria,
                COUNT(*) FILTER (WHERE resultado IN (3, 5)) AS extintos,
                COUNT(*) FILTER (WHERE resultado IN (1, 2, 4, 6)) AS ativos,
                COUNT(*) - COUNT(*) FILTER (WHERE resultado IN (3, 5)) AS total_efetivo
            FROM status_no_mes
            GROUP BY mes
            ORDER BY mes
        "));

        $dadosMensais = collect($dadosMensais)->map(fn ($row) => [
            'mes' => $row->mes,
            'total_existentes' => (int) $row->total_existentes,
            'total_pontos' => (int) $row->total_existentes,
            'persiste' => (int) $row->persiste,
            'impactado_parcial' => (int) $row->impactado_parcial,
            'deixou_ocorrer' => (int) $row->deixou_ocorrer,
            'ausente' => (int) $row->ausente,
            'nao_constatado' => (int) $row->nao_constatado,
            'conformidade' => (int) $row->conformidade,
            'sem_vistoria' => (int) $row->sem_vistoria,
            'extintos' => (int) $row->extintos,
            'ativos' => (int) $row->ativos,
            'total_efetivo' => (int) $row->total_efetivo,
        ]);

        $resultados = DB::table('resultados_acoes')->orderBy('id')->get();

        return view('dashboard', [
            'dadosMensais' => $dadosMensais,
            'totais' => $totais,
            'totalPontos' => $totalPontos,
            'resultados' => $resultados,
        ]);
    }
}
