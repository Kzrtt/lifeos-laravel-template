<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Citacoes\Models\Citacao;
use Modules\Core\Tenancy\TenantContext;
use Modules\Financas\Models\Movimentacao;
use Modules\Projetos\Models\Projeto;
use Modules\Tenancy\Models\Entity;
use Modules\Visoes\Models\Visao;
use Modules\Vocabularios\Services\VocabularioService;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    [$this->entity, $this->senha] = lifeosInstance();
    app(VocabularioService::class)->semear($this->entity->ent_id);
});

it('exporta só as linhas da instância de quem pede', function () {
    Citacao::factory()->create(['cit_texto' => 'MINHA']);
    $outra = Entity::factory()->create();
    TenantContext::runAs($outra->ent_id, fn () => Citacao::factory()->create(['cit_texto' => 'DO VIZINHO']));

    $r = rpc('lifeos-backup', ['token' => $this->senha])->assertOk();
    $sql = collect($r->json('arquivos'))->pluck('conteudo')->implode("\n");

    expect($sql)->toContain('MINHA')->not->toContain('DO VIZINHO')
        ->and($r->json('arquivos.0.nome'))->toBe('LEIAME.md')
        ->and(collect($r->json('tabelas'))->firstWhere('tabela', 'lifeos_citacoes')['linhas'])->toBe(1)
        ->and($r->json('tabelas.*.tabela'))->not->toContain('migrations', 'sessions');
});

it('credenciais saem como hash, e podem ficar de fora', function () {
    $com = rpc('lifeos-backup', ['token' => $this->senha])->json();
    $sql = collect($com['arquivos'])->pluck('conteudo')->implode("\n");
    expect($sql)->not->toContain($this->senha)->and(collect($com['tabelas'])->pluck('tabela'))->toContain('access_tokens');

    $sem = rpc('lifeos-backup', ['token' => $this->senha, 'incluir_credenciais' => false])->json();
    expect(collect($sem['tabelas'])->pluck('tabela'))->not->toContain('access_tokens', 'token_pages', 'admin_configs');
});

it('ação desconhecida é invalid_action', function () {
    rpc('lifeos-backup', ['token' => $this->senha, 'action' => 'import'])->assertStatus(400)->assertExactJson(['ok' => false, 'error' => 'invalid_action']);
});

it('o backup restaura: apaga a instância e os SQL devolvem tudo (arrays, jsonb, ordem de FK)', function () {
    $p = Projeto::factory()->create(['prj_name' => 'Projeto \'aspas\' e "duplas" $ $lifeos$', 'prj_tags' => ['Pessoal', 'a,b', 'c"d']]);
    Modules\Projetos\Models\Tarefa::factory()->create(['projeto_prj_id' => $p->prj_id, 'tar_tipo' => ['Código']]);
    Movimentacao::factory()->create(['mov_valor' => 12.34, 'mov_tipo' => ['Entrada', 'Pix']]);
    Visao::query()->create(['vis_tabela' => 'notas', 'vis_nome' => 'v', 'vis_regras' => [['campo' => 'tipo', 'operador' => 'incluir', 'valores' => ['Vida']]]]);

    $contar = fn () => collect(['lifeos_projetos', 'lifeos_tarefas', 'lifeos_movimentacoes', 'lifeos_views', 'lifeos_vocabularios', 'access_tokens'])
        ->mapWithKeys(fn ($t) => [$t => DB::table($t)->where('entity_ent_id', $this->entity->ent_id)->count()])->all();
    $antes = $contar();

    $arquivos = rpc('lifeos-backup', ['token' => $this->senha])->json('arquivos');
    $sqls = collect($arquivos)->filter(fn ($a) => str_ends_with($a['nome'], '.sql'))->sortBy('nome');

    DB::transaction(function () use ($sqls, $contar, $antes, $p) {
        foreach ($sqls as $arquivo) {
            DB::unprepared($arquivo['conteudo']);
        }
        expect($contar())->toBe($antes);

        $projeto = DB::table('lifeos_projetos')->where('prj_id', $p->prj_id)->first();
        expect($projeto->prj_name)->toBe($p->prj_name)->and($projeto->prj_tags)->toBe('{Pessoal,"a,b","c\"d"}');
        $vis = DB::table('lifeos_views')->first();
        expect(json_decode($vis->vis_regras, true)[0]['campo'])->toBe('tipo');
    });
});
