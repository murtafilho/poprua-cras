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
        Schema::create('vistorias', function (Blueprint $table) {
            $table->increments('id');
            $table->dateTime('data_abordagem')->nullable();
            $table->text('nomes_pessoas')->nullable();
            $table->integer('quantidade_pessoas')->nullable();
            $table->unsignedInteger('tipo_abordagem_id')->nullable();
            $table->boolean('casal')->nullable()->default(false);
            $table->unsignedInteger('qtd_casais')->default(0);
            $table->string('classificacao', 2)->nullable();
            $table->boolean('num_reduzido')->nullable()->default(false);
            $table->boolean('catador_reciclados')->nullable()->default(false);
            $table->boolean('resistencia')->nullable()->default(false);
            $table->boolean('fixacao_antiga')->nullable()->default(false);
            $table->boolean('excesso_objetos')->nullable()->default(false);
            $table->boolean('trafico_ilicitos')->nullable();
            $table->boolean('crianca_adolescente')->default(false);
            $table->boolean('idosos')->default(false);
            $table->boolean('gestante')->default(false);
            $table->boolean('lgbtqiapn')->default(false);
            $table->boolean('cena_uso_caracterizada')->default(false);
            $table->unsignedInteger('qtd_abrigos_provisorios')->default(0);
            $table->json('abrigos_tipos')->nullable();
            $table->boolean('deficiente')->nullable();
            $table->boolean('agrupamento_quimico')->nullable();
            $table->boolean('saude_mental')->nullable();
            $table->boolean('animais')->nullable();
            $table->unsignedInteger('qtd_animais')->default(0);
            $table->boolean('conducao_forcas_seguranca')->default(false);
            $table->text('conducao_forcas_observacao')->nullable();
            $table->boolean('apreensao_fiscal')->default(false);
            $table->boolean('auto_fiscalizacao_aplicado')->default(false);
            $table->string('auto_fiscalizacao_numero')->nullable();
            $table->integer('e1_id')->nullable();
            $table->integer('e2_id')->nullable();
            $table->integer('e3_id')->nullable();
            $table->integer('e4_id')->nullable();
            $table->string('material_apreendido', 100)->nullable();
            $table->string('material_descartado', 100)->nullable();
            $table->integer('tipo_abrigo_desmontado_id')->nullable();
            $table->integer('qtd_kg')->nullable();
            $table->integer('resultado_acao_id')->nullable();
            $table->string('movimento_migratorio')->nullable();
            $table->text('observacao')->nullable();
            $table->unsignedInteger('ponto_id')->nullable()->index('ponto_id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrentOnUpdate()->nullable();
            $table->timestamp('updated_at')->useCurrentOnUpdate()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vistorias');
    }
};
