<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    [$this->entity, $this->senha] = lifeosInstance();
    $this->criar = fn (array $view) => rpc('lifeos-views', ['token' => $this->senha, 'action' => 'create', 'view' => $view]);
});

it('cria, lista por tabela e ordena', function () {
    $a = ($this->criar)(['tabela' => 'notas', 'nome' => 'Vida', 'regras' => [['campo' => 'tipo', 'operador' => 'incluir', 'valores' => ['Vida']]]])->assertOk();
    ($this->criar)(['tabela' => 'notas', 'nome' => 'Outra', 'regras' => []])->assertJsonPath('view.ordem', 1);
    ($this->criar)(['tabela' => 'tarefas', 'nome' => 'T', 'modo' => 'qualquer', 'regras' => [['campo' => 'status', 'operador' => 'excluir', 'valores' => ['Feito']]]])->assertOk();

    expect(array_keys($a->json('view')))->toBe(['id', 'tabela', 'nome', 'modo', 'regras', 'ordem', 'padrao', 'created_at', 'updated_at'])
        ->and($a->json('view.modo'))->toBe('todas')->and($a->json('view.ordem'))->toBe(0);

    rpc('lifeos-views', ['token' => $this->senha, 'tabela' => 'notas'])->assertJsonCount(2, 'views')->assertJsonPath('views.0.nome', 'Vida');
    rpc('lifeos-views', ['token' => $this->senha])->assertStatus(400)->assertJsonPath('error', 'invalid_tabela');
});

it('mantém uma só padrão por tabela', function () {
    $a = ($this->criar)(['tabela' => 'notas', 'nome' => 'A', 'regras' => [], 'padrao' => true])->json('view.id');
    $b = ($this->criar)(['tabela' => 'notas', 'nome' => 'B', 'regras' => [], 'padrao' => true])->json('view.id');
    ($this->criar)(['tabela' => 'tarefas', 'nome' => 'C', 'regras' => [], 'padrao' => true])->assertOk();

    $views = rpc('lifeos-views', ['token' => $this->senha, 'tabela' => 'notas'])->json('views');
    expect(collect($views)->firstWhere('id', $a)['padrao'])->toBeFalse()
        ->and(collect($views)->firstWhere('id', $b)['padrao'])->toBeTrue();

    rpc('lifeos-views', ['token' => $this->senha, 'action' => 'update', 'id' => $a, 'patch' => ['padrao' => true]])->assertJsonPath('view.padrao', true);
    $views = rpc('lifeos-views', ['token' => $this->senha, 'tabela' => 'notas'])->json('views');
    expect(collect($views)->where('padrao', true))->toHaveCount(1);
});

it('mesmos códigos de erro', function (array $view, string $erro) {
    ($this->criar)($view)->assertStatus(400)->assertJsonPath('error', $erro);
})->with([
    [['tabela' => 'x', 'nome' => 'a', 'regras' => []], 'invalid_tabela'],
    [['tabela' => 'notas', 'nome' => ' ', 'regras' => []], 'invalid_nome'],
    [['tabela' => 'notas', 'nome' => 'a', 'modo' => 'x', 'regras' => []], 'invalid_modo'],
    [['tabela' => 'notas', 'nome' => 'a'], 'invalid_regras'],
    [['tabela' => 'notas', 'nome' => 'a', 'regras' => [['campo' => 'status', 'operador' => 'incluir', 'valores' => ['x']]]], 'invalid_regras'],
    [['tabela' => 'notas', 'nome' => 'a', 'regras' => [['campo' => 'tipo', 'operador' => 'talvez', 'valores' => ['x']]]], 'invalid_regras'],
    [['tabela' => 'notas', 'nome' => 'a', 'regras' => [['campo' => 'tipo', 'operador' => 'incluir', 'valores' => []]]], 'invalid_regras'],
    [['tabela' => 'notas', 'nome' => 'a', 'regras' => [], 'padrao' => 'sim'], 'invalid_padrao'],
]);

it('update e delete', function () {
    $id = ($this->criar)(['tabela' => 'tarefas', 'nome' => 'A', 'regras' => []])->json('view.id');

    rpc('lifeos-views', ['token' => $this->senha, 'action' => 'update', 'id' => $id, 'patch' => ['regras' => [['campo' => 'status', 'operador' => 'incluir', 'valores' => ['Feito']]], 'nome' => 'Z']])
        ->assertOk()->assertJsonPath('view.nome', 'Z')->assertJsonPath('view.regras.0.campo', 'status');
    rpc('lifeos-views', ['token' => $this->senha, 'action' => 'update', 'id' => $id, 'patch' => ['x' => 1]])->assertJsonPath('error', 'empty_patch');
    rpc('lifeos-views', ['token' => $this->senha, 'action' => 'update', 'id' => '00000000-0000-4000-8000-000000000000', 'patch' => ['nome' => 'a']])->assertStatus(404);
    rpc('lifeos-views', ['token' => $this->senha, 'action' => 'delete', 'id' => $id])->assertOk()->assertJsonPath('id', $id);
});
