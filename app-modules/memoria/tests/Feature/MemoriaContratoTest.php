<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Memoria\Models\Memoria;
use Modules\Vocabularios\Services\VocabularioService;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    [$this->entity, $this->senha] = lifeosInstance();
    app(VocabularioService::class)->semear($this->entity->ent_id);
});

it('cria memória com registros iniciais e devolve o índice com categorias', function () {
    $r = rpc('lifeos-memorias', ['token' => $this->senha, 'action' => 'create', 'memoria' => [
        'titulo' => ' Quem sou ', 'descricao' => 'resumo', 'categoria' => 'Perfil', 'registros' => ['um', 'dois'],
    ]])->assertOk();

    expect(array_keys($r->json('memoria')))->toBe(['id', 'titulo', 'descricao', 'categoria', 'created_at', 'updated_at', 'registros'])
        ->and($r->json('memoria.titulo'))->toBe('Quem sou')
        ->and($r->json('memoria.registros.*.texto'))->toBe(['um', 'dois'])
        ->and($r->json('memoria.registros.0.origem'))->toBe('manual')
        ->and(array_keys($r->json('memoria.registros.0')))->toBe(['id', 'memoria_id', 'texto', 'origem', 'created_at', 'updated_at']);

    rpc('lifeos-memorias', ['token' => $this->senha])
        ->assertJsonCount(1, 'memorias')->assertJsonPath('categorias', ['Perfil', 'Preferências', 'Projetos', 'Referências', 'Vida']);
});

it('título é único sem diferenciar caixa e espaço', function () {
    Memoria::factory()->create(['mem_titulo' => 'Meu Título']);

    rpc('lifeos-memorias', ['token' => $this->senha, 'action' => 'create', 'memoria' => ['titulo' => '  meu título ', 'categoria' => 'Perfil']])
        ->assertStatus(409)->assertExactJson(['ok' => false, 'error' => 'titulo_duplicado']);

    $outra = Memoria::factory()->create();
    rpc('lifeos-memorias', ['token' => $this->senha, 'action' => 'update', 'id' => $outra->mem_id, 'patch' => ['titulo' => 'MEU TÍTULO']])
        ->assertStatus(409)->assertJsonPath('error', 'titulo_duplicado');

    // apagada libera o título
    $x = Memoria::factory()->create(['mem_titulo' => 'Livre']);
    rpc('lifeos-memorias', ['token' => $this->senha, 'action' => 'delete', 'id' => $x->mem_id])->assertOk();
    rpc('lifeos-memorias', ['token' => $this->senha, 'action' => 'create', 'memoria' => ['titulo' => 'livre', 'categoria' => 'Vida']])->assertOk();
});

it('registro: cria, edita, apaga, e a memória sobe no índice', function () {
    $m = Memoria::factory()->create();
    $outra = Memoria::factory()->create();
    $outra->forceFill(['mem_updated_at' => now()->addMinute()])->saveQuietly();

    $reg = rpc('lifeos-memorias', ['token' => $this->senha, 'action' => 'registro_create', 'registro' => ['memoria_id' => $m->mem_id, 'texto' => 'fato', 'origem' => 'claude.ai']])
        ->assertOk()->assertJsonPath('registro.origem', 'claude.ai');
    $id = $reg->json('registro.id');

    rpc('lifeos-memorias', ['token' => $this->senha, 'action' => 'registro_update', 'id' => $id, 'patch' => ['texto' => 'fato corrigido']])
        ->assertOk()->assertJsonPath('registro.texto', 'fato corrigido');
    rpc('lifeos-memorias', ['token' => $this->senha, 'action' => 'registro_delete', 'id' => $id])->assertOk()->assertJsonPath('id', $id);

    expect(Memoria::query()->count())->toBe(2);
    rpc('lifeos-memorias', ['token' => $this->senha, 'action' => 'registro_create', 'registro' => ['memoria_id' => '00000000-0000-4000-8000-000000000000', 'texto' => 'x']])
        ->assertStatus(404)->assertJsonPath('error', 'not_found');
});

it('excluir memória leva os registros junto', function () {
    $r = rpc('lifeos-memorias', ['token' => $this->senha, 'action' => 'create', 'memoria' => ['titulo' => 'a', 'categoria' => 'Vida', 'registros' => ['x']]]);
    rpc('lifeos-memorias', ['token' => $this->senha, 'action' => 'delete', 'id' => $r->json('memoria.id')])->assertOk();

    expect(Modules\Memoria\Models\MemoriaRegistro::query()->count())->toBe(0);
    rpc('lifeos-memorias', ['token' => $this->senha])->assertJsonCount(0, 'memorias');
});

it('mesmos códigos de erro', function (array $body, int $status, string $erro) {
    rpc('lifeos-memorias', ['token' => $this->senha, ...$body])->assertStatus($status)->assertJsonPath('error', $erro);
})->with([
    [['action' => 'create'], 400, 'missing_memoria'],
    [['action' => 'create', 'memoria' => ['titulo' => str_repeat('a', 121), 'categoria' => 'Vida']], 400, 'invalid_titulo'],
    [['action' => 'create', 'memoria' => ['titulo' => 'a', 'descricao' => str_repeat('a', 401), 'categoria' => 'Vida']], 400, 'invalid_descricao'],
    [['action' => 'create', 'memoria' => ['titulo' => 'a', 'categoria' => 'Nada']], 400, 'invalid_categoria'],
    [['action' => 'create', 'memoria' => ['titulo' => 'a', 'categoria' => 'Vida', 'registros' => ['']]], 400, 'invalid_texto'],
    [['action' => 'registro_create'], 400, 'missing_registro'],
    [['action' => 'registro_create', 'registro' => ['texto' => 'x']], 400, 'missing_memoria_id'],
    [['action' => 'registro_update', 'id' => '00000000-0000-4000-8000-000000000000', 'patch' => []], 400, 'invalid_texto'],
    [['action' => 'registro_delete'], 400, 'missing_id'],
]);
