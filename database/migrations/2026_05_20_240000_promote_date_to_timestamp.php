<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Promove campos `date` (sem hora) para `timestamp(0)` em colunas que
     * fazem sentido carregar hora/minuto.
     *
     * Valores existentes mantêm o dia (com hora 00:00:00).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE vistorias ALTER COLUMN data_prevista_zeladoria TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING data_prevista_zeladoria::timestamp');
        DB::statement('ALTER TABLE morador_historicos ALTER COLUMN data_entrada TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING data_entrada::timestamp');
        DB::statement('ALTER TABLE morador_historicos ALTER COLUMN data_saida TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING data_saida::timestamp');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vistorias ALTER COLUMN data_prevista_zeladoria TYPE DATE USING data_prevista_zeladoria::date');
        DB::statement('ALTER TABLE morador_historicos ALTER COLUMN data_entrada TYPE DATE USING data_entrada::date');
        DB::statement('ALTER TABLE morador_historicos ALTER COLUMN data_saida TYPE DATE USING data_saida::date');
    }
};
