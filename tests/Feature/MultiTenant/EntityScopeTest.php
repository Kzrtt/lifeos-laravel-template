<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Citacoes\Models\Citacao;
use Modules\Core\Scopes\EntityScope;
use Modules\Core\Tenancy\TenantContext;
use Modules\Tenancy\Models\Entity;

uses(RefreshDatabase::class);

it('queries retornam apenas dados do tenant atual', function () {
    [$entityA, $entityB] = Entity::factory()->count(2)->create();

    TenantContext::set($entityA->ent_id);
    Citacao::factory()->create();

    TenantContext::set($entityB->ent_id);
    $citacaoB = Citacao::factory()->create();

    TenantContext::set($entityA->ent_id);
    expect(Citacao::query()->find($citacaoB->cit_id))->toBeNull()
        ->and(Citacao::query()->count())->toBe(1);
});

it('create preenche entity_ent_id automaticamente a partir do contexto', function () {
    $entity = tenantContext();

    $citacao = Citacao::factory()->create();

    expect($citacao->entity_ent_id)->toBe($entity->ent_id);
});

it('find por ID de outro tenant retorna null', function () {
    [$entityA, $entityB] = Entity::factory()->count(2)->create();

    TenantContext::set($entityA->ent_id);
    $citacao = Citacao::factory()->create();

    TenantContext::set($entityB->ent_id);
    expect(Citacao::query()->find($citacao->cit_id))->toBeNull();
});

it('withoutGlobalScope permite acesso administrativo deliberado a todos os tenants', function () {
    [$entityA, $entityB] = Entity::factory()->count(2)->create();

    TenantContext::set($entityA->ent_id);
    Citacao::factory()->count(3)->create();

    TenantContext::set($entityB->ent_id);
    Citacao::factory()->count(2)->create();

    $total = Citacao::withoutGlobalScope(EntityScope::class)->count();

    expect($total)->toBe(5);
});

it('withoutEntityScope executa o bloco sem filtro e restaura o contexto', function () {
    [$entityA, $entityB] = Entity::factory()->count(2)->create();

    TenantContext::set($entityA->ent_id);
    Citacao::factory()->create();

    TenantContext::set($entityB->ent_id);
    Citacao::factory()->create();

    $total = Citacao::withoutEntityScope(fn (): int => Citacao::query()->count());

    expect($total)->toBe(2)
        ->and(TenantContext::id())->toBe($entityB->ent_id);
});
