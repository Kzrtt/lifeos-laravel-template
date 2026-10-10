<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Notas\Models\Nota;
use Modules\Projetos\Models\Projeto;
use Modules\Vocabularios\Services\VocabularioService;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    [$this->entity, $this->senha] = lifeosInstance();
    app(VocabularioService::class)->semear($this->entity->ent_id);
});

it('CRUD com projetos vinculados, chaves e conteúdo byte a byte', function () {
    $p1 = Projeto::factory()->create();
    $p2 = Projeto::factory()->create();
    $md = "# Título\n\n  texto com {{chaves}} e espaços no fim   \n\n";

    $c = rpc('lifeos-notas', ['token' => $this->senha, 'action' => 'create', 'nota' => [
        'name' => ' Minha nota ', 'tipo' => ['Vida', 'Pessoal'], 'data' => '2026-10-07', 'conteudo_md' => $md, 'projeto_ids' => [$p1->prj_id, $p2->prj_id],
    ]])->assertOk();

    expect(array_keys($c->json('nota')))->toBe(['id', 'name', 'tipo', 'data', 'conteudo_md', 'projeto_ids', 'created_at', 'updated_at'])
        ->and($c->json('nota.name'))->toBe('Minha nota')
        ->and($c->json('nota.conteudo_md'))->toBe($md)
        ->and($c->json('nota.projeto_ids'))->toHaveCount(2);

    $id = $c->json('nota.id');
    rpc('lifeos-notas', ['token' => $this->senha, 'action' => 'update', 'id' => $id, 'patch' => ['projeto_ids' => [$p2->prj_id]]])
        ->assertOk()->assertJsonPath('nota.projeto_ids', [$p2->prj_id])->assertJsonPath('nota.name', 'Minha nota');

    rpc('lifeos-notas', ['token' => $this->senha, 'action' => 'update', 'id' => $id, 'patch' => ['data' => null, 'conteudo_md' => null, 'tipo' => []]])
        ->assertOk()->assertJsonPath('nota.data', null)->assertJsonPath('nota.conteudo_md', null)->assertJsonPath('nota.projeto_ids', [$p2->prj_id]);

    rpc('lifeos-notas', ['token' => $this->senha, 'action' => 'delete', 'id' => $id])->assertOk();
    rpc('lifeos-notas', ['token' => $this->senha])->assertJsonCount(0, 'notas');
});

it('lista por data desc (nulos por último), depois criação desc', function () {
    $a = Nota::factory()->create(['not_name' => 'sem data']);
    $b = Nota::factory()->create(['not_name' => 'antiga', 'not_data' => '2026-01-01']);
    $c = Nota::factory()->create(['not_name' => 'recente', 'not_data' => '2026-06-01']);

    $nomes = rpc('lifeos-notas', ['token' => $this->senha])->json('notas.*.name');
    expect($nomes)->toBe(['recente', 'antiga', 'sem data']);
});

it('mesmos códigos de erro', function (array $body, int $status, string $erro) {
    rpc('lifeos-notas', ['token' => $this->senha, ...$body])->assertStatus($status)->assertJsonPath('error', $erro);
})->with([
    'sem nota' => [['action' => 'create'], 400, 'missing_nota'],
    'sem nome' => [['action' => 'create', 'nota' => ['name' => ' ']], 400, 'invalid_name'],
    'tipo inválido' => [['action' => 'create', 'nota' => ['name' => 'a', 'tipo' => ['Zzz']]], 400, 'invalid_tipo'],
    'data inválida' => [['action' => 'create', 'nota' => ['name' => 'a', 'data' => '07/10/2026']], 400, 'invalid_data'],
    'projeto_ids não-array' => [['action' => 'create', 'nota' => ['name' => 'a', 'projeto_ids' => 'x']], 400, 'invalid_projeto_ids'],
    'projeto_id vazio' => [['action' => 'create', 'nota' => ['name' => 'a', 'projeto_ids' => ['']]], 400, 'invalid_projeto_ids'],
    'projeto inexistente' => [['action' => 'create', 'nota' => ['name' => 'a', 'projeto_ids' => ['00000000-0000-4000-8000-000000000000']]], 400, 'invalid_projeto_ids'],
    'update vazio' => [['action' => 'update', 'id' => '00000000-0000-4000-8000-000000000000', 'patch' => ['x' => 1]], 400, 'empty_patch'],
    'update inexistente' => [['action' => 'update', 'id' => '00000000-0000-4000-8000-000000000000', 'patch' => ['name' => 'x']], 404, 'not_found'],
    'delete sem id' => [['action' => 'delete'], 400, 'missing_id'],
]);

it('excluir um projeto desvincula as notas (evento)', function () {
    $p = Projeto::factory()->create();
    $n = rpc('lifeos-notas', ['token' => $this->senha, 'action' => 'create', 'nota' => ['name' => 'a', 'projeto_ids' => [$p->prj_id]]])->json('nota.id');

    rpc('lifeos-projetos', ['token' => $this->senha, 'action' => 'delete', 'id' => $p->prj_id])->assertOk();

    expect(rpc('lifeos-notas', ['token' => $this->senha])->json('notas.0.projeto_ids'))->toBe([]);
});

it('renomear tipo de nota migra os dados', function () {
    $nota = Nota::factory()->create(['not_tipo' => ['Vida', 'Pessoal']]);
    $voc = rpc('lifeos-vocabularios', ['token' => $this->senha])->json('vocabularios.nota_tipo');
    $id = collect($voc)->firstWhere('valor', 'Vida')['id'];

    rpc('lifeos-vocabularios', ['token' => $this->senha, 'action' => 'update', 'id' => $id, 'novo_valor' => 'Existência'])
        ->assertOk()->assertJsonPath('linhas_migradas', 1);
    expect($nota->fresh()->not_tipo)->toBe(['Existência', 'Pessoal']);
});
