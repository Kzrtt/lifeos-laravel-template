<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Citacoes\Models\Citacao;
use Modules\Core\Models\AuditLog;
use Modules\Core\Tenancy\TenantContext;
use Modules\Tenancy\Models\Entity;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    [$this->entity, $this->senha] = lifeosInstance();
});

it('CRUD completo no envelope do contrato', function () {
    $criada = rpc('lifeos-citacoes', [
        'token' => $this->senha, 'action' => 'create',
        'citacao' => ['texto' => '  A *pressa* é inimiga  ', 'autor' => ' Alguém '],
    ])->assertOk()->assertJsonPath('citacao.texto', 'A *pressa* é inimiga')->assertJsonPath('citacao.autor', 'Alguém');

    expect(array_keys($criada->json('citacao')))->toBe(['id', 'texto', 'autor', 'created_at']);
    $id = $criada->json('citacao.id');

    rpc('lifeos-citacoes', ['token' => $this->senha])
        ->assertOk()->assertJsonCount(1, 'citacoes')->assertJsonPath('citacoes.0.id', $id);

    rpc('lifeos-citacoes', ['token' => $this->senha, 'action' => 'update', 'id' => $id, 'patch' => ['autor' => 'Outro']])
        ->assertOk()->assertJsonPath('citacao.autor', 'Outro')->assertJsonPath('citacao.texto', 'A *pressa* é inimiga');

    rpc('lifeos-citacoes', ['token' => $this->senha, 'action' => 'delete', 'id' => $id])
        ->assertOk()->assertExactJson(['ok' => true, 'id' => $id]);

    rpc('lifeos-citacoes', ['token' => $this->senha])->assertJsonCount(0, 'citacoes');
});

it('devolve os mesmos códigos de erro das functions', function (array $body, int $status, string $erro) {
    rpc('lifeos-citacoes', ['token' => $this->senha, ...$body])
        ->assertStatus($status)->assertExactJson(['ok' => false, 'error' => $erro]);
})->with([
    'sem citacao' => [['action' => 'create'], 400, 'missing_citacao'],
    'texto vazio' => [['action' => 'create', 'citacao' => ['texto' => ' ', 'autor' => 'x']], 400, 'invalid_texto'],
    'autor grande' => [['action' => 'create', 'citacao' => ['texto' => 'x', 'autor' => str_repeat('a', 201)]], 400, 'invalid_autor'],
    'update sem id' => [['action' => 'update', 'patch' => ['autor' => 'x']], 400, 'missing_id'],
    'update sem patch' => [['action' => 'update', 'id' => '00000000-0000-4000-8000-000000000000'], 400, 'empty_patch'],
    'update patch vazio' => [['action' => 'update', 'id' => '00000000-0000-4000-8000-000000000000', 'patch' => ['x' => 1]], 400, 'empty_patch'],
    'update inexistente' => [['action' => 'update', 'id' => '00000000-0000-4000-8000-000000000000', 'patch' => ['autor' => 'x']], 404, 'not_found'],
    'delete sem id' => [['action' => 'delete'], 400, 'missing_id'],
    'delete inexistente' => [['action' => 'delete', 'id' => 'lixo'], 404, 'not_found'],
]);

it('não vaza citações entre entidades', function () {
    Citacao::factory()->create();
    $outra = Entity::factory()->create();
    TenantContext::runAs($outra->ent_id, fn () => Citacao::factory()->count(2)->create());

    rpc('lifeos-citacoes', ['token' => $this->senha])->assertJsonCount(1, 'citacoes');
});

it('exclusão esconde da tela mas a trilha de auditoria guarda o texto', function () {
    $c = Citacao::factory()->create(['cit_texto' => 'recuperável']);

    rpc('lifeos-citacoes', ['token' => $this->senha, 'action' => 'delete', 'id' => $c->cit_id])->assertOk();

    expect(Citacao::query()->count())->toBe(0)
        ->and(Citacao::withTrashed()->count())->toBe(1);

    $audit = AuditLog::query()->where('aud_record_id', $c->cit_id)->where('aud_action', 'deleted')->first();
    expect($audit->aud_before['cit_texto'])->toBe('recuperável');
});
