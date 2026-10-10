<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\AuditLog;
use Modules\Projetos\Models\Projeto;
use Modules\Projetos\Models\Tarefa;
use Modules\Projetos\Support\ConsumidorProjetos;
use Modules\Vocabularios\Services\VocabularioService;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    [$this->entity, $this->senha] = lifeosInstance();
    app(VocabularioService::class)->semear($this->entity->ent_id);
});

it('projetos: CRUD, chaves e ordem por nome', function () {
    $b = rpc('lifeos-projetos', ['token' => $this->senha, 'action' => 'create', 'projeto' => ['name' => 'Beta', 'status' => 'Em Progresso', 'tags' => ['Pessoal'], 'emoji' => ' 🚀 ']])
        ->assertOk()->assertJsonPath('projeto.emoji', '🚀');
    expect(array_keys($b->json('projeto')))->toBe(['id', 'name', 'emoji', 'status', 'tags']);

    rpc('lifeos-projetos', ['token' => $this->senha, 'action' => 'create', 'projeto' => ['name' => 'Alfa', 'status' => 'Feito']])->assertOk();
    rpc('lifeos-projetos', ['token' => $this->senha])->assertJsonPath('projetos.0.name', 'Alfa')->assertJsonPath('projetos.1.name', 'Beta');

    $id = $b->json('projeto.id');
    rpc('lifeos-projetos', ['token' => $this->senha, 'action' => 'update', 'id' => $id, 'patch' => ['tags' => ['Acadêmico', 'Pessoal'], 'emoji' => '']])
        ->assertOk()->assertJsonPath('projeto.tags', ['Acadêmico', 'Pessoal'])->assertJsonPath('projeto.emoji', null);
});

it('projetos: mesmos códigos de erro', function (array $body, int $status, string $erro) {
    rpc('lifeos-projetos', ['token' => $this->senha, ...$body])->assertStatus($status)->assertExactJson(['ok' => false, 'error' => $erro]);
})->with([
    'sem projeto' => [['action' => 'create'], 400, 'missing_projeto'],
    'sem nome' => [['action' => 'create', 'projeto' => ['name' => ' ', 'status' => 'Feito']], 400, 'invalid_name'],
    'status fora do vocabulário' => [['action' => 'create', 'projeto' => ['name' => 'a', 'status' => 'Xis']], 400, 'invalid_status'],
    'tag fora do vocabulário' => [['action' => 'create', 'projeto' => ['name' => 'a', 'status' => 'Feito', 'tags' => ['Nope']]], 400, 'invalid_tags'],
    'update sem id' => [['action' => 'update', 'patch' => ['name' => 'x']], 400, 'missing_id'],
    'update patch vazio' => [['action' => 'update', 'id' => '00000000-0000-4000-8000-000000000000', 'patch' => []], 400, 'empty_patch'],
    'update inexistente' => [['action' => 'update', 'id' => '00000000-0000-4000-8000-000000000000', 'patch' => ['name' => 'x']], 404, 'not_found'],
    'delete inexistente' => [['action' => 'delete', 'id' => '00000000-0000-4000-8000-000000000000'], 404, 'not_found'],
]);

it('o vocabulário editado vale na validação', function () {
    rpc('lifeos-vocabularios', ['token' => $this->senha, 'action' => 'create', 'dominio' => 'projeto_status', 'valor' => 'Arquivado'])->assertOk();
    rpc('lifeos-projetos', ['token' => $this->senha, 'action' => 'create', 'projeto' => ['name' => 'a', 'status' => 'Arquivado']])->assertOk();
});

it('não apaga projeto com tarefas (has_tarefas) e apaga depois de esvaziar', function () {
    $p = Projeto::factory()->create();
    $t = Tarefa::factory()->create(['projeto_prj_id' => $p->prj_id]);

    rpc('lifeos-projetos', ['token' => $this->senha, 'action' => 'delete', 'id' => $p->prj_id])
        ->assertStatus(409)->assertExactJson(['ok' => false, 'error' => 'has_tarefas']);

    rpc('lifeos-tarefas', ['token' => $this->senha, 'action' => 'delete', 'id' => $t->tar_id])->assertOk();
    rpc('lifeos-projetos', ['token' => $this->senha, 'action' => 'delete', 'id' => $p->prj_id])->assertOk();
});

it('tarefas: CRUD, filtro por projeto e chaves', function () {
    $p1 = Projeto::factory()->create();
    $p2 = Projeto::factory()->create();

    $c = rpc('lifeos-tarefas', ['token' => $this->senha, 'action' => 'create', 'tarefa' => [
        'name' => 'Fazer', 'status' => 'Em Andamento', 'tipo' => ['Código'], 'projeto_id' => $p1->prj_id,
        'data_entrega' => '2026-10-10', 'descricao' => '  md  ',
    ]])->assertOk();
    expect(array_keys($c->json('tarefa')))->toBe(['id', 'name', 'status', 'tipo', 'projeto_id', 'data_entrega', 'descricao', 'updated_at'])
        ->and($c->json('tarefa.data_entrega'))->toBe('2026-10-10')
        ->and($c->json('tarefa.descricao'))->toBe('md');

    rpc('lifeos-tarefas', ['token' => $this->senha, 'action' => 'create', 'tarefa' => ['name' => 'Outra', 'status' => 'Feito', 'projeto_id' => $p2->prj_id, 'data_entrega' => 'lixo']])
        ->assertJsonPath('tarefa.data_entrega', null);

    rpc('lifeos-tarefas', ['token' => $this->senha])->assertJsonCount(2, 'tarefas');
    rpc('lifeos-tarefas', ['token' => $this->senha, 'projeto_id' => $p1->prj_id])->assertJsonCount(1, 'tarefas');
    rpc('lifeos-tarefas', ['token' => $this->senha, 'projeto_id' => 'lixo'])->assertJsonCount(0, 'tarefas');

    rpc('lifeos-tarefas', ['token' => $this->senha, 'action' => 'update', 'id' => $c->json('tarefa.id'), 'patch' => ['status' => 'Feito', 'projeto_id' => $p2->prj_id, 'descricao' => '']])
        ->assertOk()->assertJsonPath('tarefa.status', 'Feito')->assertJsonPath('tarefa.projeto_id', $p2->prj_id)->assertJsonPath('tarefa.descricao', null);
});

it('tarefas: erros do contrato', function (array $tarefa, string $erro) {
    $p = Projeto::factory()->create();
    $base = ['name' => 'a', 'status' => 'Feito', 'projeto_id' => $p->prj_id];
    rpc('lifeos-tarefas', ['token' => $this->senha, 'action' => 'create', 'tarefa' => [...$base, ...$tarefa]])
        ->assertStatus(400)->assertJsonPath('error', $erro);
})->with([
    ['tarefa' => ['name' => ''], 'erro' => 'invalid_name'],
    ['tarefa' => ['status' => 'x'], 'erro' => 'invalid_status'],
    ['tarefa' => ['tipo' => ['x']], 'erro' => 'invalid_tipo'],
    ['tarefa' => ['projeto_id' => ''], 'erro' => 'missing_projeto_id'],
    ['tarefa' => ['projeto_id' => '00000000-0000-4000-8000-000000000000'], 'erro' => 'invalid_projeto_id'],
]);

it('tarefa: não fica sem projeto no update', function () {
    $t = Tarefa::factory()->create();
    rpc('lifeos-tarefas', ['token' => $this->senha, 'action' => 'update', 'id' => $t->tar_id, 'patch' => ['projeto_id' => '']])
        ->assertStatus(400)->assertJsonPath('error', 'invalid_projeto_id');
});

it('consumidor de vocabulário conta e renomeia (arrays e escalares), auditando', function () {
    $p = Projeto::factory()->create(['prj_tags' => ['Pessoal', 'Acadêmico']]);
    Tarefa::factory()->count(2)->create(['projeto_prj_id' => $p->prj_id, 'tar_status' => 'Feito']);
    $c = app(ConsumidorProjetos::class);

    expect($c->contarUso('projeto_tag', 'Pessoal'))->toBe(1)
        ->and($c->contarUso('tarefa_status', 'Feito'))->toBe(2)
        ->and($c->renomear('projeto_tag', 'Pessoal', 'Íntimo'))->toBe(1)
        ->and($p->fresh()->prj_tags)->toBe(['Íntimo', 'Acadêmico'])
        ->and($c->renomear('tarefa_status', 'Feito', 'Concluído'))->toBe(2)
        ->and(AuditLog::query()->where('aud_table', 'lifeos_tarefas')->where('aud_action', 'updated')->count())->toBe(2);
});

it('renomear via vocabularios migra os dados do módulo', function () {
    $p = Projeto::factory()->create(['prj_status' => 'Pausado']);
    $voc = rpc('lifeos-vocabularios', ['token' => $this->senha])->json('vocabularios.projeto_status');
    $id = collect($voc)->firstWhere('valor', 'Pausado')['id'];

    rpc('lifeos-vocabularios', ['token' => $this->senha, 'action' => 'update', 'id' => $id, 'novo_valor' => 'Em pausa'])
        ->assertOk()->assertJsonPath('linhas_migradas', 1);
    expect($p->fresh()->prj_status)->toBe('Em pausa');
});
