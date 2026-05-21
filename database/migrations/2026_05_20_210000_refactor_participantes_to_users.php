<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Refatora participantes da vistoria pra apontar pra users direto,
     * abandonando o modelo de membros_equipe separado.
     * Cria tabela user_team para "minha equipe" (auto-relação de users).
     */
    public function up(): void
    {
        // 1) Nova tabela: minha equipe (auto-relação self-ref de users)
        Schema::create('user_team', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['owner_user_id', 'member_user_id'], 'user_team_unique');
            $table->index('owner_user_id');
        });

        // 2) vistoria_participantes: troca membro_equipe_id -> user_id
        Schema::table('vistoria_participantes', function (Blueprint $table) {
            $table->dropUnique('vistoria_participantes_vistoria_id_membro_equipe_id_unique');
            $table->dropForeign(['membro_equipe_id']);
            $table->dropColumn('membro_equipe_id');
        });

        Schema::table('vistoria_participantes', function (Blueprint $table) {
            $table->foreignId('user_id')->after('vistoria_id')->constrained('users')->cascadeOnDelete();
            $table->unique(['vistoria_id', 'user_id'], 'vistoria_participantes_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vistoria_participantes', function (Blueprint $table) {
            $table->dropUnique('vistoria_participantes_unique');
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('vistoria_participantes', function (Blueprint $table) {
            $table->foreignId('membro_equipe_id')->after('vistoria_id')->constrained('membros_equipe')->cascadeOnDelete();
            $table->unique(['vistoria_id', 'membro_equipe_id'], 'vistoria_participantes_vistoria_id_membro_equipe_id_unique');
        });

        Schema::dropIfExists('user_team');
    }
};
