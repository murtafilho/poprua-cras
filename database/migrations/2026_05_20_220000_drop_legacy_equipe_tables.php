<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove tabelas legadas substituídas pelo modelo "Minha Equipe" (user_team).
     *
     * - equipe_dia: associação por data substituída por preferência persistente
     * - membros_equipe: cadastro paralelo substituído por uso direto da tabela users
     */
    public function up(): void
    {
        Schema::dropIfExists('equipe_dia');
        Schema::dropIfExists('membros_equipe');
    }

    /**
     * Recria as tabelas vazias com a estrutura original (sem dados).
     */
    public function down(): void
    {
        Schema::create('membros_equipe', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('matricula', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('equipe');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index('ativo');
            $table->index('equipe');
        });

        Schema::create('equipe_dia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('data');
            $table->foreignId('membro_equipe_id')->constrained('membros_equipe')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'data', 'membro_equipe_id'], 'equipe_dia_unique');
            $table->index(['user_id', 'data']);
        });
    }
};
