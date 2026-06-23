<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            // 1.9 Lavação (limpeza com água) — distinta de houve_lavratura (auto formal).
            $table->boolean('houve_lavacao')->default(false)->after('houve_lavratura');
        });
    }

    public function down(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->dropColumn('houve_lavacao');
        });
    }
};
