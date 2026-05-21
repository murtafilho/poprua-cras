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
        Schema::create('geo_bairros', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('codigo')->index();
            $table->string('nome')->index();
            $table->decimal('area_km2', 10, 5)->nullable();
            $table->decimal('perimetro_m', 12, 3)->nullable();
            $table->json('geometry');
            $table->timestamps();
        });

        // Adicionar coluna PostGIS geometry
        DB::statement("SELECT AddGeometryColumn('public', 'geo_bairros', 'geom', 4326, 'MULTIPOLYGON', 2)");
        DB::statement('CREATE INDEX idx_geo_bairros_geom ON geo_bairros USING GIST(geom)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geo_bairros');
    }
};
