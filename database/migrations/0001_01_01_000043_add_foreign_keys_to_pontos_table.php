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
        Schema::table('pontos', function (Blueprint $table) {
            $table->foreign(['endereco_atualizado_id'])->references(['id'])->on('endereco_atualizados')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pontos', function (Blueprint $table) {
            $table->dropForeign('pontos_endereco_atualizado_id_foreign');
        });
    }
};
