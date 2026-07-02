<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('parametros')
            ->where('chave', 'app_nome')
            ->whereIn('valor', ['POPRUA CRAS', 'PopRua CRAS', 'SIZEM BH'])
            ->update([
                'valor' => 'SIZEM',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('parametros')
            ->where('chave', 'app_nome')
            ->where('valor', 'SIZEM')
            ->update([
                'valor' => 'POPRUA CRAS',
                'updated_at' => now(),
            ]);
    }
};
