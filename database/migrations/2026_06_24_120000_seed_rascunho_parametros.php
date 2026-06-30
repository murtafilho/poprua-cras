<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $params = [
            [
                'chave' => 'rascunho_debounce_ms',
                'valor' => '5000',
                'tipo' => 'integer',
                'grupo' => 'workflow',
                'descricao' => 'Intervalo de autosave do rascunho de zeladoria (milissegundos)',
            ],
            [
                'chave' => 'rascunho_dias_expiracao',
                'valor' => '30',
                'tipo' => 'integer',
                'grupo' => 'workflow',
                'descricao' => 'Dias sem atualização para expirar rascunhos (job rascunhos:limpar)',
            ],
        ];

        foreach ($params as $param) {
            $exists = DB::table('parametros')->where('chave', $param['chave'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('parametros')->insert(array_merge($param, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        DB::table('parametros')->whereIn('chave', [
            'rascunho_debounce_ms',
            'rascunho_dias_expiracao',
        ])->delete();
    }
};
