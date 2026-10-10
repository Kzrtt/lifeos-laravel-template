<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** lifeos_views (vis_) — visões salvas. Uma padrão por tabela, POR INSTÂNCIA. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifeos_views', function (Blueprint $table): void {
            $table->uuid('vis_id')->primary();
            $table->uuid('entity_ent_id');
            $table->string('vis_tabela'); // notas | tarefas
            $table->text('vis_nome');
            $table->string('vis_modo')->default('todas'); // todas (E) | qualquer (OU)
            $table->jsonb('vis_regras')->default('[]');
            $table->integer('vis_ordem')->default(0);
            $table->boolean('vis_padrao')->default(false);
            $table->timestamp('vis_created_at')->nullable();
            $table->timestamp('vis_updated_at')->nullable();
            $table->timestamp('vis_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
            $table->index(['entity_ent_id', 'vis_tabela', 'vis_ordem']);
        });
        DB::statement('create unique index lifeos_views_padrao_uniq on lifeos_views (entity_ent_id, vis_tabela) where vis_padrao and vis_deleted_at is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('lifeos_views');
    }
};
