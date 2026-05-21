<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('cloud_disk')->nullable()->after('disk');
            $table->string('cloud_path')->nullable()->after('cloud_disk');
            $table->string('cloud_status')->default('pending')->after('cloud_path');
            $table->timestamp('cloud_synced_at')->nullable()->after('cloud_status');

            $table->index('cloud_status');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['cloud_status']);
            $table->dropColumn(['cloud_disk', 'cloud_path', 'cloud_status', 'cloud_synced_at']);
        });
    }
};
