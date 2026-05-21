<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class MatrizPermissoesController extends Controller
{
    public function __invoke(): View
    {
        $roles = Role::with('permissions')->orderBy('id')->get();

        $grupos = [
            'Vistorias' => [
                'ver vistorias',
                'criar vistorias',
                'editar vistorias proprias',
                'editar qualquer vistoria',
                'excluir vistorias',
                'reativar vistorias',
                'cancelar vistorias',
            ],
            'Pontos' => [
                'ver pontos',
                'criar pontos',
                'editar pontos',
                'excluir pontos',
            ],
            'Usuarios' => [
                'ver usuarios',
                'criar usuarios',
                'editar usuarios',
                'excluir usuarios',
                'gerenciar roles',
            ],
            'Sistema' => [
                'ver configuracoes',
                'editar configuracoes',
                'ver mapa',
                'ver relatorios',
                'exportar dados',
            ],
        ];

        // Pre-indexa permissoes por role para lookup O(1) na view
        $matrix = [];
        foreach ($roles as $role) {
            $matrix[$role->name] = $role->permissions->pluck('name')->flip()->toArray();
        }

        // Regras de negocio da VistoriaPolicy (nao expressas como permissao Spatie)
        $regrasNegocio = [
            [
                'acao' => 'Editar vistoria',
                'condicao' => 'Aberta (nao finalizada e nao cancelada)',
                'regra' => 'Apenas o dono da vistoria',
                'admin' => false,
            ],
            [
                'acao' => 'Finalizar vistoria',
                'condicao' => 'Aberta',
                'regra' => 'Apenas o dono da vistoria',
                'admin' => false,
            ],
            [
                'acao' => 'Salvar e Finalizar (no formulario)',
                'condicao' => 'Aberta',
                'regra' => 'Apenas o dono da vistoria',
                'admin' => false,
            ],
            [
                'acao' => 'Reativar vistoria',
                'condicao' => 'Finalizada (nao cancelada)',
                'regra' => 'Permissao: reativar vistorias',
                'admin' => true,
            ],
            [
                'acao' => 'Cancelar vistoria',
                'condicao' => 'Aberta',
                'regra' => 'Apenas o dono da vistoria',
                'admin' => false,
            ],
            [
                'acao' => 'Cancelar vistoria',
                'condicao' => 'Finalizada',
                'regra' => 'Permissao: cancelar vistorias',
                'admin' => true,
            ],
            [
                'acao' => 'Complementar vistoria',
                'condicao' => 'Finalizada',
                'regra' => 'Qualquer usuario autenticado',
                'admin' => false,
            ],
        ];

        return view('admin.matriz.index', [
            'roles' => $roles,
            'grupos' => $grupos,
            'matrix' => $matrix,
            'regrasNegocio' => $regrasNegocio,
        ]);
    }
}
