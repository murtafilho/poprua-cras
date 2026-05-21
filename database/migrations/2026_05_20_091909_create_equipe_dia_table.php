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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('equipe_dia');
    }
};
