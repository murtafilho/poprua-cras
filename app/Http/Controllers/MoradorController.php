<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMoradorRequest;
use App\Http\Requests\UpdateMoradorRequest;
use App\Models\Morador;
use App\Models\Ponto;
use App\Services\MoradorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MoradorController extends Controller
{
    public function __construct(
        private MoradorService $moradorService
    ) {}

    public function index(Request $request): View
    {
        $query = Morador::query()->with(['pontoAtual.enderecoAtualizado', 'media']);

        // Filtrar por termo de busca
        if ($request->filled('search')) {
            $termo = $request->search;
            $query->where(function ($q) use ($termo) {
                $q->where('nome_social', 'like', "%{$termo}%")
                    ->orWhere('apelido', 'like', "%{$termo}%")
                    ->orWhere('nome_registro', 'like', "%{$termo}%");
            });
        }

        // Filtrar por gênero
        if ($request->filled('genero')) {
            $query->where('genero', $request->genero);
        }

        // Filtrar com/sem ponto
        if ($request->filled('situacao')) {
            if ($request->situacao === 'com_ponto') {
                $query->whereNotNull('ponto_atual_id');
            } elseif ($request->situacao === 'sem_ponto') {
                $query->whereNull('ponto_atual_id');
            }
        }

        $moradores = $query->orderBy('nome_social')->paginate(15);

        // Gêneros únicos para filtro
        $generos = Morador::select('genero')
            ->distinct()
            ->whereNotNull('genero')
            ->orderBy('genero')
            ->pluck('genero');

        return view('moradores.index', [
            'moradores' => $moradores,
            'generos' => $generos,
        ]);
    }

    public function show(Morador $morador): View
    {
        $morador->load(['pontoAtual.enderecoAtualizado']);
        $historico = $this->moradorService->getHistorico($morador);

        return view('moradores.show', [
            'morador' => $morador,
            'historico' => $historico,
        ]);
    }

    public function create(Request $request): View
    {
        $ponto = null;
        if ($request->filled('ponto_id')) {
            $ponto = Ponto::with(['enderecoAtualizado'])->find($request->ponto_id);
        }

        return view('moradores.create', [
            'ponto' => $ponto,
        ]);
    }

    public function store(StoreMoradorRequest $request): RedirectResponse
    {
        $dados = $request->validated();
        $fotos = $this->extrairFotos($request);
        unset($dados['fotografias'], $dados['fotografia']);

        if (! empty($dados['ponto_id'])) {
            $ponto = Ponto::findOrFail($dados['ponto_id']);
            unset($dados['ponto_id'], $dados['vistoria_id']);
            $morador = $this->moradorService->criarComEntrada($dados, $ponto);
        } else {
            unset($dados['ponto_id'], $dados['vistoria_id']);
            $morador = Morador::create($dados);
        }

        $this->anexarFotos($morador, $fotos, $request->user()?->id);

        return redirect()
            ->route('moradores.show', $morador)
            ->with('success', 'Morador cadastrado com sucesso.');
    }

    public function edit(Morador $morador): View
    {
        return view('moradores.edit', [
            'morador' => $morador,
        ]);
    }

    public function update(UpdateMoradorRequest $request, Morador $morador): RedirectResponse
    {
        $dados = $request->validated();
        $fotos = $this->extrairFotos($request);
        unset($dados['fotografias'], $dados['fotografia']);

        $this->anexarFotos($morador, $fotos, $request->user()?->id);

        $morador->update($dados);

        return redirect()
            ->route('moradores.show', $morador)
            ->with('success', 'Morador atualizado com sucesso.');
    }

    /**
     * @return array<int, \Illuminate\Http\UploadedFile>
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
     * @param  array<int, \Illuminate\Http\UploadedFile>  $fotos
     */
    private function anexarFotos(Morador $morador, array $fotos, ?int $userId): void
    {
        foreach ($fotos as $foto) {
            $media = $morador->addMedia($foto)
                ->withCustomProperties(['uploaded_by_user_id' => $userId])
                ->toMediaCollection('fotos');

        }
    }

    public function destroy(Morador $morador): RedirectResponse
    {
        $morador->delete();

        return redirect()
            ->route('moradores.index')
            ->with('success', 'Morador removido com sucesso.');
    }
}
