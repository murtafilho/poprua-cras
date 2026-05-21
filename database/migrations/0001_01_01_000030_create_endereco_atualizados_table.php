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
        Schema::create('endereco_atualizados', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('FID')->nullable();
            $table->string('IDEND')->nullable()->index('idx_endereco_atualizado_idend');
            $table->string('ID_EDC')->nullable();
            $table->string('ID_LOGRADOURO')->nullable();
            $table->string('SIGLA_TIPO_LOGRADOURO')->nullable();
            $table->string('DESC_TIPO_LOGRADOURO')->nullable();
            $table->string('NOME_LOGRADOURO')->nullable()->index('idx_endereco_atualizado_logradouro');
            $table->string('NUMERO_IMOVEL')->nullable()->index('idx_endereco_atualizado_numero');
            $table->string('LETRA_IMOVEL')->nullable();
            $table->string('ID_BAIRRO_POPULAR')->nullable();
            $table->string('NUM_BAIRRO_POPULAR')->nullable();
            $table->string('NOME_BAIRRO_POPULAR')->nullable();
            $table->string('ID_BAIRRO_OFICIAL')->nullable();
            $table->string('NUM_BAIRRO_OFICIAL')->nullable();
            $table->string('TIPO_BAIRRO_OFICIAL')->nullable();
            $table->string('NOME_BAIRRO_OFICIAL')->nullable();
            $table->string('ID_REGIONAL')->nullable();
            $table->string('NOME_REGIONAL')->nullable()->index('idx_endereco_atualizado_regional');
            $table->string('CEP')->nullable();
            $table->string('EXISTENCIA_NUM_LOCAL')->nullable();
            $table->string('SITUACAO_PBH')->nullable();
            $table->string('GEOMETRIA')->nullable();
            $table->decimal('lat', 18, 14)->nullable();
            $table->decimal('lng', 18, 14)->nullable();
        });

        // Adicionar coluna PostGIS geometry
        DB::statement("SELECT AddGeometryColumn('public', 'endereco_atualizados', 'geom', 4326, 'POINT', 2)");
        DB::statement('CREATE INDEX idx_endereco_atualizados_geom ON endereco_atualizados USING GIST(geom)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('endereco_atualizados');
    }
};
