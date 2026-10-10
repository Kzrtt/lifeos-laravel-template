<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * lifeos_eventos (evt_) — calendário do hub. O projeto é de outro módulo: sem FK
 * de banco; ao excluir o projeto o evento fica sem ele (evento ProjetoExcluido).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifeos_eventos', function (Blueprint $table): void {
            $table->uuid('evt_id')->primary();
            $table->uuid('entity_ent_id');
            $table->uuid('projeto_prj_id')->nullable();
            $table->text('evt_name');
            $table->date('evt_date');
            $table->date('evt_date_fim')->nullable();
            $table->string('evt_tipo');
            $table->timestamp('evt_created_at')->nullable();
            $table->timestamp('evt_updated_at')->nullable();
            $table->timestamp('evt_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
            $table->index(['entity_ent_id', 'evt_date']);
            $table->index('projeto_prj_id');
        });
        DB::statement('alter table lifeos_eventos add constraint lifeos_eventos_date_fim_check check (evt_date_fim is null or evt_date_fim >= evt_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('lifeos_eventos');
    }
};
