<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->index(['data_abordagem', 'ponto_id'], 'idx_vistorias_data_ponto');
            $table->index('tipo_abordagem_id', 'idx_vistorias_tipo_abordagem');
            $table->index('resultado_acao_id', 'idx_vistorias_resultado');
            $table->index('data_prevista_zeladoria', 'idx_vistorias_data_prevista');
            $table->index(['finalizada', 'cancelada', 'data_abordagem'], 'idx_vistorias_status_data');
        });

        Schema::table('pontos', function (Blueprint $table) {
            if (! $this->hasIndex('pontos', 'idx_pontos_id_vistorias')) {
                $table->index('id', 'idx_pontos_id_vistorias');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->dropIndex('idx_vistorias_data_ponto');
            $table->dropIndex('idx_vistorias_tipo_abordagem');
            $table->dropIndex('idx_vistorias_resultado');
            $table->dropIndex('idx_vistorias_data_prevista');
            $table->dropIndex('idx_vistorias_status_data');
        });

        Schema::table('pontos', function (Blueprint $table) {
            if ($this->hasIndex('pontos', 'idx_pontos_id_vistorias')) {
                $table->dropIndex('idx_pontos_id_vistorias');
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $indexes = DB::select('SELECT indexname FROM pg_indexes WHERE tablename = ?', [$table]);

        return collect($indexes)->contains('indexname', $index);
    }
};
