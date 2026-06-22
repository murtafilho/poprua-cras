<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabela do spatie/laravel-activitylog (v4).
 *
 * Esquema final do pacote (consolida create + add_event_column + add_batch_uuid_column),
 * escrito a mao porque o container nao roda `vendor:publish`. Nome da tabela e colunas
 * batem com o config padrao (config/activitylog.php -> table_name = 'activity_log').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();

            $table->index('log_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
