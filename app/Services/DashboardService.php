<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Ponto;
use App\Models\ResultadoAcao;
use App\Models\Vistoria;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /** Tempo de cache em segundos (30 minutos) */
    private const int CACHE_TTL = 1800;

    /** Total de pontos cadastrados */
    public function totalPontos(): int
    {
        return Cache::remember(
            'dashboard:total_pontos',
            self::CACHE_TTL,
            fn () => Ponto::query()->count()
        );
    }

    /** Totais de vistorias e pontos vistoriados */
    public function totaisVistorias(): object
    {
        return Cache::remember(
            'dashboard:totais',
            self::CACHE_TTL,
            fn () => Vistoria::query()
                ->selectRaw('COUNT(*) as vistorias')
                ->selectRaw('COUNT(DISTINCT ponto_id) as pontos_vistoriados')
                ->toBase()
                ->first()
        );
    }

    /**
     * Dados mensais de evolucao dos pontos com status por resultado de acao.
     *
     * Usa raw SQL por ser uma query otimizada com CTEs cruzando multiplas tabelas.
     *
     * @return Collection<int|string, array{mes: mixed, total_existentes: int, total_pontos: int, persiste: int, impactado_parcial: int, deixou_ocorrer: int, ausente: int, nao_constatado: int, conformidade: int, sem_vistoria: int, extintos: int, ativos: int, total_efetivo: int}>
     */
    public function dadosMensais(): Collection
    {
        $rows = Cache::remember('dashboard:dados_mensais', self::CACHE_TTL, fn () => DB::select("
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

        return collect($rows)->map(fn ($row) => [
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
    }

    /**
     * Todos os resultados de acao ordenados por ID.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ResultadoAcao>
     */
    public function resultadosAcoes(): \Illuminate\Database\Eloquent\Collection
    {
        return ResultadoAcao::query()->orderBy('id')->get();
    }
}
