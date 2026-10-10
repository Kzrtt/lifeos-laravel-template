<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * lifeos_projetos (prj_) e lifeos_tarefas (tar_). Tarefa nunca existe sem
 * projeto: FK `projeto_prj_id` NOT NULL com restrict (a Action traduz para
 * `has_tarefas`). Os vocabulários (status, tags, tipos) são validados pela
 * aplicação contra lifeos_vocabularios, não por CHECK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifeos_projetos', function (Blueprint $table): void {
            $table->uuid('prj_id')->primary();
            $table->uuid('entity_ent_id');
            $table->text('prj_name');
            $table->text('prj_emoji')->nullable();
            $table->string('prj_status');
            $table->timestamp('prj_created_at')->nullable();
            $table->timestamp('prj_updated_at')->nullable();
            $table->timestamp('prj_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
        });
        DB::statement("alter table lifeos_projetos add column prj_tags text[] not null default '{}'");

        Schema::create('lifeos_tarefas', function (Blueprint $table): void {
            $table->uuid('tar_id')->primary();
            $table->uuid('entity_ent_id');
            $table->uuid('projeto_prj_id');
            $table->text('tar_name');
            $table->string('tar_status');
            $table->date('tar_data_entrega')->nullable();
            $table->text('tar_descricao')->nullable();
            $table->timestamp('tar_created_at')->nullable();
            $table->timestamp('tar_updated_at')->nullable();
            $table->timestamp('tar_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
            $table->foreign('projeto_prj_id')->references('prj_id')->on('lifeos_projetos')->restrictOnDelete();
            $table->index('projeto_prj_id');
        });
        DB::statement("alter table lifeos_tarefas add column tar_tipo text[] not null default '{}'");
    }

    public function down(): void
    {
        Schema::dropIfExists('lifeos_tarefas');
        Schema::dropIfExists('lifeos_projetos');
    }
};
