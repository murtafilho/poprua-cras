<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VistoriaService
{
    /**
     * Dados para os selects do formulário de criação/edição.
     *
     * `usuariosEquipe`: todos os usuários ativos do sistema (exceto o próprio).
     * `participantesPreSelecionados`: IDs dos usuários marcados como "minha equipe"
     * pelo usuário autenticado (em /minha-equipe). Vazio em edicao — `edit()`
     * sobrescreve com os participantes ja salvos da propria vistoria.
     *
     * @return array<string, mixed>
     */
    public function getFormSelectData(): array
    {
        $me = Auth::user();
        $participantesPreSelecionados = $me
            ? $me->team()->pluck('users.id')->all()
            : [];

        $usuariosQuery = User::query()
            ->where('ativo', true)
            ->permission('participar de equipes vistoria')
            ->orderBy('name');
        if ($me) {
            $usuariosQuery->where('id', '!=', $me->id);
        }

        return [
            'tiposAbordagem' => DB::table('tipo_abordagem')->orderBy('id')->get(),
            'tiposAbrigo' => DB::table('tipo_abrigo_desmontado')->orderBy('id')->get(),
            'resultadosAcao' => DB::table('resultados_acoes')->orderBy('id')->get(),
            'encaminhamentos' => DB::table('encaminhamentos')->orderBy('id')->get(),
            'usuariosEquipe' => $usuariosQuery->get(['id', 'name', 'email']),
            'participantesPreSelecionados' => $participantesPreSelecionados,
        ];
    }

    /**
     * Tipos de abrigo para exibição no show/report.
     *
     * @param  array<mixed>|null  $ids
     * @return array<string>
     */
    public function getTiposAbrigoSelecionados(?array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return DB::table('tipo_abrigo_desmontado')
            ->whereIn('id', $ids)
            ->pluck('tipo_abrigo')
            ->toArray();
    }

    /**
     * Listagem paginada das vistorias do usuário logado.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function listarMinhas(int $userId, array $filtros, int $perPage): LengthAwarePaginator
    {
        $query = $this->buildBaseListQuery()
            ->where('v.user_id', $userId)
            ->select([
                'v.id', 'v.ponto_id', 'v.data_abordagem', 'v.quantidade_pessoas',
                'v.qtd_kg', 'v.observacao',
                DB::raw('ea."NOME_LOGRADOURO" as logradouro'),
                DB::raw('ea."SIGLA_TIPO_LOGRADOURO" as tipo'),
                DB::raw('COALESCE(ea."NUMERO_IMOVEL", p.numero) as numero'),
                'p.lat', 'p.lng',
                DB::raw('ea."NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('ea."NOME_REGIONAL" as regional'),
                'ta.tipo as tipo_abordagem',
                'ra.resultado as resultado_acao',
                'p.complemento', 'v.finalizada', 'v.cancelada',
            ]);

        if (! empty($filtros['data_inicio'])) {
            $query->whereDate('v.data_abordagem', '>=', $filtros['data_inicio']);
        }
        if (! empty($filtros['data_fim'])) {
            $query->whereDate('v.data_abordagem', '<=', $filtros['data_fim']);
        }
        if (! empty($filtros['resultado'])) {
            $query->where('v.resultado_acao_id', $filtros['resultado']);
        }

        return $query->orderBy('v.data_abordagem', 'desc')->paginate($perPage);
    }

    /**
     * Listagem paginada com todos os filtros (admin / index).
     *
     * @param  array<string, mixed>  $filtros
     */
    public function listarComFiltros(array $filtros, int $perPage): LengthAwarePaginator
    {
        $query = $this->buildBaseListQuery()
            ->leftJoin('users as u', 'u.id', '=', 'v.user_id')
            ->select([
                'v.id', 'v.ponto_id', 'v.data_abordagem', 'v.quantidade_pessoas',
                'v.qtd_kg', 'v.observacao',
                DB::raw('ea."NOME_LOGRADOURO" as logradouro'),
                DB::raw('ea."SIGLA_TIPO_LOGRADOURO" as tipo'),
                DB::raw('COALESCE(ea."NUMERO_IMOVEL", p.numero) as numero'),
                'p.lat', 'p.lng',
                DB::raw('ea."NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('ea."NOME_REGIONAL" as regional'),
                'ta.tipo as tipo_abordagem',
                'ra.resultado as resultado_acao',
                'u.name as usuario', 'p.complemento',
                'v.data_prevista_zeladoria', 'v.periodo_zeladoria',
                'v.finalizada', 'v.cancelada', 'v.user_id',
            ]);

        if (! empty($filtros['endereco'])) {
            $query->where('ea.NOME_LOGRADOURO', $filtros['endereco']);
        }
        if (! empty($filtros['numero_endereco'])) {
            $query->where('ea.NUMERO_IMOVEL', $filtros['numero_endereco']);
        }
        if (! empty($filtros['logradouro'])) {
            $query->where('ea.NOME_LOGRADOURO', 'ilike', '%'.$filtros['logradouro'].'%');
        }
        if (! empty($filtros['numero'])) {
            $query->where('ea.NUMERO_IMOVEL', $filtros['numero']);
        }
        if (! empty($filtros['bairro'])) {
            $query->where('ea.NOME_BAIRRO_POPULAR', 'ilike', '%'.$filtros['bairro'].'%');
        }
        if (! empty($filtros['regional'])) {
            $query->where('ea.NOME_REGIONAL', $filtros['regional']);
        }
        if (! empty($filtros['resultado'])) {
            $query->where('v.resultado_acao_id', $filtros['resultado']);
        }
        if (! empty($filtros['data_inicio'])) {
            $query->whereDate('v.data_abordagem', '>=', $filtros['data_inicio']);
        }
        if (! empty($filtros['data_fim'])) {
            $query->whereDate('v.data_abordagem', '<=', $filtros['data_fim']);
        }
        if (! empty($filtros['supervisor'])) {
            $query->where('v.user_id', $filtros['supervisor']);
        }
        if (! empty($filtros['data_prevista_inicio'])) {
            $query->whereDate('v.data_prevista_zeladoria', '>=', $filtros['data_prevista_inicio']);
        }
        if (! empty($filtros['data_prevista_fim'])) {
            $query->whereDate('v.data_prevista_zeladoria', '<=', $filtros['data_prevista_fim']);
        }

        return $query->orderBy('v.data_abordagem', 'desc')->paginate($perPage);
    }

    /**
     * Autocomplete de logradouros que possuem vistorias.
     */
    public function buscarLogradourosSugeridos(string $termo, ?int $numero): Collection
    {
        $base = DB::table('vistorias as v')
            ->join('pontos as p', 'p.id', '=', 'v.ponto_id')
            ->join('endereco_atualizados as ea', 'ea.id', '=', 'p.endereco_atualizado_id')
            ->where('ea.NOME_LOGRADOURO', 'ilike', '%'.$termo.'%')
            ->whereRaw('ea."NUMERO_IMOVEL" ~ \'^[0-9]+$\'');

        if ($numero !== null) {
            $sub = (clone $base)->selectRaw(
                'DISTINCT ea."SIGLA_TIPO_LOGRADOURO" as tipo, ea."NOME_LOGRADOURO" as logradouro, '.
                'CAST(ea."NUMERO_IMOVEL" AS INTEGER) as numero, ea."NOME_REGIONAL" as regional, '.
                'ABS(CAST(ea."NUMERO_IMOVEL" AS INTEGER) - ?) as diff',
                [$numero]
            );

            return DB::query()->fromSub($sub, 'sub')
                ->select(['tipo', 'logradouro', 'numero', 'regional'])
                ->orderByRaw('CASE WHEN logradouro ILIKE ? THEN 0 ELSE 1 END', [$termo.'%'])
                ->orderBy('diff')
                ->limit(20)
                ->get();
        }

        $sub = (clone $base)->selectRaw(
            'DISTINCT ea."SIGLA_TIPO_LOGRADOURO" as tipo, ea."NOME_LOGRADOURO" as logradouro, '.
            'CAST(ea."NUMERO_IMOVEL" AS INTEGER) as numero, ea."NOME_REGIONAL" as regional'
        );

        return DB::query()->fromSub($sub, 'sub')
            ->select(['tipo', 'logradouro', 'numero', 'regional'])
            ->orderByRaw('CASE WHEN logradouro ILIKE ? THEN 0 ELSE 1 END', [$termo.'%'])
            ->orderBy('logradouro')->orderBy('numero')
            ->limit(20)
            ->get();
    }

    /**
     * Roteiro de zeladorias com data prevista.
     *
     * @param  array<string, mixed>  $filtros
     */
    public function listarRoteiro(array $filtros): Collection
    {
        $query = DB::table('vistorias as v')
            ->join('pontos as p', 'p.id', '=', 'v.ponto_id')
            ->leftJoin('endereco_atualizados as ea', 'ea.id', '=', 'p.endereco_atualizado_id')
            ->leftJoin('users as u', 'u.id', '=', 'v.user_id')
            ->leftJoin('resultados_acoes as ra', 'ra.id', '=', 'v.resultado_acao_id')
            ->whereNull('v.deleted_at')
            ->whereNotNull('v.data_prevista_zeladoria')
            ->whereDate('v.data_prevista_zeladoria', '>=', $filtros['data_prevista_inicio'])
            ->select([
                'v.id', 'v.data_abordagem', 'v.data_prevista_zeladoria', 'v.periodo_zeladoria',
                DB::raw('ea."NOME_LOGRADOURO" as logradouro'),
                DB::raw('ea."SIGLA_TIPO_LOGRADOURO" as tipo'),
                DB::raw('COALESCE(ea."NUMERO_IMOVEL", p.numero) as numero'),
                DB::raw('ea."NOME_BAIRRO_POPULAR" as bairro'),
                DB::raw('ea."NOME_REGIONAL" as regional'),
                'ra.resultado as resultado_acao',
                'u.name as usuario', 'p.complemento',
            ]);

        if (! empty($filtros['data_prevista_fim'])) {
            $query->whereDate('v.data_prevista_zeladoria', '<=', $filtros['data_prevista_fim']);
        }
        if (! empty($filtros['supervisor'])) {
            $query->where('v.user_id', $filtros['supervisor']);
        }
        if (! empty($filtros['regional'])) {
            $query->where('ea.NOME_REGIONAL', $filtros['regional']);
        }

        return $query->orderBy('v.data_prevista_zeladoria')->orderBy('v.periodo_zeladoria')->get();
    }

    /**
     * Dados de filtro para a listagem com cache de 1h.
     *
     * @return array{bairros: Collection, regionais: Collection, resultados: Collection, supervisores: Collection}
     */
    public function getFilterData(): array
    {
        return [
            'bairros' => Cache::remember('filtro:bairros', 3600, fn () => DB::table('endereco_atualizados')
                ->select('NOME_BAIRRO_POPULAR as bairro')->distinct()
                ->whereNotNull('NOME_BAIRRO_POPULAR')->orderBy('NOME_BAIRRO_POPULAR')->pluck('bairro')),
            'regionais' => Cache::remember('filtro:regionais', 3600, fn () => DB::table('endereco_atualizados')
                ->select('NOME_REGIONAL as regional')->distinct()
                ->whereNotNull('NOME_REGIONAL')->orderBy('NOME_REGIONAL')->pluck('regional')),
            'resultados' => Cache::remember('filtro:resultados', 3600, fn () => DB::table('resultados_acoes')
                ->orderBy('id')->get()),
            'supervisores' => Cache::remember('filtro:supervisores', 3600, fn () => DB::table('users')
                ->select('id', 'name')->orderBy('name')->get()),
        ];
    }

    private function buildBaseListQuery(): Builder
    {
        return DB::table('vistorias as v')
            ->join('pontos as p', 'p.id', '=', 'v.ponto_id')
            ->leftJoin('endereco_atualizados as ea', 'ea.id', '=', 'p.endereco_atualizado_id')
            ->leftJoin('tipo_abordagem as ta', 'ta.id', '=', 'v.tipo_abordagem_id')
            ->leftJoin('resultados_acoes as ra', 'ra.id', '=', 'v.resultado_acao_id')
            ->whereNull('v.deleted_at');
    }
}
