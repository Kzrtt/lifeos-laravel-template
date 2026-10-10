<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * lifeos_memorias (mem_) e lifeos_memoria_registros (mrg_). Título único por
 * instância, sem diferenciar caixa e espaço, ignorando apagadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifeos_memorias', function (Blueprint $table): void {
            $table->uuid('mem_id')->primary();
            $table->uuid('entity_ent_id');
            $table->text('mem_titulo');
            $table->text('mem_descricao')->default('');
            $table->string('mem_categoria');
            $table->timestamp('mem_created_at')->nullable();
            $table->timestamp('mem_updated_at')->nullable();
            $table->timestamp('mem_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
        });
        DB::statement('create unique index lifeos_memorias_titulo_uidx on lifeos_memorias (entity_ent_id, lower(btrim(mem_titulo))) where mem_deleted_at is null');

        Schema::create('lifeos_memoria_registros', function (Blueprint $table): void {
            $table->uuid('mrg_id')->primary();
            $table->uuid('entity_ent_id');
            $table->uuid('memoria_mem_id');
            $table->text('mrg_texto');
            $table->text('mrg_origem')->nullable();
            $table->timestamp('mrg_created_at')->nullable();
            $table->timestamp('mrg_updated_at')->nullable();
            $table->timestamp('mrg_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
            $table->foreign('memoria_mem_id')->references('mem_id')->on('lifeos_memorias')->cascadeOnDelete();
            $table->index(['memoria_mem_id', 'mrg_created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifeos_memoria_registros');
        Schema::dropIfExists('lifeos_memorias');
    }
};
