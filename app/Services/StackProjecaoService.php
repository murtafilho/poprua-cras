<?php

declare(strict_types=1);

namespace App\Services;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

class StackProjecaoService
{
    /** @return array<string, mixed> */
    public function dados(): array
    {
        $fotosLegado = Media::query()->count();

        return [
            'geradoEm' => now()->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            'totais' => [
                'fotografias' => $fotosLegado,
            ],
            'projecaoAnual' => $this->projecaoAnual($fotosLegado),
            'cenariosAno5' => $this->cenariosAnoCinco(),
        ];
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
}
