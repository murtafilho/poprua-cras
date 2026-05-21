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
        Schema::create('vistoria_fotos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('vistoria_id')->index('vistoria_fotos_vistoria_id_foreign');
            $table->string('caminho');
            $table->string('nome_original')->nullable();
            $table->integer('tamanho')->nullable();
            $table->string('mime_type')->nullable();
            $table->integer('ordem')->default(0);
            $table->text('descricao')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vistoria_fotos');
    }
};
