<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Renuncias\Models\Renuncia;
use Modules\Renuncias\Models\Tentativa;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    [$this->entity, $this->senha] = lifeosInstance();
    $this->criar = fn (array $r) => rpc('lifeos-renuncias', ['token' => $this->senha, 'action' => 'create', 'renuncia' => $r]);
});

it('cria e lista na ordem de "desde", com tentativas vazias', function () {
    $a = ($this->criar)(['nome' => ' Cigarro ', 'emoji' => '🚭', 'desde' => now()->subDays(10)->toIso8601String()])->assertOk();
    expect(array_keys($a->json('renuncia')))->toBe(['id', 'nome', 'emoji', 'desde', 'arquivada', 'created_at', 'updated_at', 'tentativas'])
        ->and($a->json('renuncia.nome'))->toBe('Cigarro')->and($a->json('renuncia.tentativas'))->toBe([]);

    ($this->criar)(['nome' => 'Açúcar', 'emoji' => '🍬', 'desde' => now()->subDays(20)->toIso8601String()])->assertOk();
    rpc('lifeos-renuncias', ['token' => $this->senha])->assertJsonPath('renuncias.0.nome', 'Açúcar');
});

it('"desde" no futuro próximo vira agora; distante é recusado', function () {
    $r = ($this->criar)(['nome' => 'a', 'emoji' => 'x', 'desde' => now()->addMinutes(2)->toIso8601String()])->assertOk();
    expect(Carbon\Carbon::parse($r->json('renuncia.desde'))->lessThanOrEqualTo(now()->addSecond()))->toBeTrue();

    ($this->criar)(['nome' => 'b', 'emoji' => 'x', 'desde' => now()->addHour()->toIso8601String()])->assertStatus(400)->assertJsonPath('error', 'invalid_desde');
    ($this->criar)(['nome' => 'b', 'emoji' => 'x', 'desde' => 'ontem'])->assertStatus(400)->assertJsonPath('error', 'invalid_desde');
});

it('nome é único sem diferenciar caixa', function () {
    ($this->criar)(['nome' => 'Café', 'emoji' => '☕', 'desde' => now()->subDay()->toIso8601String()])->assertOk();
    ($this->criar)(['nome' => ' café ', 'emoji' => '☕', 'desde' => now()->subDay()->toIso8601String()])->assertStatus(409)->assertExactJson(['ok' => false, 'error' => 'nome_duplicado']);
});

it('recaída fecha a tentativa e recomeça a contagem', function () {
    $inicio = now()->subDays(10)->startOfSecond();
    $r = Renuncia::factory()->create(['ren_desde' => $inicio]);
    $quando = now()->subDays(2)->startOfSecond();

    $res = rpc('lifeos-renuncias', ['token' => $this->senha, 'action' => 'recaida', 'id' => $r->ren_id, 'quando' => $quando->toIso8601String()])->assertOk();

    expect($res->json('renuncia.tentativas'))->toHaveCount(1)
        ->and(Carbon\Carbon::parse($res->json('renuncia.tentativas.0.inicio'))->equalTo($inicio))->toBeTrue()
        ->and(Carbon\Carbon::parse($res->json('renuncia.tentativas.0.fim'))->equalTo($quando))->toBeTrue()
        ->and(Carbon\Carbon::parse($res->json('renuncia.desde'))->equalTo($quando))->toBeTrue()
        ->and(array_keys($res->json('renuncia.tentativas.0')))->toBe(['id', 'inicio', 'fim']);
});

it('recaída: sem quando usa agora; antes do início e inexistente são erros', function () {
    $r = Renuncia::factory()->create(['ren_desde' => now()->subDay()]);

    rpc('lifeos-renuncias', ['token' => $this->senha, 'action' => 'recaida', 'id' => $r->ren_id])->assertOk();
    rpc('lifeos-renuncias', ['token' => $this->senha, 'action' => 'recaida', 'id' => $r->ren_id, 'quando' => now()->subDays(30)->toIso8601String()])
        ->assertStatus(400)->assertJsonPath('error', 'quando_antes_do_inicio');
    rpc('lifeos-renuncias', ['token' => $this->senha, 'action' => 'recaida', 'id' => '00000000-0000-4000-8000-000000000000'])
        ->assertStatus(404)->assertJsonPath('error', 'not_found');
    rpc('lifeos-renuncias', ['token' => $this->senha, 'action' => 'recaida', 'id' => $r->ren_id, 'quando' => 'xx'])
        ->assertStatus(400)->assertJsonPath('error', 'invalid_quando');
    rpc('lifeos-renuncias', ['token' => $this->senha, 'action' => 'recaida'])->assertJsonPath('error', 'missing_id');
});

it('arquivar e desarquivar (recomeça de agora)', function () {
    $r = Renuncia::factory()->create(['ren_desde' => now()->subDays(40)]);

    rpc('lifeos-renuncias', ['token' => $this->senha, 'action' => 'update', 'id' => $r->ren_id, 'patch' => ['arquivada' => true]])->assertJsonPath('renuncia.arquivada', true);
    $res = rpc('lifeos-renuncias', ['token' => $this->senha, 'action' => 'update', 'id' => $r->ren_id, 'patch' => ['arquivada' => false]])->assertOk();
    expect(Carbon\Carbon::parse($res->json('renuncia.desde'))->greaterThan(now()->subMinute()))->toBeTrue();

    rpc('lifeos-renuncias', ['token' => $this->senha, 'action' => 'update', 'id' => $r->ren_id, 'patch' => ['arquivada' => 'sim']])->assertJsonPath('error', 'invalid_arquivada');
    rpc('lifeos-renuncias', ['token' => $this->senha, 'action' => 'update', 'id' => $r->ren_id, 'patch' => ['x' => 1]])->assertJsonPath('error', 'empty_patch');
});

it('excluir leva as tentativas', function () {
    $r = Renuncia::factory()->create(['ren_desde' => now()->subDays(5)]);
    rpc('lifeos-renuncias', ['token' => $this->senha, 'action' => 'recaida', 'id' => $r->ren_id])->assertOk();
    rpc('lifeos-renuncias', ['token' => $this->senha, 'action' => 'delete', 'id' => $r->ren_id])->assertOk();

    expect(Tentativa::query()->count())->toBe(0)->and(Renuncia::query()->count())->toBe(0);
});
