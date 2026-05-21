<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membros_equipe', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('matricula', 30)->nullable();
            $table->string('email')->nullable();
            $table->enum('equipe', ['supervisores', 'coordenadores', 'gcm', 'slu', 'agentes_campo']);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index('equipe');
            $table->index('ativo');
        });

        Schema::create('vistoria_participantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vistoria_id')->constrained('vistorias')->cascadeOnDelete();
            $table->foreignId('membro_equipe_id')->constrained('membros_equipe')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['vistoria_id', 'membro_equipe_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vistoria_participantes');
        Schema::dropIfExists('membros_equipe');
    }
};
