<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVistoriaRequest;
use App\Http\Requests\UpdateVistoriaRequest;
use App\Models\Ponto;
use App\Models\Vistoria;
use App\Services\MoradorService;
use App\Services\ParametroService;
use App\Services\VistoriaRascunhoService;
use App\Services\VistoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VistoriaController extends Controller
{
    public function __construct(
        private MoradorService $moradorService,
        private VistoriaService $vistoriaService,
        private VistoriaRascunhoService $rascunhoService,
        private ParametroService $parametroService,
    ) {}

    public function show(Vistoria $vistoria): View
    {
        return $this->renderVistoria($vistoria, 'vistorias.show');
    }

    public function report(Vistoria $vistoria): View
    {
        return $this->renderVistoria($vistoria, 'vistorias.report');
    }

    public function reportPrint(Vistoria $vistoria): View
    {
        return $this->renderVistoria($vistoria, 'vistorias.report-print');
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
            'participantes.roles',
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

        $fields = $this->vistoriaService->montarCamposVistoria($request, $validated);

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

        // Processar upload de novas fotos (com legenda e publica)
        if ($request->hasFile('fotos')) {
            $legendas = $request->input('legendas_fotos', []);
            $publicas = $request->input('publicas_fotos', []);
            foreach ($request->file('fotos') as $index => $foto) {
                if ($foto->isValid()) {
                    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($foto->getClientOriginalName(), PATHINFO_FILENAME));
                    $legenda = $legendas[$index] ?? '';
                    $publica = ($publicas[$index] ?? '0') === '1';
                    $media = $vistoria->addMedia($foto)
                        ->usingName($safeName)
                        ->withCustomProperties([
                            'legenda' => $legenda,
                            'publica' => $publica,
                        ])
                        ->toMediaCollection('fotos');

                }
            }
        }

        $this->invalidarCachesPosMutacaoVistoria();

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
        $this->vistoriaService->finalizar($vistoria);

        return redirect()->route('vistorias.show', $vistoria)->with('success', 'Zeladoria finalizada com sucesso!');
    }

    public function reativar(Vistoria $vistoria): RedirectResponse
    {
        $this->authorize('reativar', $vistoria);
        $this->vistoriaService->reativar($vistoria);

        return redirect()->route('vistorias.show', $vistoria)->with('success', 'Zeladoria reativada. O responsavel pode retomar a edicao.');
    }

    public function cancelar(Vistoria $vistoria): RedirectResponse
    {
        $this->authorize('cancelar', $vistoria);
        $this->vistoriaService->cancelar($vistoria);

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

        $this->invalidarCachesPosMutacaoVistoria();

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
        if (! $request->has('situacao')) {
            $request->merge(['situacao' => 'aberta']);
        }

        $request->merge(['supervisor' => (string) auth()->id(), '_minhas' => true]);

        return $this->index($request, skipAuth: true);
    }

    public function index(Request $request, bool $skipAuth = false): View
    {
        if (! $skipAuth) {
            $this->authorize('viewAny', Vistoria::class);
        }

        $perPageMax = $this->parametroService->perPageMaximo();

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
            'situacao' => 'nullable|in:aberta,finalizada,todas',
            'per_page' => "nullable|integer|min:1|max:{$perPageMax}",
        ]);

        $perPage = $this->parametroService->resolverPerPage(
            $request->filled('per_page') ? (int) $request->input('per_page') : null
        );

        $vistorias = $this->vistoriaService->listarComFiltros(
            $request->only(['endereco', 'numero_endereco', 'logradouro', 'numero', 'bairro',
                'regional', 'resultado', 'data_inicio', 'data_fim', 'supervisor',
                'data_prevista_inicio', 'data_prevista_fim',
                'tipo_abordagem', 'situacao_comunicado', 'retorno_previsto', 'situacao']),
            $perPage
        );

        return view('vistorias.index', array_merge(
            compact('vistorias'),
            $this->getFilterData(),
            ['perPagePadrao' => $this->parametroService->perPagePadrao()]
        ));
    }

    public function exportarRoteiro(Request $request): View|Response
    {
        $request->validate([
            'data_prevista_inicio' => 'required|date',
            'data_prevista_fim' => 'nullable|date|after_or_equal:data_prevista_inicio',
            'supervisor' => 'nullable|integer|exists:users,id',
            'regional' => 'nullable|string|max:50',
            'format' => 'nullable|string|in:pdf,csv',
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

        if ($request->get('format') === 'csv') {
            return $this->downloadRoteiroCsv($vistorias);
        }

        return view('vistorias.roteiro', $viewData);
    }

    /**
     * @param  Collection<int, \stdClass>  $vistorias
     */
    private function downloadRoteiroCsv(Collection $vistorias): StreamedResponse
    {
        $filename = 'roteiro-zeladoria-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($vistorias): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            // BOM UTF-8 para Excel abrir acentos corretamente
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Data Prevista',
                'Período',
                'Endereço',
                'Bairro',
                'Regional',
                'Supervisor',
                'Resultado',
            ], ';');

            foreach ($vistorias as $v) {
                $periodo = match ($v->periodo_zeladoria) {
                    'manha' => 'Manhã',
                    'tarde' => 'Tarde',
                    default => '-',
                };
                $endereco = trim(sprintf(
                    '%s %s, %s%s',
                    $v->tipo ?? '',
                    $v->logradouro ?? '',
                    $v->numero ?? '',
                    $v->complemento ? ' - '.$v->complemento : ''
                ));

                fputcsv($out, [
                    Carbon::parse($v->data_prevista_zeladoria)->format('d/m/Y'),
                    $periodo,
                    $endereco,
                    $v->bairro ?? '',
                    $v->regional ?? '',
                    $v->usuario ?? '',
                    $v->resultado_acao ?? '-',
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function store(StoreVistoriaRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        ['vistoria' => $vistoria, 'ponto_novo' => $pontoNovo] =
            $this->vistoriaService->criarComRelacionamentos($request, $validated);

        $this->invalidarCachesPosMutacaoVistoria($pontoNovo);

        $this->rascunhoService->descartarAposStore(
            $request->user(),
            isset($validated['ponto_id']) ? (int) $validated['ponto_id'] : (int) $vistoria->ponto_id,
            isset($validated['lat']) ? (float) $validated['lat'] : null,
            isset($validated['lng']) ? (float) $validated['lng'] : null,
        );

        return redirect()->route('vistorias.show', $vistoria)->with('success', 'Zeladoria registrada com sucesso!');
    }

    /**
     * @return array<string, mixed>
     */
    private function getFilterData(): array
    {
        return $this->vistoriaService->getFilterData();
    }

    private function invalidarCachesPosMutacaoVistoria(bool $novoPonto = false): void
    {
        if ($novoPonto) {
            Cache::forget('dashboard:total_pontos');
        }

        Cache::forget('dashboard:totais');
        Cache::forget('dashboard:dados_mensais');
        $this->vistoriaService->invalidarCacheListagem();
    }
}
