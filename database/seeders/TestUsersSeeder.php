<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissoes novas exigidas pelas regras de negocio de vistoria
        $novasPermissoes = ['reativar vistorias', 'cancelar vistorias'];
        foreach ($novasPermissoes as $nome) {
            Permission::firstOrCreate(['name' => $nome, 'guard_name' => 'web']);
        }

        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $adminRole->givePermissionTo($novasPermissoes);
        }

        // Usuarios teste — um por tipo de role
        // Senha padrao: Cras@2026
        $usuarios = [
            [
                'name' => 'Teste Admin',
                'email' => 'admin@teste.local',
                'role' => 'admin',
            ],
            [
                'name' => 'Teste Supervisor',
                'email' => 'supervisor@teste.local',
                'role' => 'supervisor',
            ],
            [
                'name' => 'Teste Coordenador',
                'email' => 'coordenador@teste.local',
                'role' => 'coordenador',
            ],
            [
                'name' => 'Teste Guarda Municipal',
                'email' => 'guarda@teste.local',
                'role' => 'guardas-municipais',
            ],
            [
                'name' => 'Teste Agente SLU',
                'email' => 'agente.slu@teste.local',
                'role' => 'agentes-slu',
            ],
            [
                'name' => 'Teste Agente de Campo',
                'email' => 'agente.campo@teste.local',
                'role' => 'agentes-campo',
            ],
            [
                'name' => 'Teste Sem Role',
                'email' => 'sem.role@teste.local',
                'role' => null,
            ],
        ];

        foreach ($usuarios as $dados) {
            $user = User::firstOrCreate(
                ['email' => $dados['email']],
                [
                    'name' => $dados['name'],
                    'password' => Hash::make('Cras@2026'),
                    'email_verified_at' => now(),
                ]
            );

            // Sincroniza role (nao acumula em re-runs)
            $user->syncRoles($dados['role'] ? [$dados['role']] : []);

            $this->command->info("  {$dados['email']} [{$dados['role']}]");
        }
    }
}
