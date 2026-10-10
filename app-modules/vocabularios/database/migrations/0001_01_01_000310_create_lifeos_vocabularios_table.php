<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * lifeos_vocabularios (voc_) — tags e status editáveis. Única por instância e
 * ignorando apagados: (entidade, domínio, valor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifeos_vocabularios', function (Blueprint $table): void {
            $table->uuid('voc_id')->primary();
            $table->uuid('entity_ent_id');
            $table->string('voc_dominio');
            $table->text('voc_valor');
            $table->string('voc_cor')->nullable();
            $table->integer('voc_ordem')->default(0);
            $table->boolean('voc_protegido')->default(false);
            $table->timestamp('voc_created_at')->nullable();
            $table->timestamp('voc_updated_at')->nullable();
            $table->timestamp('voc_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
            $table->index(['entity_ent_id', 'voc_dominio', 'voc_ordem']);
        });
        DB::statement('create unique index lifeos_vocabularios_unique_valor on lifeos_vocabularios (entity_ent_id, voc_dominio, voc_valor) where voc_deleted_at is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('lifeos_vocabularios');
    }
};
