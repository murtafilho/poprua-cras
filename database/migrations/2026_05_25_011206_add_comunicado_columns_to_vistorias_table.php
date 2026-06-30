<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MIGRATION LOCAL — adiciona colunas que o commit 013efd9 ("feat: identidade
 * PBH, workflow zeladoria, parametrização e preparação pre-launch") referencia
 * em VistoriaService, VistoriaController, UpdateVistoriaRequest, e nas views
 * vistorias/{create,edit,show,index}.blade.php — mas cuja migration ficou
 * de fora do commit. Quando o autor (murtafilho) empurrar a migration oficial,
 * remover ESTE arquivo antes do próximo pull para evitar duplicação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            if (! Schema::hasColumn('vistorias', 'houve_comunicado')) {
                $table->boolean('houve_comunicado')->default(false)->after('houve_lavratura');
            }
            if (! Schema::hasColumn('vistorias', 'data_comunicado')) {
                $table->date('data_comunicado')->nullable()->after('houve_comunicado');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->dropColumn(['houve_comunicado', 'data_comunicado']);
        });
    }
};
