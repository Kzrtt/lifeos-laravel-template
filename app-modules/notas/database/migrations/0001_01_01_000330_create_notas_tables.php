<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * lifeos_notas (not_) e o vínculo N:N com projetos (lifeos_notas_projetos,
 * npj_). O projeto é de outro módulo: `projeto_prj_id` não tem FK de banco
 * para a tabela dele (fronteira de módulo); a existência é validada pelo
 * ProjetoReadModel e o vínculo some quando o projeto é excluído (evento).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifeos_notas', function (Blueprint $table): void {
            $table->uuid('not_id')->primary();
            $table->uuid('entity_ent_id');
            $table->text('not_name');
            $table->date('not_data')->nullable();
            $table->text('not_conteudo_md')->nullable();
            $table->timestamp('not_created_at')->nullable();
            $table->timestamp('not_updated_at')->nullable();
            $table->timestamp('not_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
        });
        DB::statement("alter table lifeos_notas add column not_tipo text[] not null default '{}'");

        Schema::create('lifeos_notas_projetos', function (Blueprint $table): void {
            $table->uuid('npj_id')->primary();
            $table->uuid('entity_ent_id');
            $table->uuid('nota_not_id');
            $table->uuid('projeto_prj_id');
            $table->timestamp('npj_created_at')->nullable();
            $table->timestamp('npj_updated_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
            $table->foreign('nota_not_id')->references('not_id')->on('lifeos_notas')->cascadeOnDelete();
            $table->unique(['nota_not_id', 'projeto_prj_id']);
            $table->index('projeto_prj_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifeos_notas_projetos');
        Schema::dropIfExists('lifeos_notas');
    }
};
