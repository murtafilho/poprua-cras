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
        Schema::create('parametros', function (Blueprint $table) {
            $table->string('chave', 100)->primary();
            $table->text('valor')->nullable();
            $table->string('tipo', 20)->default('string');
            $table->string('grupo', 50)->default('geral');
            $table->string('descricao', 255)->nullable();
            $table->timestamps();
        });

        DB::table('parametros')->insert([
            ['chave' => 'info_precaria_dias', 'valor' => '60', 'tipo' => 'integer', 'grupo' => 'workflow', 'descricao' => 'Dias sem vistoria para classificar ponto como Informação Precária', 'created_at' => now(), 'updated_at' => now()],
            ['chave' => 'app_nome', 'valor' => 'SIZEM', 'tipo' => 'string', 'grupo' => 'geral', 'descricao' => 'Nome exibido no sistema', 'created_at' => now(), 'updated_at' => now()],
            ['chave' => 'app_orgao', 'valor' => 'Prefeitura de Belo Horizonte', 'tipo' => 'string', 'grupo' => 'geral', 'descricao' => 'Órgão responsável', 'created_at' => now(), 'updated_at' => now()],
            ['chave' => 'mapa_centro_lat', 'valor' => '-19.9135', 'tipo' => 'float', 'grupo' => 'mapa', 'descricao' => 'Latitude central do mapa', 'created_at' => now(), 'updated_at' => now()],
            ['chave' => 'mapa_centro_lng', 'valor' => '-43.9514', 'tipo' => 'float', 'grupo' => 'mapa', 'descricao' => 'Longitude central do mapa', 'created_at' => now(), 'updated_at' => now()],
            ['chave' => 'mapa_zoom_padrao', 'valor' => '12', 'tipo' => 'integer', 'grupo' => 'mapa', 'descricao' => 'Zoom padrão do mapa', 'created_at' => now(), 'updated_at' => now()],
            ['chave' => 'vistorias_por_pagina', 'valor' => '5', 'tipo' => 'integer', 'grupo' => 'listagem', 'descricao' => 'Quantidade padrão de vistorias por página', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parametros');
    }
};
