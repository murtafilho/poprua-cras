<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('morador_historicos', function (Blueprint $table) {
            $table->foreign(['morador_id'], 'morador_historico_morador_id_foreign')->references(['id'])->on('moradores')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['ponto_id'], 'morador_historico_ponto_id_foreign')->references(['id'])->on('pontos')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['vistoria_entrada_id'], 'morador_historico_vistoria_entrada_id_foreign')->references(['id'])->on('vistorias')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['vistoria_saida_id'], 'morador_historico_vistoria_saida_id_foreign')->references(['id'])->on('vistorias')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('morador_historicos', function (Blueprint $table) {
            $table->dropForeign('morador_historico_morador_id_foreign');
            $table->dropForeign('morador_historico_ponto_id_foreign');
            $table->dropForeign('morador_historico_vistoria_entrada_id_foreign');
            $table->dropForeign('morador_historico_vistoria_saida_id_foreign');
        });
    }
};
