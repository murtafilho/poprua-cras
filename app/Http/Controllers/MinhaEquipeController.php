<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class MinhaEquipeController extends Controller
{
    /**
     * Lista todos os usuários ativos (exceto o próprio) com checkbox marcando
     * quem já faz parte da equipe do usuário autenticado.
     */
    public function index(Request $request): View
    {
        Gate::authorize('create', Vistoria::class);

        $me = $request->user();

        $usuarios = User::query()
            ->where('ativo', true)
            ->permission('participar de equipes vistoria')
            ->where('id', '!=', $me->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $marcados = $me->team()->pluck('users.id')->all();

        return view('minha-equipe.index', [
            'usuarios' => $usuarios,
            'marcados' => $marcados,
        ]);
    }

    /**
     * Substitui (sync) a equipe do usuário autenticado.
     */
    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('create', Vistoria::class);

        $validated = $request->validate([
            'membros' => 'nullable|array',
            'membros.*' => 'integer|exists:users,id',
        ]);

        $me = $request->user();
        $ids = collect($validated['membros'] ?? [])
            ->filter(fn ($id) => (int) $id !== (int) $me->id)
            ->unique()
            ->values()
            ->all();

        $me->team()->sync($ids);

        return redirect()->route('minha-equipe.index')
            ->with('success', 'Sua equipe foi atualizada — '.count($ids).' '.\Illuminate\Support\Str::plural('membro', count($ids)).'.');
    }
}
