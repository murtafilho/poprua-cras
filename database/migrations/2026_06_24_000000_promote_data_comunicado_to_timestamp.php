<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Data de Entrega" do comunicado passa a registrar data + hora (data/hora
     * corrente no momento do preenchimento). Promove a coluna date -> timestamp,
     * mesmo padrao usado em data_prevista_zeladoria (2026_05_20_240000).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE vistorias ALTER COLUMN data_comunicado TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING data_comunicado::timestamp');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE vistorias ALTER COLUMN data_comunicado TYPE DATE USING data_comunicado::date');
    }
};
