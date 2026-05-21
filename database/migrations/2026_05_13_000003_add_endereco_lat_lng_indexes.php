<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('endereco_atualizados', function (Blueprint $table) {
            $table->index('lat');
            $table->index('lng');
        });
    }

    public function down(): void
    {
        Schema::table('endereco_atualizados', function (Blueprint $table) {
            $table->dropIndex(['lat']);
            $table->dropIndex(['lng']);
        });
    }
};
