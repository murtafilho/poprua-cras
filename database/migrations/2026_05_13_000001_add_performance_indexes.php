<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->index('data_abordagem');
            $table->index('deleted_at');
            $table->index('resultado_acao_id');
        });

        Schema::table('pontos', function (Blueprint $table) {
            $table->index('caracteristica_abrigo_id');
        });

        Schema::table('endereco_atualizados', function (Blueprint $table) {
            $table->index('NOME_BAIRRO_POPULAR');
        });

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ea_logradouro_trgm ON endereco_atualizados USING GIN ("NOME_LOGRADOURO" gin_trgm_ops)');
    }

    public function down(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->dropIndex(['data_abordagem']);
            $table->dropIndex(['deleted_at']);
            $table->dropIndex(['resultado_acao_id']);
        });

        Schema::table('pontos', function (Blueprint $table) {
            $table->dropIndex(['caracteristica_abrigo_id']);
        });

        Schema::table('endereco_atualizados', function (Blueprint $table) {
            $table->dropIndex(['NOME_BAIRRO_POPULAR']);
        });

        DB::statement('DROP INDEX IF EXISTS idx_ea_logradouro_trgm');
    }
};
