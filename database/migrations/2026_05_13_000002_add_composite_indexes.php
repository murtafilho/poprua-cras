<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->index(['ponto_id', 'deleted_at']);
            $table->index(['ponto_id', 'data_abordagem']);
        });
    }

    public function down(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->dropIndex(['ponto_id', 'deleted_at']);
            $table->dropIndex(['ponto_id', 'data_abordagem']);
        });
    }
};
