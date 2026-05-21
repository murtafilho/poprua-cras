<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            // 1.8 Finalização de vistoria
            $table->boolean('finalizada')->default(false)->after('observacao');
            $table->timestamp('finalizada_em')->nullable()->after('finalizada');
            $table->foreignId('finalizada_por')->nullable()->after('finalizada_em')
                ->constrained('users')->nullOnDelete();

            // 1.3 Data/horário previstos para ação de zeladoria
            $table->date('data_prevista_zeladoria')->nullable()->after('data_abordagem');
            $table->string('periodo_zeladoria', 10)->nullable()->after('data_prevista_zeladoria');

            // 1.9 Lavratura e tipos de protocolo
            $table->boolean('houve_lavratura')->default(false)->after('auto_fiscalizacao_numero');
            $table->string('tipo_protocolo', 20)->nullable()->after('houve_lavratura');
        });
    }

    public function down(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->dropForeign(['finalizada_por']);
            $table->dropColumn([
                'finalizada',
                'finalizada_em',
                'finalizada_por',
                'data_prevista_zeladoria',
                'periodo_zeladoria',
                'houve_lavratura',
                'tipo_protocolo',
            ]);
        });
    }
};
