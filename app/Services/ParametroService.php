<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Parametro;
use Illuminate\Support\Collection;

class ParametroService
{
    /** @return Collection<string, Collection<int, Parametro>> */
    public function listarAgrupados(): Collection
    {
        $ordemGrupos = config('parametros.ordem_grupos', []);

        $agrupados = Parametro::query()
            ->orderBy('chave')
            ->get()
            ->groupBy('grupo')
            ->sortBy(fn (Collection $items, string $grupo): int => ($pos = array_search($grupo, $ordemGrupos, true)) !== false ? (int) $pos : 99);

        /** @var Collection<string, Collection<int, Parametro>> $result */
        $result = new Collection;
        foreach ($agrupados as $grupo => $items) {
            $result->put((string) $grupo, Collection::make($items->values()->all()));
        }

        return $result;
    }

    /** @param  array<string, string|null>  $parametros */
    public function atualizarLote(array $parametros): void
    {
        $existentes = Parametro::query()
            ->whereIn('chave', array_keys($parametros))
            ->get()
            ->keyBy('chave');

        foreach ($parametros as $chave => $valor) {
            if (! $existentes->has($chave)) {
                continue;
            }

            Parametro::set($chave, $valor ?? '');
        }
    }

    /** @param  array{chave: string, valor?: ?string, tipo: string, grupo: string, descricao?: ?string}  $dados */
    public function criar(array $dados): Parametro
    {
        return Parametro::query()->create([
            'chave' => $dados['chave'],
            'valor' => $dados['valor'] ?? '',
            'tipo' => $dados['tipo'],
            'grupo' => $dados['grupo'],
            'descricao' => $dados['descricao'] ?? null,
        ]);
    }

    public function remover(string $chave): void
    {
        Parametro::query()->where('chave', $chave)->delete();
        Parametro::forgetCache($chave);
    }

    public function perPagePadrao(): int
    {
        return max(1, (int) Parametro::get(
            'vistorias_por_pagina',
            config('parametros.listagem.default_per_page', 5)
        ));
    }

    public function perPageMaximo(): int
    {
        return max(1, (int) Parametro::get(
            'paginacao_max',
            config('parametros.listagem.max_per_page', 100)
        ));
    }

    public function resolverPerPage(?int $solicitado): int
    {
        $padrao = $this->perPagePadrao();
        $maximo = max($padrao, $this->perPageMaximo());

        if ($solicitado === null || $solicitado < 1) {
            return $padrao;
        }

        return min($solicitado, $maximo);
    }

    public function fotoMaxKb(): int
    {
        return max(1, (int) Parametro::get(
            'foto_max_tamanho_kb',
            config('parametros.defaults.foto_max_tamanho_kb', 10240)
        ));
    }

    /** @return array{critico: int, alto: int, medio: int} */
    public function limitesComplexidade(): array
    {
        $defaults = config('parametros.defaults', []);

        return [
            'critico' => max(1, (int) Parametro::get('complexidade_critico', $defaults['complexidade_critico'] ?? 8)),
            'alto' => max(1, (int) Parametro::get('complexidade_alto', $defaults['complexidade_alto'] ?? 5)),
            'medio' => max(1, (int) Parametro::get('complexidade_medio', $defaults['complexidade_medio'] ?? 3)),
        ];
    }

    public function badgeComplexidade(int $complexidade): string
    {
        $limites = $this->limitesComplexidade();

        return match (true) {
            $complexidade >= $limites['critico'] => 'badge-danger',
            $complexidade >= $limites['alto'] => 'badge-warning',
            $complexidade >= $limites['medio'] => 'badge-info',
            $complexidade >= 1 => 'badge-success',
            default => 'badge-default',
        };
    }

    public function corComplexidade(int $complexidade): string
    {
        $limites = $this->limitesComplexidade();

        return match (true) {
            $complexidade >= $limites['critico'] => '#dc2626',
            $complexidade >= $limites['alto'] => '#f59e0b',
            $complexidade >= $limites['medio'] => '#184186',
            default => '#6b7280',
        };
    }

    /** @return array{center: array{0: float, 1: float}, zoom: int, complexidade: array{critico: int, alto: int, medio: int}} */
    public function configMapa(): array
    {
        $defaults = config('parametros.defaults', []);

        return [
            'center' => [
                (float) Parametro::get('mapa_centro_lat', $defaults['mapa_centro_lat'] ?? -19.9135),
                (float) Parametro::get('mapa_centro_lng', $defaults['mapa_centro_lng'] ?? -43.9514),
            ],
            'zoom' => max(1, (int) Parametro::get('mapa_zoom_padrao', $defaults['mapa_zoom_padrao'] ?? 12)),
            'complexidade' => $this->limitesComplexidade(),
        ];
    }

    public function sincronizarConfigApp(): void
    {
        config([
            'app.brand' => Parametro::get('app_nome', config('app.brand')),
            'app.orgao' => Parametro::get('app_orgao', config('app.orgao')),
        ]);
    }

    /** @return array{grupos: array<string, array{label: string, desc: string}>, contextos: array<string, string>} */
    public function metadadosUi(): array
    {
        return [
            'grupos' => config('parametros.grupos', []),
            'contextos' => config('parametros.contextos', []),
        ];
    }

    /** @return list<string> */
    public function regrasValidacaoFoto(bool $obrigatoria = false): array
    {
        $max = $this->fotoMaxKb();
        $regras = ['image', 'mimes:jpeg,jpg,png,webp', "max:{$max}"];

        return $obrigatoria
            ? array_merge(['required'], $regras)
            : array_merge(['nullable'], $regras);
    }
}
