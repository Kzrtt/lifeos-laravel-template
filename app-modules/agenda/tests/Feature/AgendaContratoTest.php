<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Models\Evento;
use Modules\Projetos\Models\Projeto;
use Modules\Vocabularios\Services\VocabularioService;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    [$this->entity, $this->senha] = lifeosInstance();
    app(VocabularioService::class)->semear($this->entity->ent_id);
});

it('CRUD e chaves do evento', function () {
    $p = Projeto::factory()->create();
    $c = rpc('lifeos-eventos', ['token' => $this->senha, 'action' => 'create', 'evento' => [
        'name' => ' Prova ', 'date' => '2026-10-20', 'date_fim' => '2026-10-22', 'tipo' => 'faculdade', 'projeto_id' => $p->prj_id,
    ]])->assertOk();
    expect(array_keys($c->json('evento')))->toBe(['id', 'name', 'date', 'date_fim', 'tipo', 'projeto_id'])
        ->and($c->json('evento.name'))->toBe('Prova');

    $id = $c->json('evento.id');
    rpc('lifeos-eventos', ['token' => $this->senha, 'action' => 'update', 'id' => $id, 'patch' => ['date_fim' => '', 'projeto_id' => null]])
        ->assertOk()->assertJsonPath('evento.date_fim', null)->assertJsonPath('evento.projeto_id', null);
    rpc('lifeos-eventos', ['token' => $this->senha, 'action' => 'delete', 'id' => $id])->assertOk();
});

it('a janela inclui eventos que cruzam o intervalo', function () {
    Evento::factory()->create(['evt_name' => 'dentro', 'evt_date' => '2026-10-10']);
    Evento::factory()->create(['evt_name' => 'cruza', 'evt_date' => '2026-09-28', 'evt_date_fim' => '2026-10-03']);
    Evento::factory()->create(['evt_name' => 'antes', 'evt_date' => '2026-09-01']);
    Evento::factory()->create(['evt_name' => 'depois', 'evt_date' => '2026-11-01']);

    $nomes = rpc('lifeos-eventos', ['token' => $this->senha, 'from' => '2026-10-01', 'to' => '2026-10-31'])->json('eventos.*.name');
    expect($nomes)->toBe(['cruza', 'dentro']);

    rpc('lifeos-eventos', ['token' => $this->senha])->assertStatus(400)->assertJsonPath('error', 'invalid_range');
});

it('mesmos códigos de erro', function (array $evento, string $erro) {
    rpc('lifeos-eventos', ['token' => $this->senha, 'action' => 'create', 'evento' => [...['name' => 'a', 'date' => '2026-10-10', 'tipo' => 'vida'], ...$evento]])
        ->assertStatus(400)->assertJsonPath('error', $erro);
})->with([
    [['name' => ''], 'invalid_name'],
    [['date' => '10/10/2026'], 'invalid_date'],
    [['date' => '2026-02-31'], 'invalid_date'],
    [['date_fim' => 'x'], 'invalid_date_fim'],
    [['date_fim' => '2026-10-09'], 'date_fim_antes_de_date'],
    [['tipo' => 'nada'], 'invalid_tipo'],
    [['projeto_id' => '00000000-0000-4000-8000-000000000000'], 'invalid_projeto_id'],
]);

it('update valida date_fim contra a data atual do banco', function () {
    $e = Evento::factory()->create(['evt_date' => '2026-10-10']);
    rpc('lifeos-eventos', ['token' => $this->senha, 'action' => 'update', 'id' => $e->evt_id, 'patch' => ['date_fim' => '2026-10-01']])
        ->assertStatus(400)->assertJsonPath('error', 'date_fim_antes_de_date');
});

it('excluir projeto mantém o evento, sem projeto', function () {
    $p = Projeto::factory()->create();
    $e = Evento::factory()->create(['projeto_prj_id' => $p->prj_id]);

    rpc('lifeos-projetos', ['token' => $this->senha, 'action' => 'delete', 'id' => $p->prj_id])->assertOk();

    expect($e->fresh()->projeto_prj_id)->toBeNull();
});
