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
        Schema::table('moradores', function (Blueprint $table) {
            $table->foreign(['ponto_atual_id'])->references(['id'])->on('pontos')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moradores', function (Blueprint $table) {
            $table->dropForeign('moradores_ponto_atual_id_foreign');
        });
    }
};
