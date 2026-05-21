<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMembroEquipeRequest;
use App\Http\Requests\Admin\UpdateMembroEquipeRequest;
use App\Models\MembroEquipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MembroEquipeController extends Controller
{
    /** Tipos validos espelhando o enum do banco (membros_equipe.equipe). */
    public const EQUIPES = [
        'supervisores' => 'Supervisores',
        'coordenadores' => 'Coordenadores',
        'gcm' => 'GCM',
        'slu' => 'SLU',
        'agentes_campo' => 'Agentes de Campo',
    ];

    public function index(): View
    {
        $membros = MembroEquipe::query()
            ->orderBy('equipe')
            ->orderBy('nome')
            ->get()
            ->groupBy('equipe');

        return view('admin.membros-equipe.index', [
            'membrosPorEquipe' => $membros,
            'equipes' => self::EQUIPES,
        ]);
    }

    public function create(): View
    {
        return view('admin.membros-equipe.create', [
            'equipes' => self::EQUIPES,
        ]);
    }

    public function store(StoreMembroEquipeRequest $request): RedirectResponse
    {
        MembroEquipe::create($request->validated());

        return redirect()->route('admin.membros-equipe.index')
            ->with('success', 'Membro cadastrado com sucesso.');
    }

    public function edit(MembroEquipe $membros_equipe): View
    {
        return view('admin.membros-equipe.edit', [
            'membro' => $membros_equipe,
            'equipes' => self::EQUIPES,
        ]);
    }

    public function update(UpdateMembroEquipeRequest $request, MembroEquipe $membros_equipe): RedirectResponse
    {
        $membros_equipe->update($request->validated());

        return redirect()->route('admin.membros-equipe.index')
            ->with('success', 'Membro atualizado com sucesso.');
    }

    public function destroy(MembroEquipe $membros_equipe): RedirectResponse
    {
        $membros_equipe->delete();

        return redirect()->route('admin.membros-equipe.index')
            ->with('success', 'Membro removido.');
    }
}
