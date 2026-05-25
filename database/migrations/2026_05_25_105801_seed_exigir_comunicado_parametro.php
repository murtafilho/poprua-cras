<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cria o parametro `exigir_comunicado` (grupo workflow) que ate entao so
 * existia como descricao na view admin/parametros — agora vira opt-in real
 * que o StoreVistoriaRequest/UpdateVistoriaRequest leem para bloquear o
 * agendamento de zeladoria sem comunicado previo.
 *
 * Default `0` (desligado) para nao mudar o comportamento de instalacoes
 * existentes sem aviso ao operador. SUFIS habilita pelo admin quando
 * decidir adotar a regra.
 */
return new class extends Migration {
    public function up(): void
    {
        $exists = DB::table('parametros')->where('chave', 'exigir_comunicado')->exists();
        if ($exists) {
            return; // idempotente
        }

        DB::table('parametros')->insert([
            'chave' => 'exigir_comunicado',
            'valor' => '0',
            'tipo' => 'boolean',
            'grupo' => 'workflow',
            'descricao' => 'Se Sim (1), o sistema impede agendar zeladoria (data_prevista_zeladoria) sem que houve_comunicado=Sim na mesma vistoria.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('parametros')->where('chave', 'exigir_comunicado')->delete();
    }
};
