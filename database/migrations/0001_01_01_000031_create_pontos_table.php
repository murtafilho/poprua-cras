<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pontos', function (Blueprint $table) {
            $table->increments('id');
            $table->string('numero', 10)->nullable();
            $table->integer('caracteristica_abrigo_id')->nullable();
            $table->string('complemento', 120)->nullable();
            $table->unsignedBigInteger('endereco_atualizado_id')->nullable()->index('idx_pontos_endereco_atualizado');
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->decimal('lat', 17, 14)->nullable();
            $table->decimal('lng', 17, 14)->nullable();
        });

        // Adicionar coluna PostGIS geometry
        DB::statement("SELECT AddGeometryColumn('public', 'pontos', 'geom', 4326, 'POINT', 2)");
        DB::statement('CREATE INDEX idx_pontos_geom ON pontos USING GIST(geom)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pontos');
    }
};
