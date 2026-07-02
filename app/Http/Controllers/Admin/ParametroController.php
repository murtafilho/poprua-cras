<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreParametroRequest;
use App\Http\Requests\Admin\UpdateParametrosRequest;
use App\Services\ParametroService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ParametroController extends Controller
{
    public function __construct(private ParametroService $parametroService) {}

    public function index(): View
    {
        $metadados = $this->parametroService->metadadosUi();

        return view('admin.parametros.index', [
            'parametros' => $this->parametroService->listarAgrupados(),
            'gruposInfo' => $metadados['grupos'],
            'contextos' => $metadados['contextos'],
        ]);
    }

    public function update(UpdateParametrosRequest $request): RedirectResponse
    {
        /** @var array<string, string|null> $parametros */
        $parametros = $request->validated('parametros');

        $this->parametroService->atualizarLote($parametros);

        return redirect()->route('admin.parametros.index')
            ->with('success', 'Parâmetros atualizados com sucesso.');
    }

    public function store(StoreParametroRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $this->parametroService->criar([
            'chave' => $validated['chave'],
            'valor' => $validated['valor'] ?? '',
            'tipo' => $validated['tipo'],
            'grupo' => $validated['grupo'],
            'descricao' => $validated['descricao'] ?? null,
        ]);

        return redirect()->route('admin.parametros.index')
            ->with('success', "Parâmetro '{$validated['chave']}' criado.");
    }

    public function destroy(string $chave): RedirectResponse
    {
        $this->parametroService->remover($chave);

        return redirect()->route('admin.parametros.index')
            ->with('success', "Parâmetro '{$chave}' removido.");
    }
}
