<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->boolean('cancelada')->default(false)->after('finalizada_por');
            $table->timestamp('cancelada_em')->nullable()->after('cancelada');
            $table->foreignId('cancelada_por')->nullable()->after('cancelada_em')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->dropForeign(['cancelada_por']);
            $table->dropColumn(['cancelada', 'cancelada_em', 'cancelada_por']);
        });
    }
};
