<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** lifeos_manifestacoes (man_) — intenções de longo prazo; o banner mora no disco `public`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifeos_manifestacoes', function (Blueprint $table): void {
            $table->uuid('man_id')->primary();
            $table->uuid('entity_ent_id');
            $table->text('man_name');
            $table->string('man_status');
            $table->text('man_banner_url')->nullable();
            $table->text('man_descricao')->nullable();
            $table->timestamp('man_created_at')->nullable();
            $table->timestamp('man_updated_at')->nullable();
            $table->timestamp('man_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
        });
        DB::statement("alter table lifeos_manifestacoes add column man_tags text[] not null default '{}'");
    }

    public function down(): void
    {
        Schema::dropIfExists('lifeos_manifestacoes');
    }
};
