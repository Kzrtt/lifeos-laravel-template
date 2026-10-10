<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Citacoes\Models\Citacao;
use Modules\Core\Models\AuditLog;

uses(RefreshDatabase::class);

beforeEach(function () {
    tenantContext();
});

it('cria registro em audit_logs ao criar uma citação', function () {
    $citacao = Citacao::factory()->create();

    $audit = AuditLog::query()
        ->where('aud_table', 'lifeos_citacoes')
        ->where('aud_record_id', $citacao->cit_id)
        ->where('aud_action', 'created')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->aud_before)->toBeNull()
        ->and($audit->aud_after)->not->toBeNull()
        ->and($audit->entity_ent_id)->toBe($citacao->entity_ent_id);
});

it('registra o diff real (antes/depois) em updated', function () {
    $citacao = Citacao::factory()->create(['cit_autor' => 'Autor Velho']);

    $citacao->update(['cit_autor' => 'Autor Novo']);

    $audit = AuditLog::query()
        ->where('aud_table', 'lifeos_citacoes')
        ->where('aud_action', 'updated')
        ->latest('aud_created_at')
        ->first();

    expect($audit->aud_before['cit_autor'])->toBe('Autor Velho')
        ->and($audit->aud_after['cit_autor'])->toBe('Autor Novo');
});

it('não expõe colunas sensíveis ($hidden do observer) no diff', function () {
    app(Modules\Acesso\Services\TokenService::class)->create('senha-que-nao-pode-vazar', 'x', false);
    app(Modules\Acesso\Services\SegredoService::class)->set('mcp_token', 'token-que-nao-pode-vazar');

    $trilha = AuditLog::query()->whereIn('aud_table', ['access_tokens', 'admin_configs'])->get();

    expect($trilha)->toHaveCount(2);
    foreach ($trilha as $linha) {
        expect(json_encode($linha->aud_after))->not->toContain('tok_hash')->not->toContain('cfg_value')
            ->not->toContain('senha-que-nao-pode-vazar')->not->toContain('token-que-nao-pode-vazar');
    }
});

it('registra deleted com o estado anterior', function () {
    $citacao = Citacao::factory()->create();

    $citacao->delete();

    $audit = AuditLog::query()
        ->where('aud_table', 'lifeos_citacoes')
        ->where('aud_record_id', $citacao->cit_id)
        ->where('aud_action', 'deleted')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->aud_before)->not->toBeNull()
        ->and($audit->aud_after)->toBeNull();
});

it('update sem mudança real não gera audit_log', function () {
    $citacao = Citacao::factory()->create();
    $antes = AuditLog::query()->count();

    $citacao->update(['cit_texto' => $citacao->cit_texto]);

    expect(AuditLog::query()->count())->toBe($antes);
});

it('audit_logs registra mutações de todos os tenants (sem EntityScope)', function () {
    Citacao::factory()->create();

    tenantContext(); // troca o tenant do contexto
    Citacao::factory()->create();

    $tenants = AuditLog::query()
        ->where('aud_table', 'lifeos_citacoes')
        ->pluck('entity_ent_id')
        ->unique();

    expect($tenants)->toHaveCount(2);
});

it('conta a história completa do registro em ordem cronológica', function () {
    $citacao = Citacao::factory()->create();
    $citacao->update(['cit_texto' => 'editado']);
    $citacao->delete();

    $trilha = AuditLog::query()
        ->where('aud_table', 'lifeos_citacoes')
        ->where('aud_record_id', $citacao->cit_id)
        ->orderBy('aud_created_at')
        ->pluck('aud_action');

    expect($trilha->all())->toBe(['created', 'updated', 'deleted']);
});
