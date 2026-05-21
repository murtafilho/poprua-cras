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
        Schema::create('morador_historicos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('morador_id');
            $table->unsignedInteger('ponto_id')->index('morador_historico_ponto_id_index');
            $table->unsignedInteger('vistoria_entrada_id')->nullable()->index('morador_historico_vistoria_entrada_id_foreign');
            $table->unsignedInteger('vistoria_saida_id')->nullable()->index('morador_historico_vistoria_saida_id_foreign');
            $table->date('data_entrada');
            $table->date('data_saida')->nullable();
            $table->timestamps();

            $table->index(['morador_id', 'data_saida'], 'morador_historico_morador_id_data_saida_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('morador_historicos');
    }
};
