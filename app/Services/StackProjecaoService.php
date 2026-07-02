<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Morador;
use App\Models\Ponto;
use App\Models\Vistoria;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StackProjecaoService
{
    private const string ETL_VISTORIA_BULK_DATE = '2026-01-07';

    private const string ETL_PONTO_BULK_DATE = '2026-02-24';

    private const string POS_ETL_START = '2026-01-08';

    /** @return array<string, mixed> */
    public function dados(): array
    {
        $fotosLegado = Media::query()->count();
        $fotoStats = $this->estatisticasFotos();
        $vistoriasSemestre = $this->vistoriasPorSemestre();
        $pontosSemestre = $this->pontosPorSemestre();

        return [
            'geradoEm' => now()->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            'totais' => [
                'vistorias' => Vistoria::query()->count(),
                'pontos' => Ponto::query()->count(),
                'moradores' => Morador::query()->count(),
                'fotografias' => $fotosLegado,
                'vistoriasPosEtl' => Vistoria::query()
                    ->where('created_at', '>=', self::POS_ETL_START)
                    ->count(),
                'pontosOrganicosPosEtl' => Ponto::query()
                    ->where('created_at', '>=', '2026-03-01')
                    ->count(),
            ],
            'vistoriasSemestre' => $vistoriasSemestre,
            'vistoriasMensalPosEtl' => $this->vistoriasMensalPosEtl(),
            'pontosSemestre' => $pontosSemestre,
            'fotoStats' => $fotoStats,
            'fotoDistribuicao' => $this->distribuicaoFotosPorVistoria(),
            'fotosMes' => $this->fotosPorMes(),
            'projecaoAnual' => $this->projecaoAnual($fotosLegado),
            'cenariosAno5' => $this->cenariosAnoCinco(),
            'chartPayload' => $this->chartPayload(
                $vistoriasSemestre,
                $pontosSemestre,
                $fotoStats,
                $fotosLegado,
            ),
        ];
    }

    /** @return list<array{semestre: string, total: int, origem: string}> */
    private function vistoriasPorSemestre(): array
    {
        $rows = DB::select('
            SELECT
                EXTRACT(YEAR FROM created_at)::int AS ano,
                CASE WHEN EXTRACT(MONTH FROM created_at) <= 6 THEN 1 ELSE 2 END AS semestre,
                COUNT(*)::int AS total
            FROM vistorias
            WHERE deleted_at IS NULL
              AND created_at::date != ?
            GROUP BY 1, 2
            ORDER BY 1, 2
        ', [self::ETL_VISTORIA_BULK_DATE]);

        return array_map(function (object $row): array {
            $label = sprintf('%d-S%d', $row->ano, $row->semestre);
            $origem = ($row->ano === 2026 && $row->semestre === 1) ? 'sizem' : 'legado';

            return [
                'semestre' => $label,
                'total' => (int) $row->total,
                'origem' => $origem,
            ];
        }, $rows);
    }

    /** @return list<array{mes: string, total: int}> */
    private function vistoriasMensalPosEtl(): array
    {
        $rows = DB::select("
            SELECT TO_CHAR(created_at, 'YYYY-MM') AS mes, COUNT(*)::int AS total
            FROM vistorias
            WHERE deleted_at IS NULL
              AND created_at >= ?
            GROUP BY 1
            ORDER BY 1
        ", [self::POS_ETL_START]);

        return array_map(fn (object $row): array => [
            'mes' => $row->mes,
            'total' => (int) $row->total,
        ], $rows);
    }

    /** @return list<array{semestre: string, organico: int, etl: int}> */
    private function pontosPorSemestre(): array
    {
        $organicos = collect(DB::select('
            SELECT
                EXTRACT(YEAR FROM created_at)::int AS ano,
                CASE WHEN EXTRACT(MONTH FROM created_at) <= 6 THEN 1 ELSE 2 END AS semestre,
                COUNT(*)::int AS total
            FROM pontos
            WHERE deleted_at IS NULL
              AND created_at::date != ?
            GROUP BY 1, 2
            ORDER BY 1, 2
        ', [self::ETL_PONTO_BULK_DATE]))->keyBy(fn (object $row): string => sprintf('%d-S%d', $row->ano, $row->semestre));

        $etlPorSemestre = DB::selectOne('
            SELECT
                EXTRACT(YEAR FROM created_at)::int AS ano,
                CASE WHEN EXTRACT(MONTH FROM created_at) <= 6 THEN 1 ELSE 2 END AS semestre,
                COUNT(*)::int AS total
            FROM pontos
            WHERE deleted_at IS NULL
              AND created_at::date = ?
            GROUP BY 1, 2
        ', [self::ETL_PONTO_BULK_DATE]);

        $semestres = $organicos->keys()->merge(
            $etlPorSemestre ? [sprintf('%d-S%d', $etlPorSemestre->ano, $etlPorSemestre->semestre)] : []
        )->unique()->sort()->values();

        return $semestres->map(function (string $label) use ($organicos, $etlPorSemestre): array {
            $etl = 0;
            if ($etlPorSemestre && $label === sprintf('%d-S%d', $etlPorSemestre->ano, $etlPorSemestre->semestre)) {
                $etl = (int) $etlPorSemestre->total;
            }

            $organicoRow = $organicos->get($label);

            return [
                'semestre' => $label,
                'organico' => (int) ($organicoRow === null ? 0 : $organicoRow->total),
                'etl' => $etl,
            ];
        })->all();
    }

    /** @return array{media: float, comFoto: int, comDezOuMais: int, pctDezOuMais: float, morador: int} */
    private function estatisticasFotos(): array
    {
        $stats = DB::selectOne("
            SELECT
                COUNT(*)::int AS com_foto,
                COALESCE(ROUND(AVG(cnt)::numeric, 1), 0) AS media,
                COUNT(*) FILTER (WHERE cnt >= 10)::int AS com_dez_ou_mais
            FROM (
                SELECT model_id, COUNT(*) AS cnt
                FROM media
                WHERE model_type LIKE ?
                  AND collection_name = 'fotos'
                GROUP BY model_id
            ) sub
        ", ['%Vistoria%']);

        $moradorFotos = (int) DB::table('media')
            ->where('collection_name', 'fotos')
            ->where('model_type', 'like', '%Morador%')
            ->count();

        $comFoto = (int) ($stats->com_foto ?? 0);
        $comDez = (int) ($stats->com_dez_ou_mais ?? 0);

        return [
            'media' => (float) ($stats->media ?? 0),
            'comFoto' => $comFoto,
            'comDezOuMais' => $comDez,
            'pctDezOuMais' => $comFoto > 0 ? round($comDez / $comFoto * 100) : 0,
            'morador' => $moradorFotos,
        ];
    }

    /** @return list<array{faixa: string, total: int}> */
    private function distribuicaoFotosPorVistoria(): array
    {
        $rows = DB::select("
            SELECT cnt::int AS fotos, COUNT(*)::int AS vistorias
            FROM (
                SELECT model_id, COUNT(*) AS cnt
                FROM media
                WHERE model_type LIKE ?
                  AND collection_name = 'fotos'
                GROUP BY model_id
            ) sub
            GROUP BY cnt
            ORDER BY cnt
        ", ['%Vistoria%']);

        $buckets = [
            '1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0,
            '6' => 0, '7' => 0, '8' => 0, '9' => 0, '10' => 0,
            '11–15' => 0, '16+' => 0,
        ];

        foreach ($rows as $row) {
            $fotos = (int) $row->fotos;
            $key = match (true) {
                $fotos <= 10 => (string) $fotos,
                $fotos <= 15 => '11–15',
                default => '16+',
            };
            $buckets[$key] += (int) $row->vistorias;
        }

        return collect($buckets)->map(fn (int $total, string $faixa): array => [
            'faixa' => $faixa,
            'total' => $total,
        ])->values()->all();
    }

    /** @return list<array{mes: string, total: int}> */
    private function fotosPorMes(): array
    {
        $rows = DB::select("
            SELECT TO_CHAR(created_at, 'YYYY-MM') AS mes, COUNT(*)::int AS total
            FROM media
            GROUP BY 1
            ORDER BY 1
        ");

        return array_map(fn (object $row): array => [
            'mes' => $row->mes,
            'total' => (int) $row->total,
        ], $rows);
    }

    /**
     * @return list<array{ano: string, vistorias: int, fotosPorVistoria: int, fotosMoradores: int, fotosVistorias: int, totalAno: int, acumulado: int, midiaGb: float}>
     */
    private function projecaoAnual(int $fotosLegado): array
    {
        $anos = [
            ['ano' => 'Ano 1 (2026–27)', 'vistorias' => 4000, 'fpv' => 8, 'fm' => 800],
            ['ano' => 'Ano 2 (2027–28)', 'vistorias' => 7000, 'fpv' => 10, 'fm' => 2000],
            ['ano' => 'Ano 3 (2028–29)', 'vistorias' => 10000, 'fpv' => 12, 'fm' => 4000],
            ['ano' => 'Ano 4 (2029–30)', 'vistorias' => 12000, 'fpv' => 13, 'fm' => 6000],
            ['ano' => 'Ano 5 (2030–31)', 'vistorias' => 14000, 'fpv' => 13, 'fm' => 8000],
        ];

        $acumulado = $fotosLegado;
        $resultado = [];

        foreach ($anos as $ano) {
            $fotosVistorias = $ano['vistorias'] * $ano['fpv'];
            $totalAno = $fotosVistorias + $ano['fm'];
            $acumulado += $totalAno;

            $resultado[] = [
                'ano' => $ano['ano'],
                'vistorias' => $ano['vistorias'],
                'fotosPorVistoria' => $ano['fpv'],
                'fotosMoradores' => $ano['fm'],
                'fotosVistorias' => $fotosVistorias,
                'totalAno' => $totalAno,
                'acumulado' => $acumulado,
                'midiaGb' => round($acumulado * 430 / 1024 / 1024, 1),
            ];
        }

        return $resultado;
    }

    /** @return list<array{nome: string, vistorias: int, fotosPorVistoria: float, fotosMoradores: int, totalFotos: int, midiaGb: int}> */
    private function cenariosAnoCinco(): array
    {
        $cenarios = [
            ['nome' => 'Conservador', 'vistorias' => 8000, 'fotosPorVistoria' => 10, 'fotosMoradores' => 4000],
            ['nome' => 'Referência', 'vistorias' => 14000, 'fotosPorVistoria' => 12.5, 'fotosMoradores' => 8000],
            ['nome' => 'Intensivo', 'vistorias' => 18000, 'fotosPorVistoria' => 15, 'fotosMoradores' => 12000],
        ];

        return array_map(function (array $cenario): array {
            $totalFotos = (int) ($cenario['vistorias'] * $cenario['fotosPorVistoria'] + $cenario['fotosMoradores']);

            return [
                ...$cenario,
                'totalFotos' => $totalFotos,
                'midiaGb' => (int) round($totalFotos * 430 / 1024 / 1024),
            ];
        }, $cenarios);
    }

    /**
     * @param  list<array{semestre: string, total: int, origem: string}>  $vistoriasSemestre
     * @param  list<array{semestre: string, organico: int, etl: int}>  $pontosSemestre
     * @param  array{media: float, comFoto: int, comDezOuMais: int, pctDezOuMais: float, morador: int}  $fotoStats
     * @return array<string, mixed>
     */
    private function chartPayload(array $vistoriasSemestre, array $pontosSemestre, array $fotoStats, int $fotosLegado): array
    {
        $projecao = $this->projecaoAnual($fotosLegado);

        return [
            'vistoriasSemestre' => $vistoriasSemestre,
            'vistoriasMensalPosEtl' => $this->vistoriasMensalPosEtl(),
            'pontosSemestre' => $pontosSemestre,
            'fotoDistribuicao' => $this->distribuicaoFotosPorVistoria(),
            'fotosMes' => $this->fotosPorMes(),
            'projecao' => array_map(fn (array $row): array => [
                'label' => preg_replace('/^Ano \d+ /', '', $row['ano']) ?? $row['ano'],
                'fotosVistorias' => $row['fotosVistorias'],
                'fotosMoradores' => $row['fotosMoradores'],
            ], $projecao),
        ];
    }
}
