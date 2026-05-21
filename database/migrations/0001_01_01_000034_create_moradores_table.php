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
        Schema::create('moradores', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('ponto_atual_id')->nullable()->index();
            $table->string('nome_social');
            $table->string('nome_registro')->nullable();
            $table->string('apelido')->nullable();
            $table->string('genero')->nullable();
            $table->text('observacoes')->nullable();
            $table->string('documento')->nullable();
            $table->string('contato')->nullable();
            $table->string('fotografia')->nullable()->comment('Caminho da foto');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('moradores');
    }
};
