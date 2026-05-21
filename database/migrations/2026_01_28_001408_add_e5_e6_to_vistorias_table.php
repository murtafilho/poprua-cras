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
        Schema::table('vistorias', function (Blueprint $table) {
            $table->unsignedInteger('e5_id')->nullable()->after('e4_id');
            $table->unsignedInteger('e6_id')->nullable()->after('e5_id');

            $table->foreign('e5_id')->references('id')->on('encaminhamentos')->nullOnDelete();
            $table->foreign('e6_id')->references('id')->on('encaminhamentos')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->dropForeign(['e5_id']);
            $table->dropForeign(['e6_id']);
            $table->dropColumn(['e5_id', 'e6_id']);
        });
    }
};
