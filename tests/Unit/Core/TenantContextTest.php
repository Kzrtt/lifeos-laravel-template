<?php

declare(strict_types=1);

use Modules\Core\Tenancy\TenantContext;

// Precisa do container (session) — não toca o banco.
uses(Tests\TestCase::class);

it('devolve null quando não há tenant no contexto', function () {
    expect(TenantContext::id())->toBeNull()
        ->and(TenantContext::has())->toBeFalse();
});

it('set/forget definem e limpam o tenant atual', function () {
    TenantContext::set('tenant-a');

    expect(TenantContext::id())->toBe('tenant-a');

    TenantContext::forget();

    expect(TenantContext::has())->toBeFalse();
});

it('runAs executa no tenant informado e restaura o anterior', function () {
    TenantContext::set('tenant-a');

    $inside = TenantContext::runAs('tenant-b', fn (): ?string => TenantContext::id());

    expect($inside)->toBe('tenant-b')
        ->and(TenantContext::id())->toBe('tenant-a');
});

it('runAs restaura o contexto mesmo quando o callback lança', function () {
    TenantContext::set('tenant-a');

    try {
        TenantContext::runAs('tenant-b', function (): void {
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // esperado
    }

    expect(TenantContext::id())->toBe('tenant-a');
});

it('withoutScope executa sem tenant e restaura ao final', function () {
    TenantContext::set('tenant-a');

    $inside = TenantContext::withoutScope(fn (): bool => TenantContext::has());

    expect($inside)->toBeFalse()
        ->and(TenantContext::id())->toBe('tenant-a');
});
