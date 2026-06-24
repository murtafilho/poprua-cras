<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVistoriaRequest;
use App\Http\Requests\UpdateVistoriaRequest;
use App\Models\Ponto;
use App\Models\Vistoria;
use App\Services\MoradorService;
use App\Services\PontoService;
use App\Services\VistoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class VistoriaController extends Controller
{
    public function __construct(
        private MoradorService $moradorService,
        private PontoService $pontoService,
        private VistoriaService $vistoriaService
    ) {}

    public function show(Vistoria $vistoria): View
    {
        return $this->renderVistoria($vistoria, 'vistorias.show');
    }

    private function renderVistoria(Vistoria $vistoria, string $viewName): View
    {
        $this->authorize('view', $vistoria);

        $vistoria->load([
            'ponto.enderecoAtualizado',
            'user',
            'tipoAbordagem',
            'tipoAbrigoDesmontado',
            'resultadoAcao',
            'encaminhamento1',
            'encaminhamento2',
            'encaminhamento3',
            'encaminhamento4',
            'encaminhamento5',
            'encaminhamento6',
            'moradoresEntrada.morador',
            'participantes',
            'media',
        ]);

        $tiposAbrigoSelecionados = $this->vistoriaService->getTiposAbrigoSelecionados($vistoria->abrigos_tipos);

        // Histórico do ponto (vistoria anterior + ultimas 20) — exibido no detalhe unificado.
        $vistoriaAnterior = null;
        $historicoPonto = collect();
        if ($vistoria->ponto_id) {
            $vistoriaAnterior = Vistoria::where('ponto_id', $vistoria->ponto_id)
                ->where('id', '!=', $vistoria->id)
                ->where('data_abordagem', '<', $vistoria->data_abordagem)
                ->with(['user', 'resultadoAcao'])
                ->orderBy('data_abordagem', 'desc')
                ->first();

            $historicoPonto = Vistoria::where('ponto_id', $vistoria->ponto_id)
                ->where('id', '!=', $vistoria->id)
                ->with(['user', 'resultadoAcao'])
                ->orderBy('data_abordagem', 'desc')
                ->limit(20)
                ->get();
        }

        return view($viewName, [
            'vistoria' => $vistoria,
            'tiposAbrigoSelecionados' => $tiposAbrigoSelecionados,
            'vistoriaAnterior' => $vistoriaAnterior,
            'historicoPonto' => $historicoPonto,
        ]);
    }

    public function edit(Vistoria $vistoria): View
    {
        $this->authorize('update', $vistoria);

        $vistoria->load([
            'ponto.enderecoAtualizado',
            'ponto.moradores' => function ($query) {
                $query->whereNotNull('ponto_atual_id');
            },
        ]);

        $vistoria->load('participantes');

        return view('vistorias.edit', array_merge(
            ['vistoria' => $vistoria],
            $this->vistoriaService->getFormSelectData()
        ));
    }

    public function update(UpdateVistoriaRequest $request, Vistoria $vistoria): RedirectResponse
    {
        $validated = $request->validated();

        $fields = $this->extractVistoriaFields($request, $validated);

        if (! empty($validated['ponto_id'])) {
            $fields['ponto_id'] = $validated['ponto_id'];
        }

        $vistoria->update($fields);

        // Sincronizar participantes da equipe
        /** @var array<int, int> $participantesIds */
        $participantesIds = $validated['participantes'] ?? [];
        $vistoria->participantes()->sync($participantesIds);

        // Regra: a equipe marcada na vistoria atualiza a "Minha Equipe" do owner.
        // Só sincroniza se quem está editando é o próprio owner da vistoria.
        if ($vistoria->user_id === (int) $request->user()->id) {
            $idsParaEquipe = collect($participantesIds)
                ->filter(fn ($id) => (int) $id !== (int) $vistoria->user_id)
                ->unique()
                ->values()
                ->all();
            $request->user()->team()->sync($idsParaEquipe);
        }

        // Processar moradores
        if (! empty($validated['moradores_presentes'])) {
            $this->moradorService->atualizarPresencaVistoria($vistoria, $validated['moradores_presentes']);
        }
        if (! empty($validated['novos_moradores'])) {
            $ponto = $vistoria->ponto ?? Ponto::findOrFail($vistoria->ponto_id);
            foreach ($validated['novos_moradores'] as $dadosMorador) {
                $this->moradorService->criarComEntrada(
                    $dadosMorador,
                    $ponto,
                    $vistoria
                );
            }
        }

        // Remover fotos selecionadas
        if (! empty($validated['remover_fotos'])) {
            $vistoria->getMedia('fotos')
                ->whereIn('id', $validated['remover_fotos'])
                ->each(fn ($media) => $media->delete());
        }

        // Processar upload de novas fotos (com legenda, igual ao store())
        if ($request->hasFile('fotos')) {
            $legendas = $request->input('legendas_fotos', []);
            foreach ($request->file('fotos') as $index => $foto) {
                if ($foto->isValid()) {
                    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($foto->getClientOriginalName(), PATHINFO_FILENAME));
                    $legenda = $legendas[$index] ?? '';
                    $media = $vistoria->addMedia($foto)
                        ->usingName($safeName)
                        ->withCustomProperties(['legenda' => $legenda])
                        ->toMediaCollection('fotos');

                }
            }
        }

        Cache::forget('dashboard:dados_mensais');

        if ($request->boolean('finalizar_apos_salvar')) {
            $vistoria->update([
                'finalizada' => true,
                'finalizada_em' => now(),
                'finalizada_por' => auth()->id(),
            ]);

            return redirect()->route('vistorias.show', $vistoria)->with('success', 'Zeladoria salva e finalizada com sucesso!');
        }

        return redirect()->route('vistorias.show', $vistoria)->with('success', 'Zeladoria atualizada com sucesso!');
    }

    public function finalizar(Vistoria $vistoria): RedirectResponse
    {
        $this->authorize('update', $vistoria);

        $vistoria->update([
            'finalizada' => true,
            'finalizada_em' => now(),
            'finalizada_por' => auth()->id(),
        ]);

        return redirect()->route('vistorias.show', $vistoria)->with('success', 'Zeladoria finalizada com sucesso!');
    }

    public function reativar(Vistoria $vistoria): RedirectResponse
    {
        $this->authorize('reativar', $vistoria);

        $vistoria->update([
            'finalizada' => false,
            'finalizada_em' => null,
            'finalizada_por' => null,
        ]);

        return redirect()->route('vistorias.show', $vistoria)->with('success', 'Zeladoria reativada. O responsavel pode retomar a edicao.');
    }

    public function cancelar(Vistoria $vistoria): RedirectResponse
    {
        $this->authorize('cancelar', $vistoria);

        $vistoria->update([
            'cancelada' => true,
            'cancelada_em' => now(),
            'cancelada_por' => auth()->id(),
        ]);

        return redirect()->route('vistorias.show', $vistoria)->with('success', 'Zeladoria cancelada.');
    }

    public function complementar(Request $request, Vistoria $vistoria): RedirectResponse
    {
        $this->authorize('view', $vistoria);

        if (! $vistoria->finalizada) {
            return redirect()->route('vistorias.show', $vistoria)->with('error', 'Esta zeladoria ainda não foi finalizada.');
        }

        $validated = $request->validate([
            'justificativa' => 'required|string|min:10|max:1000',
        ]);

        $observacaoAtual = $vistoria->observacao ?? '';
        $complemento = sprintf(
            "\n\n--- Complementação em %s por %s ---\nJustificativa: %s",
            now()->format('d/m/Y H:i'),
            auth()->user()->name,
            $validated['justificativa']
        );

        $vistoria->update([
            'observacao' => $observacaoAtual.$complemento,
        ]);

        return redirect()->route('vistorias.show', $vistoria)->with('success', 'Complementação registrada com sucesso!');
    }

    public function destroy(Vistoria $vistoria): RedirectResponse
    {
        $this->authorize('delete', $vistoria);

        $vistoria->delete();

        Cache::forget('dashboard:totais');
        Cache::forget('dashboard:dados_mensais');

        return redirect()->route('vistorias.index')->with('success', 'Zeladoria excluida com sucesso!');
    }

    public function createForPonto(Ponto $ponto): RedirectResponse
    {
        // Redireciona para o create com as coordenadas do ponto
        if ($ponto->lat && $ponto->lng) {
            return redirect()->route('vistorias.create', [
                'lat' => $ponto->lat,
                'lng' => $ponto->lng,
            ]);
        }

        // Ponto sem coordenadas, vai para criacao normal
        return redirect()->route('vistorias.create');
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Vistoria::class);

        $lat = $request->query('lat');
        $lng = $request->query('lng');

        // Buscar ponto proximo ou criar novo
        $pontoProximo = null;
        if ($lat && $lng) {
            $pontoProximo = Ponto::with(['enderecoAtualizado', 'moradores' => function ($query) {
                $query->whereNotNull('ponto_atual_id');
            }])
                ->whereNotNull('lat')
                ->whereNotNull('lng')
                ->nearby((float) $lat, (float) $lng, 50)
                ->first();
        }

        // Dados do endereco de referencia (passados do mapa)
        $enderecoReferencia = null;
        $referenciaAutomatica = null;
        if ($request->filled('endereco_logradouro')) {
            $enderecoReferencia = [
                'tipo' => $request->query('endereco_tipo'),
                'logradouro' => $request->query('endereco_logradouro'),
                'numero' => $request->query('endereco_numero'),
                'bairro' => $request->query('endereco_bairro'),
                'regional' => $request->query('endereco_regional'),
                'distancia' => $request->query('endereco_distancia'),
            ];

            // Gerar referencia automatica para novos pontos
            if (! $pontoProximo && $enderecoReferencia['distancia']) {
                $referenciaAutomatica = sprintf(
                    'A %dm de %s %s, %s',
                    (int) $enderecoReferencia['distancia'],
                    $enderecoReferencia['tipo'],
                    $enderecoReferencia['logradouro'],
                    $enderecoReferencia['numero']
                );
            }
        }

        return view('vistorias.create', array_merge([
            'lat' => $lat,
            'lng' => $lng,
            'pontoProximo' => $pontoProximo,
            'enderecoReferencia' => $enderecoReferencia,
            'referenciaAutomatica' => $referenciaAutomatica,
        ], $this->vistoriaService->getFormSelectData()));
    }

    /**
     * Autocomplete de logradouros que possuem vistorias.
     */
    public function buscarLogradouros(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2|max:100',
            'numero' => 'nullable|integer|min:1',
        ]);

        $termo = $validated['q'];
        $numero = $validated['numero'] ?? null;

        return response()->json(
            $this->vistoriaService->buscarLogradourosSugeridos($termo, $numero)
        );
    }

    public function minhas(Request $request): View
    {
        $request->merge(['supervisor' => (string) auth()->id(), '_minhas' => true]);

        return $this->index($request, skipAuth: true);
    }

    public function index(Request $request, bool $skipAuth = false): View
    {
        if (! $skipAuth) {
            $this->authorize('viewAny', Vistoria::class);
        }

        $request->validate([
            'logradouro' => 'nullable|string|max:100',
            'numero' => 'nullable|string|max:20',
            'bairro' => 'nullable|string|max:100',
            'regional' => 'nullable|string|max:50',
            'resultado' => 'nullable|integer|exists:resultados_acoes,id',
            'endereco' => 'nullable|string|max:100',
            'numero_endereco' => 'nullable|string|max:20',
            'data_inicio' => 'nullable|date',
            'data_fim' => 'nullable|date|after_or_equal:data_inicio',
            'supervisor' => 'nullable|integer|exists:users,id',
            'data_prevista_inicio' => 'nullable|date',
            'data_prevista_fim' => 'nullable|date|after_or_equal:data_prevista_inicio',
            'tipo_abordagem' => 'nullable|integer|exists:tipo_abordagem,id',
            'situacao_comunicado' => 'nullable|in:com_comunicado,sem_comunicado,aguardando_retorno',
            'retorno_previsto' => 'nullable|in:vencidos,proximos_7,proximos_30',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $vistorias = $this->vistoriaService->listarComFiltros(
            $request->only(['endereco', 'numero_endereco', 'logradouro', 'numero', 'bairro',
                'regional', 'resultado', 'data_inicio', 'data_fim', 'supervisor',
                'data_prevista_inicio', 'data_prevista_fim',
                'tipo_abordagem', 'situacao_comunicado', 'retorno_previsto']),
            min((int) $request->input('per_page', 5), 100)
        );

        return view('vistorias.index', array_merge(
            compact('vistorias'),
            $this->getFilterData()
        ));
    }

    public function exportarRoteiro(Request $request): View|Response
    {
        $request->validate([
            'data_prevista_inicio' => 'required|date',
            'data_prevista_fim' => 'nullable|date|after_or_equal:data_prevista_inicio',
            'supervisor' => 'nullable|integer|exists:users,id',
            'regional' => 'nullable|string|max:50',
            'format' => 'nullable|string|in:pdf',
        ]);

        $vistorias = $this->vistoriaService->listarRoteiro(
            $request->only(['data_prevista_inicio', 'data_prevista_fim', 'supervisor', 'regional'])
        );

        $viewData = [
            'vistorias' => $vistorias,
            'dataInicio' => $request->input('data_prevista_inicio'),
            'dataFim' => $request->input('data_prevista_fim'),
        ];

        if ($request->get('format') === 'pdf') {
            $pdf = Pdf::loadView('vistorias.roteiro', $viewData)
                ->setPaper('a4', 'landscape');

            return $pdf->download('roteiro-zeladoria-'.now()->format('Y-m-d').'.pdf');
        }

        return view('vistorias.roteiro', $viewData);
    }

    public function store(StoreVistoriaRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $vistoria = DB::transaction(function () use ($request, $validated) {
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

            $fields = $this->extractVistoriaFields($request, $validated);
            $fields['ponto_id'] = $pontoId;
            $fields['user_id'] = auth()->id();

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
                foreach ($request->file('fotos') as $index => $foto) {
                    if ($foto->isValid()) {
                        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($foto->getClientOriginalName(), PATHINFO_FILENAME));
                        $legenda = $legendas[$index] ?? '';
                        $media = $vistoria->addMedia($foto)
                            ->usingName($safeName)
                            ->withCustomProperties(['legenda' => $legenda])
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

        Cache::forget('dashboard:total_pontos');
        Cache::forget('dashboard:totais');
        Cache::forget('dashboard:dados_mensais');

        return redirect()->route('vistorias.show', $vistoria)->with('success', 'Zeladoria registrada com sucesso!');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function extractVistoriaFields(Request $request, array $validated): array
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
     * @return array<string, mixed>
     */
    private function getFilterData(): array
    {
        return $this->vistoriaService->getFilterData();
    }
}
