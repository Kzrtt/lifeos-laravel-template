<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * audit_logs — trilha de auditoria imutável (append-only), gravada pelo
 * AuditObserver do core em created/updated/deleted/restored de todo model
 * transacional. Nunca sofre UPDATE nem DELETE.
 *
 * Sem FKs de propósito: a auditoria precisa sobreviver ao registro que auditou
 * (o diff permanece mesmo se o tenant/registro for apagado fisicamente um dia).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->uuid('aud_id')->primary();
            $table->uuid('entity_ent_id')->nullable()->index();
            $table->string('aud_user_id')->nullable();
            $table->string('aud_table');
            $table->string('aud_record_id');
            $table->string('aud_action'); // created | updated | deleted | restored
            $table->json('aud_before')->nullable();
            $table->json('aud_after')->nullable();
            $table->timestamp('aud_created_at')->useCurrent();

            $table->index(['aud_table', 'aud_record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
