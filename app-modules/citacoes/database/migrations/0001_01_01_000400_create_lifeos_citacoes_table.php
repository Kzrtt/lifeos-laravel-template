<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lifeos_citacoes (prefixo cit_) — frases do banner do hub. `cit_texto` aceita
 * *asteriscos* para destaque; para o banco é texto puro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lifeos_citacoes', function (Blueprint $table): void {
            $table->uuid('cit_id')->primary();
            $table->uuid('entity_ent_id');
            $table->text('cit_texto');
            $table->text('cit_autor');
            $table->timestamp('cit_created_at')->nullable();
            $table->timestamp('cit_updated_at')->nullable();
            $table->timestamp('cit_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lifeos_citacoes');
    }
};
