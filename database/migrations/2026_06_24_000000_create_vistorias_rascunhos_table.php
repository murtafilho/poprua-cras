<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vistorias_rascunhos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ponto_id')->nullable()->constrained('pontos')->nullOnDelete();
            $table->decimal('lat', 17, 14)->nullable();
            $table->decimal('lng', 17, 14)->nullable();
            $table->string('context_key', 64);
            $table->jsonb('payload');
            $table->unsignedTinyInteger('etapa_atual')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'context_key']);
            $table->index('updated_at');
        });

        DB::statement('ALTER TABLE vistorias_rascunhos ADD CONSTRAINT vistorias_rascunhos_etapa_check CHECK (etapa_atual BETWEEN 0 AND 6)');
    }

    public function down(): void
    {
        Schema::dropIfExists('vistorias_rascunhos');
    }
};
