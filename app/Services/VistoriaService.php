<?php

namespace App\Services;

use App\Http\Requests\StoreVistoriaRequest;
use App\Models\Ponto;
use App\Models\User;
use App\Models\Vistoria;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VistoriaService
{
    private const LIST_CACHE_TAGS = ['vistorias', 'vistorias_list'];

    private const LIST_CACHE_VERSION_KEY = 'vistorias:list:version';

    public function __construct(
        private MoradorService $moradorService,
        private PontoService $pontoService,
    ) {}

    /**
     * Cria a vistoria com todos os relacionamentos numa única transação:
     * ponto (novo ou existente), participantes, "Minha Equipe" do owner,
     * fotos (Spatie MediaLibrary) e moradores (novos + presença).
     *
     * @param  array<string, mixed>  $validated
     * @return array{vistoria: Vistoria, ponto_novo: bool}
     */
    public function criarComRelacionamentos(StoreVistoriaRequest $request, array $validated): array
    {
        $pontoNovo = false;

        $vistoria = DB::transaction(function () use ($request, $validated, &$pontoNovo) {
            $pontoId = $validated['ponto_id'] ?? null;
            $pontoNovo = false;

            if (! $pontoId) {
                $result = $this->pontoService->findOrCreateFromCoordinates(
                    (float) $validated['lat'],
                    (float) $validated['lng'],
                    $validated['complemento_ponto'] ?? null
                );
                $pontoId = $result['id'];
                $pontoNovo = $result['created'];
            }

            $fields = $this->montarCamposVistoria($request, $validated);
            $fields['ponto_id'] = $pontoId;
            $fields['user_id'] = $request->user()->id;

            $vistoria = Vistoria::create($fields);

            // Sincronizar participantes da equipe
            /** @var array<int, int> $participantesIds */
            $participantesIds = $validated['participantes'] ?? [];
            if (! empty($participantesIds)) {
                $vistoria->participantes()->sync($participantesIds);
            }

            // Regra: a equipe marcada na vistoria atualiza a "Minha Equipe" do owner
            // (no store, o owner é sempre o usuário logado).
            $idsParaEquipe = collect($participantesIds)
                ->filter(fn ($id) => (int) $id !== (int) $request->user()->id)
                ->unique()
                ->values()
                ->all();
            $request->user()->team()->sync($idsParaEquipe);

            // Processar upload de fotos usando Spatie Media Library
            if ($request->hasFile('fotos')) {
                $legendas = $request->input('legendas_fotos', []);
                $publicas = $request->input('publicas_fotos', []);
                foreach ($request->file('fotos') as $index => $foto) {
                    if ($foto->isValid()) {
                        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($foto->getClientOriginalName(), PATHINFO_FILENAME));
                        $legenda = $legendas[$index] ?? '';
                        $publica = ($publicas[$index] ?? '0') === '1';
                        $vistoria->addMedia($foto)
                            ->usingName($safeName)
                            ->withCustomProperties([
                                'legenda' => $legenda,
                                'publica' => $publica,
                            ])
                            ->toMediaCollection('fotos');
                    }
                }
            }

            // Atualizar complemento do ponto existente se informado
            $ponto = Ponto::find($pontoId);
            if ($ponto && ! $pontoNovo && ! empty($validated['complemento_ponto'])) {
                $ponto->update(['complemento' => $validated['complemento_ponto']]);
            }

            // Criar novos moradores e vincular ao ponto
            if (! empty($validated['novos_moradores'])) {
                foreach ($validated['novos_moradores'] as $dadosMorador) {
                    $this->moradorService->criarComEntrada($dadosMorador, $ponto, $vistoria);
                }
            }

            // Atualizar presenca dos moradores existentes
            if (! empty($validated['moradores_presentes'])) {
                $this->moradorService->atualizarPresencaVistoria(
                    $vistoria,
                    $validated['moradores_presentes']
                );
            }

            return $vistoria;
        });

        return ['vistoria' => $vistoria, 'ponto_novo' => $pontoNovo];
    }

    /**
     * Monta os campos da vistoria a partir do request validado (store e update).
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function montarCamposVistoria(Request $request, array $validated): array
    {
        $abrigosTipos = null;
        if (! empty($validated['abrigos_tipos'])) {
            $abrigosTipos = array_values(array_filter($validated['abrigos_tipos'], fn ($v) => ! empty($v)));
            if (empty($abrigosTipos)) {
                $abrigosTipos = null;
            }
        }

        return [
            'data_abordagem' => Carbon::createFromFormat('Y-m-d\TH:i', $validated['data_abordagem']),
            'tipo_abordagem_id' => $validated['tipo_abordagem_id'],
            'quantidade_pessoas' => $validated['quantidade_pessoas'] ?? 0,
            'nomes_pessoas' => $validated['nomes_pessoas'] ?? '',
            'resultado_acao_id' => $validated['resultado_acao_id'],
            'tipo_abrigo_desmontado_id' => $validated['tipo_abrigo_desmontado_id'] ?? null,
            'qtd_kg' => $validated['qtd_kg'] ?? 0,
            'observacao' => $validated['observacao'] ?? '',
            'resistencia' => $request->boolean('resistencia') ? 1 : 0,
            'num_reduzido' => $request->boolean('num_reduzido') ? 1 : 0,
            'casal' => $request->boolean('casal') ? 1 : 0,
            'qtd_casais' => $request->boolean('casal') ? ($validated['qtd_casais'] ?? 1) : 0,
            'catador_reciclados' => $request->boolean('catador_reciclados') ? 1 : 0,
            'fixacao_antiga' => $request->boolean('fixacao_antiga') ? 1 : 0,
            'excesso_objetos' => $request->boolean('excesso_objetos') ? 1 : 0,
            'trafico_ilicitos' => $request->boolean('trafico_ilicitos') ? 1 : 0,
            'crianca_adolescente' => $request->boolean('crianca_adolescente') ? 1 : 0,
            'idosos' => $request->boolean('idosos') ? 1 : 0,
            'gestante' => $request->boolean('gestante') ? 1 : 0,
            'lgbtqiapn' => $request->boolean('lgbtqiapn') ? 1 : 0,
            'cena_uso_caracterizada' => $request->boolean('cena_uso_caracterizada') ? 1 : 0,
            'deficiente' => $request->boolean('deficiente') ? 1 : 0,
            'agrupamento_quimico' => $request->boolean('agrupamento_quimico') ? 1 : 0,
            'saude_mental' => $request->boolean('saude_mental') ? 1 : 0,
            'animais' => $request->boolean('animais') ? 1 : 0,
            'qtd_animais' => $request->boolean('animais') ? ($validated['qtd_animais'] ?? 1) : 0,
            'qtd_abrigos_provisorios' => $validated['qtd_abrigos_provisorios'] ?? 0,
            'abrigos_tipos' => $abrigosTipos,
            'conducao_forcas_seguranca' => ($validated['conducao_forcas_seguranca'] ?? '0') === '1',
            'conducao_forcas_observacao' => ($validated['conducao_forcas_seguranca'] ?? '0') === '1'
                ? ($validated['conducao_forcas_observacao'] ?? '')
                : null,
            'apreensao_fiscal' => $request->boolean('apreensao_fiscal') ? 1 : 0,
            'auto_fiscalizacao_aplicado' => ($validated['auto_fiscalizacao_aplicado'] ?? '0') === '1',
            'auto_fiscalizacao_numero' => ($validated['auto_fiscalizacao_aplicado'] ?? '0') === '1'
                ? ($validated['auto_fiscalizacao_numero'] ?? '')
                : null,
            'e1_id' => $validated['e1_id'] ?? null,
            'e2_id' => $validated['e2_id'] ?? null,
            'e3_id' => $validated['e3_id'] ?? null,
            'e4_id' => $validated['e4_id'] ?? null,
            'e5_id' => $validated['e5_id'] ?? null,
            'e6_id' => $validated['e6_id'] ?? null,
            'houve_lavacao' => $request->boolean('houve_lavacao') ? 1 : 0,
            'houve_comunicado' => $request->boolean('houve_comunicado') ? 1 : 0,
            'data_comunicado' => $request->boolean('houve_comunicado') ? ($validated['data_comunicado'] ?? null) : null,
            'data_prevista_zeladoria' => $validated['data_prevista_zeladoria'] ?? null,
            'periodo_zeladoria' => $validated['periodo_zeladoria'] ?? null,
        ];
    }

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
            'usuariosEquipe' => $usuariosQuery->with('roles')->get(['id', 'name', 'email']),
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
     * @return LengthAwarePaginator<int, \stdClass>
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
                'p.complemento', 'v.finalizada', 'v.cancelada', 'v.user_id',
                'v.data_prevista_zeladoria', 'v.periodo_zeladoria',
                'v.houve_comunicado', 'v.data_comunicado',
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
     * Invalida o cache da listagem admin (/vistorias).
     * Chamado após create/update/delete/finalizar/cancelar/reativar.
     */
    public function invalidarCacheListagem(): void
    {
        if ($this->cacheListagemSuportaTags()) {
            Cache::tags(self::LIST_CACHE_TAGS)->flush();

            return;
        }

        Cache::put(
            self::LIST_CACHE_VERSION_KEY,
            $this->versaoCacheListagem() + 1,
            now()->addYear()
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function getListCacheKey(array $filtros, int $perPage, int $page): string
    {
        $filtrosHash = md5(serialize($filtros));
        $versao = $this->versaoCacheListagem();

        return "vistorias:list:v{$versao}:{$filtrosHash}:{$perPage}:{$page}";
    }

    private function versaoCacheListagem(): int
    {
        return (int) Cache::get(self::LIST_CACHE_VERSION_KEY, 1);
    }

    private function cacheListagemSuportaTags(): bool
    {
        return method_exists(Cache::getStore(), 'tags');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, \stdClass>
     */
    public function listarComFiltros(array $filtros, int $perPage): LengthAwarePaginator
    {
        $page = (int) request()->input('page', 1);
        $cacheKey = $this->getListCacheKey($filtros, $perPage, $page);

        // Cache por 2 minutos para filtros sem data (mais estaveis)
        // Cache por 30 segundos para filtros com data (mais dinamicos)
        $temFiltroData = ! empty($filtros['data_inicio']) || ! empty($filtros['data_fim']);
        $ttl = $temFiltroData ? 30 : 120;

        $loader = fn () => $this->executarListarComFiltros($filtros, $perPage);

        if ($this->cacheListagemSuportaTags()) {
            return Cache::tags(self::LIST_CACHE_TAGS)->remember($cacheKey, $ttl, $loader);
        }

        return Cache::remember($cacheKey, $ttl, $loader);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return LengthAwarePaginator<int, \stdClass>
     */
    private function executarListarComFiltros(array $filtros, int $perPage): LengthAwarePaginator
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
                'v.houve_comunicado', 'v.data_comunicado',
                DB::raw('(SELECT MAX(v2.data_abordagem) FROM vistorias v2 WHERE v2.ponto_id = v.ponto_id AND v2.deleted_at IS NULL) as ultima_vistoria_ponto'),
            ]);

        $this->aplicarFiltros($query, $filtros);

        return $query->orderBy('v.data_abordagem', 'desc')->paginate($perPage);
    }

    /**
     * Aplicar filtros na query
     */
    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltros(Builder $query, array $filtros): void
    {
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
        if (! empty($filtros['tipo_abordagem'])) {
            $query->where('v.tipo_abordagem_id', $filtros['tipo_abordagem']);
        }
        if (! empty($filtros['situacao_comunicado'])) {
            $this->applySituacaoComunicadoFilter($query, $filtros['situacao_comunicado']);
        }
        if (! empty($filtros['retorno_previsto'])) {
            $this->applyRetornoPrevisto($query, $filtros['retorno_previsto']);
        }
        if (! empty($filtros['situacao']) && $filtros['situacao'] !== 'todas') {
            match ($filtros['situacao']) {
                'aberta' => $query->where('v.finalizada', false)->where('v.cancelada', false),
                'finalizada' => $query->where('v.finalizada', true)->where('v.cancelada', false),
                default => null,
            };
        }
    }

    /**
     * Autocomplete de logradouros que possuem vistorias.
     *
     * @return Collection<int, \stdClass>
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
     * @return Collection<int, \stdClass>
     */
    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, \stdClass>
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
     * @return array{bairros: Collection<int, string>, regionais: Collection<int, string>, resultados: Collection<int, \stdClass>, supervisores: Collection<int, \stdClass>}
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
            'tiposAbordagem' => Cache::remember('filtro:tipos_abordagem', 3600, fn () => DB::table('tipo_abordagem')
                ->orderBy('id')->get()),
        ];
    }

    private function applySituacaoComunicadoFilter(Builder $query, string $situacao): void
    {
        $hoje = now()->toDateString();

        match ($situacao) {
            'com_comunicado' => $query
                ->where('ta.tipo', 'ilike', '%comunica%')
                ->whereNotNull('v.data_prevista_zeladoria'),

            'sem_comunicado' => $query
                ->where(function (Builder $q) {
                    $q->where('ta.tipo', 'not ilike', '%comunica%')
                        ->orWhereNull('ta.tipo');
                }),

            'aguardando_retorno' => $query
                ->where('ta.tipo', 'ilike', '%comunica%')
                ->whereNotNull('v.data_prevista_zeladoria')
                ->where('v.finalizada', true)
                ->whereDate('v.data_prevista_zeladoria', '>=', $hoje),

            default => null,
        };
    }

    private function applyRetornoPrevisto(Builder $query, string $retorno): void
    {
        $hoje = now()->toDateString();

        $query->whereNotNull('v.data_prevista_zeladoria');

        match ($retorno) {
            'vencidos' => $query->whereDate('v.data_prevista_zeladoria', '<', $hoje),
            'proximos_7' => $query
                ->whereDate('v.data_prevista_zeladoria', '>=', $hoje)
                ->whereDate('v.data_prevista_zeladoria', '<=', now()->addDays(7)->toDateString()),
            'proximos_30' => $query
                ->whereDate('v.data_prevista_zeladoria', '>=', $hoje)
                ->whereDate('v.data_prevista_zeladoria', '<=', now()->addDays(30)->toDateString()),
            default => null,
        };
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
