<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMoradorRequest;
use App\Http\Requests\UpdateMoradorRequest;
use App\Models\Morador;
use App\Models\Ponto;
use App\Models\Vistoria;
use App\Services\MoradorService;
use App\Services\ParametroService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class MoradorController extends Controller
{
    public function __construct(
        private MoradorService $moradorService,
        private ParametroService $parametroService,
    ) {}

    /**
     * Lista moradores com filtros
     */
    public function index(Request $request): JsonResponse
    {
        $query = Morador::query()->with(['pontoAtual.enderecoAtualizado']);

        // Filtrar por ponto
        if ($request->filled('ponto_id')) {
            $query->where('ponto_atual_id', $request->ponto_id);
        }

        // Filtrar por termo de busca
        if ($request->filled('search')) {
            $termo = $request->search;
            $query->where(function ($q) use ($termo) {
                $q->where('nome_social', 'like', "%{$termo}%")
                    ->orWhere('apelido', 'like', "%{$termo}%")
                    ->orWhere('nome_registro', 'like', "%{$termo}%");
            });
        }

        // Filtrar sem ponto (disponíveis para vincular)
        if ($request->boolean('sem_ponto')) {
            $query->whereNull('ponto_atual_id');
        }

        $perPage = $this->parametroService->resolverPerPage(
            $request->filled('per_page') ? (int) $request->per_page : null
        );

        $moradores = $query->orderBy('nome_social')->paginate($perPage);

        return response()->json($moradores);
    }

    /**
     * Retorna um morador específico
     */
    public function show(Morador $morador): JsonResponse
    {
        $morador->load(['pontoAtual.enderecoAtualizado', 'historico.ponto.enderecoAtualizado']);

        return response()->json($morador);
    }

    public function store(StoreMoradorRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $fotos = $this->extrairFotos($request);
        unset($dados['fotografia'], $dados['fotografias']);

        if (! empty($dados['ponto_id'])) {
            $ponto = Ponto::findOrFail($dados['ponto_id']);
            $vistoria = ! empty($dados['vistoria_id'])
                ? Vistoria::find($dados['vistoria_id'])
                : null;

            unset($dados['ponto_id'], $dados['vistoria_id']);

            $morador = $this->moradorService->criarComEntrada($dados, $ponto, $vistoria);
        } else {
            unset($dados['ponto_id'], $dados['vistoria_id']);
            $morador = Morador::create($dados);
        }

        $this->anexarFotos($morador, $fotos, $request->user()?->id);

        return response()->json([
            'success' => true,
            'message' => 'Morador criado com sucesso.',
            'data' => $morador->load('pontoAtual'),
        ], 201);
    }

    public function update(UpdateMoradorRequest $request, Morador $morador): JsonResponse
    {
        $dados = $request->validated();
        $fotos = $this->extrairFotos($request);
        unset($dados['fotografia'], $dados['fotografias']);

        $this->anexarFotos($morador, $fotos, $request->user()?->id);

        $morador->update($dados);

        return response()->json([
            'success' => true,
            'message' => 'Morador atualizado com sucesso.',
            'data' => $morador->fresh('pontoAtual'),
        ]);
    }

    /**
     * Arquiva morador (soft delete)
     *
     * O morador não é excluído fisicamente, apenas arquivado.
     * Seus dados e histórico são preservados.
     */
    public function destroy(Morador $morador): JsonResponse
    {
        // Desvincula do ponto atual se estiver vinculado
        if ($morador->ponto_atual_id) {
            $this->moradorService->registrarSaida($morador);
        }

        // Soft delete - não exclui fisicamente
        $morador->delete();

        return response()->json([
            'success' => true,
            'message' => 'Morador arquivado com sucesso. Dados preservados.',
        ]);
    }

    /**
     * Restaura morador arquivado
     */
    public function restore(int $id): JsonResponse
    {
        $morador = Morador::withTrashed()->findOrFail($id);

        if (! $morador->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'Morador não está arquivado.',
            ], 422);
        }

        $morador->restore();

        return response()->json([
            'success' => true,
            'message' => 'Morador restaurado com sucesso.',
            'data' => $morador->fresh('pontoAtual'),
        ]);
    }

    /**
     * Lista moradores arquivados
     */
    public function arquivados(Request $request): JsonResponse
    {
        $query = Morador::onlyTrashed()->with(['historico.ponto.enderecoAtualizado']);

        if ($request->filled('search')) {
            $termo = $request->search;
            $query->where(function ($q) use ($termo) {
                $q->where('nome_social', 'like', "%{$termo}%")
                    ->orWhere('apelido', 'like', "%{$termo}%")
                    ->orWhere('nome_registro', 'like', "%{$termo}%");
            });
        }

        $perPage = $this->parametroService->resolverPerPage(
            $request->filled('per_page') ? (int) $request->per_page : null
        );

        $moradores = $query->orderBy('deleted_at', 'desc')->paginate($perPage);

        return response()->json($moradores);
    }

    /**
     * Busca moradores por nome (para autocomplete/migração)
     */
    public function buscar(Request $request): JsonResponse
    {
        $request->validate([
            'termo' => ['required', 'string', 'min:2'],
            'excluir_ponto_id' => ['nullable', 'integer'],
        ]);

        $moradores = $this->moradorService->buscarPorNome(
            $request->termo,
            $request->excluir_ponto_id
        );

        $result = $moradores->map(function ($m) {
            $endereco = $m->pontoAtual?->enderecoAtualizado;
            $pontoEndereco = $endereco
                ? trim(($endereco->SIGLA_TIPO_LOGRADOURO ?? '').' '.($endereco->NOME_LOGRADOURO ?? '').', '.($endereco->NUMERO_IMOVEL ?? 'S/N').' — '.($endereco->NOME_BAIRRO_POPULAR ?? ''))
                : null;

            return [
                'id' => $m->id,
                'nome_social' => $m->nome_social,
                'apelido' => $m->apelido,
                'ponto_atual_id' => $m->ponto_atual_id,
                'ponto_endereco' => $pontoEndereco,
            ];
        });

        return response()->json($result);
    }

    /**
     * Retorna histórico de movimentação do morador
     */
    public function historico(Morador $morador): JsonResponse
    {
        $historico = $this->moradorService->getHistorico($morador);

        return response()->json([
            'morador' => $morador->only(['id', 'nome_social', 'apelido']),
            'historico' => $historico,
        ]);
    }

    /**
     * Lista moradores de um ponto específico
     */
    public function porPonto(Ponto $ponto): JsonResponse
    {
        $moradores = $this->moradorService->getMoradoresDoPonto($ponto);

        return response()->json($moradores);
    }

    /**
     * Registra entrada de morador em um ponto
     */
    public function entrada(Request $request, Morador $morador): JsonResponse
    {
        $request->validate([
            'ponto_id' => ['required', 'integer', 'exists:pontos,id'],
            'vistoria_id' => ['nullable', 'integer', 'exists:vistorias,id'],
        ]);

        $ponto = Ponto::findOrFail($request->ponto_id);
        $vistoria = $request->filled('vistoria_id')
            ? Vistoria::find($request->vistoria_id)
            : null;

        $historico = $this->moradorService->registrarEntrada($morador, $ponto, $vistoria);

        return response()->json([
            'success' => true,
            'message' => 'Entrada registrada com sucesso.',
            'data' => $historico->load('ponto'),
        ]);
    }

    /**
     * Registra saída de morador do ponto atual
     */
    public function saida(Request $request, Morador $morador): JsonResponse
    {
        $request->validate([
            'vistoria_id' => ['nullable', 'integer', 'exists:vistorias,id'],
        ]);

        $vistoria = $request->filled('vistoria_id')
            ? Vistoria::find($request->vistoria_id)
            : null;

        $historico = $this->moradorService->registrarSaida($morador, $vistoria);

        return response()->json([
            'success' => true,
            'message' => 'Saída registrada com sucesso.',
            'data' => $historico,
        ]);
    }

    /**
     * Transfere morador para outro ponto (migração)
     */
    public function transferir(Request $request, Morador $morador): JsonResponse
    {
        $request->validate([
            'ponto_id' => ['required', 'integer', 'exists:pontos,id'],
            'vistoria_id' => ['nullable', 'integer', 'exists:vistorias,id'],
        ]);

        $novoPonto = Ponto::findOrFail($request->ponto_id);
        $vistoria = $request->filled('vistoria_id')
            ? Vistoria::find($request->vistoria_id)
            : null;

        $historico = $this->moradorService->transferir(
            $morador,
            $novoPonto,
            $vistoria,
            $vistoria
        );

        return response()->json([
            'success' => true,
            'message' => 'Morador transferido com sucesso.',
            'data' => $historico->load('ponto'),
        ]);
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function extrairFotos(Request $request): array
    {
        if ($request->hasFile('fotografias')) {
            $arquivos = $request->file('fotografias');

            return is_array($arquivos) ? array_values($arquivos) : [$arquivos];
        }

        if ($request->hasFile('fotografia')) {
            return [$request->file('fotografia')];
        }

        return [];
    }

    /**
     * @param  array<int, UploadedFile>  $fotos
     */
    private function anexarFotos(Morador $morador, array $fotos, ?int $userId): void
    {
        foreach ($fotos as $foto) {
            $media = $morador->addMedia($foto)
                ->withCustomProperties(['uploaded_by_user_id' => $userId])
                ->toMediaCollection('fotos');

        }
    }
}
