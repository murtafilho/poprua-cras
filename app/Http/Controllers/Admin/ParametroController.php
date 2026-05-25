<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parametro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ParametroController extends Controller
{
    public function index(): View
    {
        $ordemGrupos = ['geral', 'workflow', 'mapa', 'listagem', 'limites', 'complexidade'];

        $parametros = Parametro::query()
            ->orderBy('chave')
            ->get()
            ->groupBy('grupo')
            ->sortBy(fn ($items, $grupo) => array_search($grupo, $ordemGrupos) !== false ? array_search($grupo, $ordemGrupos) : 99);

        return view('admin.parametros.index', compact('parametros'));
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'parametros' => 'required|array',
            'parametros.*' => 'nullable|string|max:500',
        ]);

        foreach ($request->input('parametros') as $chave => $valor) {
            Parametro::set($chave, $valor ?? '');
        }

        return redirect()->route('admin.parametros.index')
            ->with('success', 'Parâmetros atualizados com sucesso.');
    }

    public function create(Request $request): RedirectResponse
    {
        $request->validate([
            'chave' => 'required|string|max:100|unique:parametros,chave',
            'valor' => 'nullable|string|max:500',
            'tipo' => 'required|in:string,integer,float,boolean',
            'grupo' => 'required|string|max:50',
            'descricao' => 'nullable|string|max:255',
        ]);

        Parametro::create($request->only(['chave', 'valor', 'tipo', 'grupo', 'descricao']));

        return redirect()->route('admin.parametros.index')
            ->with('success', "Parâmetro '{$request->chave}' criado.");
    }

    public function destroy(string $chave): RedirectResponse
    {
        Parametro::where('chave', $chave)->delete();
        \Cache::forget("param:{$chave}");

        return redirect()->route('admin.parametros.index')
            ->with('success', "Parâmetro '{$chave}' removido.");
    }
}
