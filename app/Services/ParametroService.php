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

        return Parametro::query()
            ->orderBy('chave')
            ->get()
            ->groupBy('grupo')
            ->sortBy(fn (Collection $items, string $grupo): int => ($pos = array_search($grupo, $ordemGrupos, true)) !== false ? (int) $pos : 99);
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

    /** @return array{grupos: array<string, array{label: string, desc: string}>, contextos: array<string, string>} */
    public function metadadosUi(): array
    {
        return [
            'grupos' => config('parametros.grupos', []),
            'contextos' => config('parametros.contextos', []),
        ];
    }
}
