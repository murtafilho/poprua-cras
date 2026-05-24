<?php

namespace App\Console\Commands;

use App\Services\EnderecoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TransporCoordenadasPontos extends Command
{
    protected $signature = 'pontos:vincular-enderecos
                            {--dry-run : Simula sem gravar alterações}
                            {--raio=200 : Raio máximo de busca em metros}
                            {--force : Reprocessa todos, mesmo os já vinculados}';

    protected $description = 'Vincula pontos ao endereço de porta mais próximo. Prioridade: coordenadas. Fallback: endereço alfanumérico do legado MySQL.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $raio = (float) $this->option('raio');
        $force = $this->option('force');

        if ($dryRun) {
            $this->warn('Modo simulação — nenhuma alteração será gravada');
        }

        $this->info("Raio máximo de busca: {$raio}m");
        $this->newLine();

        // Buscar pontos a processar
        $query = DB::table('pontos');
        if (! $force) {
            $query->whereNull('endereco_atualizado_id');
        }
        $pontos = $query->get();

        $this->info("Pontos a processar: {$pontos->count()}");

        $bar = $this->output->createProgressBar($pontos->count());
        $bar->start();

        $porCoordenada = 0;
        $porAlfanumerico = 0;
        $foraDoRaio = 0;
        $semEndereco = 0;
        $erros = 0;

        foreach ($pontos as $ponto) {
            try {
                $resultado = $this->vincularPorCoordenada($ponto, $raio);

                if ($resultado) {
                    if (! $dryRun) {
                        $this->salvarVinculo($ponto->id, $resultado);
                    }
                    $porCoordenada++;
                    $bar->advance();

                    continue;
                }

                // Coordenada não encontrou — verificar se está fora do raio
                if ($this->temCoordenadas($ponto)) {
                    $foraDoRaio++;
                }

                // Fallback: busca alfanumérica pelo endereço do MySQL legado
                $resultado = $this->vincularPorAlfanumerico($ponto);

                if ($resultado) {
                    if (! $dryRun) {
                        $this->salvarVinculo($ponto->id, $resultado);
                    }
                    $porAlfanumerico++;
                } else {
                    $semEndereco++;
                }
            } catch (\Exception $e) {
                $this->error("\nErro no ponto {$ponto->id}: {$e->getMessage()}");
                $erros++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Concluído!');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total processados', $pontos->count()],
                ['Vinculados por coordenada', $porCoordenada],
                ['Vinculados por alfanumérico (fallback)', $porAlfanumerico],
                ['Fora do raio ('.$raio.'m)', $foraDoRaio],
                ['Sem endereço encontrado', $semEndereco],
                ['Erros', $erros],
            ]
        );

        if ($dryRun) {
            $this->warn('Execute sem --dry-run para aplicar as alterações');
        }

        return Command::SUCCESS;
    }

    /**
     * Tenta vincular por proximidade geográfica (coordenadas).
     *
     * @return array<string, mixed>|null
     */
    private function vincularPorCoordenada(object $ponto, float $raio): ?array
    {
        if (! $this->temCoordenadas($ponto)) {
            return null;
        }

        $margemGraus = ($raio * 1.5) / 111000;

        $endereco = DB::table('endereco_atualizados')
            ->select([
                'id',
                DB::raw('"SIGLA_TIPO_LOGRADOURO" as tipo'),
                DB::raw('"NOME_LOGRADOURO" as logradouro'),
                DB::raw('"NUMERO_IMOVEL" as numero'),
                DB::raw('"NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('"NOME_REGIONAL" as regional'),
                'lat',
                'lng',
            ])
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereBetween('lat', [$ponto->lat - $margemGraus, $ponto->lat + $margemGraus])
            ->whereBetween('lng', [$ponto->lng - $margemGraus, $ponto->lng + $margemGraus])
            ->orderByRaw('POWER(lat - ?, 2) + POWER(lng - ?, 2)', [$ponto->lat, $ponto->lng])
            ->first();

        if (! $endereco) {
            return null;
        }

        $distancia = $this->calcularDistancia(
            $ponto->lat, $ponto->lng,
            $endereco->lat, $endereco->lng
        );

        if ($distancia > $raio) {
            return null;
        }

        $complemento = sprintf(
            '%dm de %s %s, %s',
            round($distancia),
            $endereco->tipo,
            $endereco->logradouro,
            round($endereco->numero)
        );

        return [
            'endereco_atualizado_id' => $endereco->id,
            'complemento' => $complemento,
            'observacao' => sprintf('Vinculado por coordenada. Distância: %dm do endereço de porta mais próximo.', round($distancia)),
            'metodo' => 'coordenada',
        ];
    }

    /**
     * Fallback: tenta vincular pelo endereço alfanumérico do MySQL legado.
     *
     * @return array<string, mixed>|null
     */
    private function vincularPorAlfanumerico(object $ponto): ?array
    {
        // Buscar endereço legado no MySQL
        $enderecoLegado = $this->buscarEnderecoLegado($ponto);

        if (! $enderecoLegado) {
            return null;
        }

        $enderecoService = app(EnderecoService::class);
        $resultado = $enderecoService->geocodificarEndereco(
            $enderecoLegado->logradouro,
            $ponto->numero ?: (string) $enderecoLegado->numero,
            $enderecoLegado->bairro
        );

        if (! $resultado) {
            return null;
        }

        $obs = sprintf(
            'Vinculado por alfanumérico (fallback). Legado: %s %s, %s - %s. Encontrado: %s %s, %s - %s.',
            $enderecoLegado->tipo ?? '',
            $enderecoLegado->logradouro,
            $ponto->numero ?: 's/n',
            $enderecoLegado->bairro ?? '',
            $resultado->tipo,
            $resultado->logradouro,
            round($resultado->numero),
            $resultado->bairro
        );

        // Se o ponto não tem coordenadas, copiar do endereço encontrado
        $atualizarCoordenadas = ! $this->temCoordenadas($ponto) && $resultado->lat && $resultado->lng;

        return [
            'endereco_atualizado_id' => $resultado->id,
            'complemento' => sprintf(
                '%s %s, %s - %s',
                $resultado->tipo,
                $resultado->logradouro,
                round($resultado->numero),
                $resultado->bairro
            ),
            'observacao' => $obs,
            'metodo' => 'alfanumerico',
            'lat' => $atualizarCoordenadas ? $resultado->lat : null,
            'lng' => $atualizarCoordenadas ? $resultado->lng : null,
        ];
    }

    /**
     * Busca o endereço legado do ponto no MySQL.
     */
    private function buscarEnderecoLegado(object $ponto): ?object
    {
        try {
            return DB::connection('mysql')->table('pontos as p')
                ->join('ender as e', 'e.id', '=', 'p.endereco_id')
                ->where('p.id', $ponto->id)
                ->select([
                    'e.tipo',
                    'e.logradouro',
                    'e.bairro',
                    'e.regional',
                    DB::raw("IFNULL(p.numero, '') as numero"),
                ])
                ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Salva o vínculo no PostgreSQL.
     *
     * @param  array<string, mixed>  $resultado
     */
    private function salvarVinculo(int $pontoId, array $resultado): void
    {
        $update = [
            'endereco_atualizado_id' => $resultado['endereco_atualizado_id'],
            'complemento' => DB::raw(
                "COALESCE(NULLIF(complemento, ''), ".
                DB::connection()->getPdo()->quote($resultado['complemento']).')'
            ),
            'observacao' => $resultado['observacao'],
            'updated_at' => now(),
        ];

        // Se veio do fallback alfanumérico e o ponto não tinha coordenadas
        if (! empty($resultado['lat'])) {
            $update['lat'] = $resultado['lat'];
            $update['lng'] = $resultado['lng'];
        }

        DB::table('pontos')
            ->where('id', $pontoId)
            ->update($update);

        // Atualizar geometria PostGIS se coordenadas foram atualizadas
        if (! empty($resultado['lat'])) {
            DB::statement('
                UPDATE pontos
                SET geom = ST_SetSRID(ST_MakePoint(lng::float, lat::float), 4326)
                WHERE id = ? AND lat IS NOT NULL AND lng IS NOT NULL
            ', [$pontoId]);
        }
    }

    private function temCoordenadas(object $ponto): bool
    {
        return ! empty($ponto->lat)
            && ! empty($ponto->lng)
            && is_numeric($ponto->lat)
            && is_numeric($ponto->lng)
            && (float) $ponto->lat !== 0.0
            && (float) $ponto->lng !== 0.0;
    }

    private function calcularDistancia(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $raioTerra = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $raioTerra * $c;
    }
}
