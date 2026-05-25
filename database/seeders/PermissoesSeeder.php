<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissoesSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissoes = [
            'ver vistorias',
            'criar vistorias',
            'editar vistorias proprias',
            'editar qualquer vistoria',
            'excluir vistorias',
            'reativar vistorias',
            'cancelar vistorias',
            'ver pontos',
            'criar pontos',
            'editar pontos',
            'excluir pontos',
            'ver usuarios',
            'criar usuarios',
            'editar usuarios',
            'excluir usuarios',
            'gerenciar roles',
            'ver configuracoes',
            'editar configuracoes',
            'ver mapa',
            'ver relatorios',
            'exportar dados',
            'gerenciar parametros',
            'participar de equipes vistoria',
        ];

        foreach ($permissoes as $nome) {
            Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions($permissoes);

        $supervisor = Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        $supervisor->syncPermissions([
            'ver vistorias', 'criar vistorias', 'editar vistorias proprias',
            'ver pontos', 'criar pontos', 'editar pontos',
            'ver mapa', 'ver relatorios',
            'participar de equipes vistoria',
        ]);

        $coordenador = Role::firstOrCreate(['name' => 'coordenador', 'guard_name' => 'web']);
        $coordenador->syncPermissions([
            'ver vistorias', 'criar vistorias', 'editar vistorias proprias', 'editar qualquer vistoria',
            'ver pontos', 'criar pontos', 'editar pontos',
            'ver usuarios', 'ver mapa', 'ver relatorios', 'exportar dados',
            'participar de equipes vistoria',
        ]);

        $agente = Role::firstOrCreate(['name' => 'agente', 'guard_name' => 'web']);
        $agente->syncPermissions([
            'ver vistorias', 'criar vistorias', 'editar vistorias proprias',
            'ver pontos', 'criar pontos',
            'ver mapa',
            'participar de equipes vistoria',
        ]);

        $agentesCampo = Role::firstOrCreate(['name' => 'agentes-campo', 'guard_name' => 'web']);
        $agentesCampo->syncPermissions([
            'ver vistorias', 'criar vistorias', 'editar vistorias proprias',
            'ver pontos', 'criar pontos',
            'ver mapa',
            'participar de equipes vistoria',
        ]);

        $agentesSlu = Role::firstOrCreate(['name' => 'agentes-slu', 'guard_name' => 'web']);
        $agentesSlu->syncPermissions([
            'ver vistorias', 'criar vistorias', 'editar vistorias proprias',
            'ver pontos', 'ver mapa',
            'participar de equipes vistoria',
        ]);

        $guardas = Role::firstOrCreate(['name' => 'guardas-municipais', 'guard_name' => 'web']);
        $guardas->syncPermissions([
            'ver vistorias', 'ver pontos', 'ver mapa', 'ver relatorios',
        ]);

        $this->command->info('23 permissões + 7 roles criadas/atualizadas.');
    }
}
