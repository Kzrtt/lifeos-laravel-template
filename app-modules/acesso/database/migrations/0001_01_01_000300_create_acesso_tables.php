<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controle de acesso do LifeOS: senhas (access_tokens, prefixo tok_), escopo de
 * páginas das senhas não-mestre (token_pages, tpg_) e segredos da instância
 * (admin_configs, cfg_).
 *
 * A senha nunca é guardada: `tok_hash` (bcrypt) + `tok_hint` (a máscara que a
 * tela de senhas já mostra) + `tok_len`. Hint e tamanho servem para achar os
 * candidatos antes do Hash::check, já que hash não se busca por igualdade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_tokens', function (Blueprint $table): void {
            $table->uuid('tok_id')->primary();
            $table->uuid('entity_ent_id');
            $table->string('tok_hash');
            $table->string('tok_hint');
            $table->unsignedSmallInteger('tok_len');
            $table->boolean('tok_is_master')->default(false);
            $table->string('tok_label')->nullable();
            $table->timestamp('tok_created_at')->nullable();
            $table->timestamp('tok_updated_at')->nullable();
            $table->timestamp('tok_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
            $table->index(['tok_len', 'tok_hint']);
        });

        Schema::create('token_pages', function (Blueprint $table): void {
            $table->uuid('tpg_id')->primary();
            $table->uuid('entity_ent_id');
            $table->uuid('access_token_tok_id');
            $table->string('tpg_page_slug');
            $table->timestamp('tpg_created_at')->nullable();
            $table->timestamp('tpg_updated_at')->nullable();
            $table->timestamp('tpg_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
            $table->foreign('access_token_tok_id')->references('tok_id')->on('access_tokens');
        });
        // Um escopo por (senha, página), ignorando concessões revogadas.
        DB::statement('create unique index token_pages_unique_grant on token_pages (access_token_tok_id, tpg_page_slug) where tpg_deleted_at is null');

        Schema::create('admin_configs', function (Blueprint $table): void {
            $table->uuid('cfg_id')->primary();
            $table->uuid('entity_ent_id');
            $table->string('cfg_key');
            $table->text('cfg_value'); // cifrado (cast `encrypted`)
            $table->timestamp('cfg_created_at')->nullable();
            $table->timestamp('cfg_updated_at')->nullable();
            $table->timestamp('cfg_deleted_at')->nullable();

            $table->foreign('entity_ent_id')->references('ent_id')->on('entities');
        });
        DB::statement('create unique index admin_configs_unique_key on admin_configs (entity_ent_id, cfg_key) where cfg_deleted_at is null');
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_configs');
        Schema::dropIfExists('token_pages');
        Schema::dropIfExists('access_tokens');
    }
};
