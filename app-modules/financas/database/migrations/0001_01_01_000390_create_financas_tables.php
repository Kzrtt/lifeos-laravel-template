<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * lifeos_movimentacoes (mov_) e lifeos_recorrencias (rec_). `mov_tipo` mistura
 * direção (Entrada/Saida) e meio (Crédito, Débito, Pix...) no mesmo array —
 * herdado da migração do Notion; o saldo depende desse formato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifeos_movimentacoes', function (Blueprint $table): void {
            $table->uuid('mov_id')->primary();
            $table->uuid('entity_ent_id');
            $table->text('mov_name');
            $table->decimal('mov_valor', 12, 2);
            $table->date('mov_date');
            $table->timestamp('mov_created_at')->nullable();
            $table->timestamp('mov_updated_at')->nullable();
            $table->timestamp('mov_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
            $table->index(['entity_ent_id', 'mov_date']);
        });
        DB::statement('alter table lifeos_movimentacoes add column mov_tipo text[] not null');
        DB::statement('alter table lifeos_movimentacoes add constraint lifeos_movimentacoes_valor_check check (mov_valor >= 0)');

        Schema::create('lifeos_recorrencias', function (Blueprint $table): void {
            $table->uuid('rec_id')->primary();
            $table->uuid('entity_ent_id');
            $table->text('rec_nome');
            $table->string('rec_direcao');
            $table->decimal('rec_valor_min', 12, 2);
            $table->decimal('rec_valor_max', 12, 2);
            $table->string('rec_meio')->nullable();
            $table->smallInteger('rec_dia')->nullable();
            $table->boolean('rec_ativa')->default(true);
            $table->timestamp('rec_created_at')->nullable();
            $table->timestamp('rec_updated_at')->nullable();
            $table->timestamp('rec_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
        });
        DB::statement('alter table lifeos_recorrencias add constraint lifeos_recorrencias_check check (rec_valor_max >= rec_valor_min and rec_valor_min >= 0 and (rec_dia is null or rec_dia between 1 and 31))');
    }

    public function down(): void
    {
        Schema::dropIfExists('lifeos_recorrencias');
        Schema::dropIfExists('lifeos_movimentacoes');
    }
};
