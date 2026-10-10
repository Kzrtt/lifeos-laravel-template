<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** lifeos_renuncias (ren_) e lifeos_renuncia_tentativas (rtn_). Nome único por instância (sem caixa/espaço), ignorando apagadas. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifeos_renuncias', function (Blueprint $table): void {
            $table->uuid('ren_id')->primary();
            $table->uuid('entity_ent_id');
            $table->text('ren_nome');
            $table->text('ren_emoji');
            $table->timestamp('ren_desde');
            $table->boolean('ren_arquivada')->default(false);
            $table->timestamp('ren_created_at')->nullable();
            $table->timestamp('ren_updated_at')->nullable();
            $table->timestamp('ren_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
        });
        DB::statement('create unique index lifeos_renuncias_nome_uidx on lifeos_renuncias (entity_ent_id, lower(btrim(ren_nome))) where ren_deleted_at is null');

        Schema::create('lifeos_renuncia_tentativas', function (Blueprint $table): void {
            $table->uuid('rtn_id')->primary();
            $table->uuid('entity_ent_id');
            $table->uuid('renuncia_ren_id');
            $table->timestamp('rtn_inicio');
            $table->timestamp('rtn_fim');
            $table->timestamp('rtn_created_at')->nullable();
            $table->timestamp('rtn_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
            $table->foreign('renuncia_ren_id')->references('ren_id')->on('lifeos_renuncias')->cascadeOnDelete();
            $table->index(['renuncia_ren_id', 'rtn_fim']);
        });
        DB::statement('alter table lifeos_renuncia_tentativas add constraint lifeos_renuncia_tentativas_check check (rtn_fim >= rtn_inicio)');
    }

    public function down(): void
    {
        Schema::dropIfExists('lifeos_renuncia_tentativas');
        Schema::dropIfExists('lifeos_renuncias');
    }
};
