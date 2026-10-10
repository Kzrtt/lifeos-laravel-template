<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Financas\Models\Movimentacao;
use Modules\Financas\Models\Recorrencia;
use Modules\Financas\Services\SaldoService;
use Modules\Vocabularios\Services\VocabularioService;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    [$this->entity, $this->senha] = lifeosInstance();
    app(VocabularioService::class)->semear($this->entity->ent_id);
    $this->mov = fn (array $m) => rpc('lifeos-movimentacoes', ['token' => $this->senha, 'action' => 'create', 'movimentacao' => $m]);
});

it('saldo de abertura: Entrada soma, Saida subtrai, Crédito não sai do caixa', function () {
    Movimentacao::factory()->create(['mov_valor' => 1000, 'mov_date' => '2026-08-05', 'mov_tipo' => ['Entrada', 'Pix']]);
    Movimentacao::factory()->create(['mov_valor' => 250.50, 'mov_date' => '2026-08-10', 'mov_tipo' => ['Saida', 'Débito']]);
    Movimentacao::factory()->create(['mov_valor' => 400, 'mov_date' => '2026-08-12', 'mov_tipo' => ['Saida', 'Crédito']]);
    Movimentacao::factory()->create(['mov_valor' => 99, 'mov_date' => '2026-09-01', 'mov_tipo' => ['Saida', 'Pix']]);

    expect(app(SaldoService::class)->saldoAbertura('2026-09-01'))->toBe(749.5)
        ->and(app(SaldoService::class)->saldoAbertura('2026-10-01'))->toBe(650.5)
        ->and(app(SaldoService::class)->saldoAbertura('2026-01-01'))->toBe(0.0);
});

it('saldo em centavos não acumula erro de ponto flutuante', function () {
    foreach (range(1, 100) as $i) {
        Movimentacao::factory()->create(['mov_valor' => 0.10, 'mov_date' => '2026-01-01', 'mov_tipo' => ['Entrada', 'Pix']]);
    }

    expect(app(SaldoService::class)->saldoAbertura('2026-02-01'))->toBe(10.0);
});

it('query do mês: chaves, abertura, intervalo, ordem e mês anterior', function () {
    Movimentacao::factory()->create(['mov_name' => 'antes', 'mov_valor' => 100, 'mov_date' => '2026-09-15', 'mov_tipo' => ['Entrada', 'Pix']]);
    Movimentacao::factory()->create(['mov_name' => 'b', 'mov_valor' => 10.5, 'mov_date' => '2026-10-20']);
    Movimentacao::factory()->create(['mov_name' => 'a', 'mov_valor' => 5, 'mov_date' => '2026-10-02']);
    Movimentacao::factory()->create(['mov_name' => 'depois', 'mov_date' => '2026-11-01']);

    $r = rpc('lifeos-movimentacoes', ['token' => $this->senha, 'ym' => '2026-10', 'ym_prev' => '2026-09'])->assertOk();

    expect($r->json('ym'))->toBe('2026-10')->and($r->json('ym_prev'))->toBe('2026-09')
        ->and($r->json('range'))->toBe(['min' => '2026-09', 'max' => '2026-11'])
        ->and($r->json('saldo_abertura'))->toBe(100)
        ->and($r->json('count'))->toBe(2)
        ->and($r->json('movimentacoes.*.name'))->toBe(['a', 'b'])
        ->and($r->json('movimentacoes_prev.*.name'))->toBe(['antes'])
        ->and(array_keys($r->json('movimentacoes.0')))->toBe(['id', 'name', 'valor', 'date', 'tipo', 'created_at'])
        ->and($r->json('fetched_at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/');

    $sem = rpc('lifeos-movimentacoes', ['token' => $this->senha, 'ym' => '2026-10'])->json();
    expect($sem)->not->toHaveKeys(['ym_prev', 'movimentacoes_prev']);
});

it('sem dados: range null e saldo 0', function () {
    $r = rpc('lifeos-movimentacoes', ['token' => $this->senha, 'ym' => '2026-10'])->assertOk();
    expect($r->json('range'))->toBeNull()->and($r->json('saldo_abertura'))->toBe(0)->and($r->json('movimentacoes'))->toBe([]);
});

it('CRUD da movimentação', function () {
    $c = ($this->mov)(['name' => ' Mercado ', 'valor' => 42.456, 'date' => '2026-10-07', 'tipo' => ['Saida', 'Pix']])->assertOk();
    expect($c->json('movimentacao.name'))->toBe('Mercado')->and($c->json('movimentacao.valor'))->toBe(42.46);
    $id = $c->json('movimentacao.id');

    rpc('lifeos-movimentacoes', ['token' => $this->senha, 'action' => 'update', 'id' => $id, 'patch' => ['valor' => '10', 'tipo' => ['Entrada']]])
        ->assertOk()->assertJsonPath('movimentacao.valor', 10)->assertJsonPath('movimentacao.tipo', ['Entrada']);
    rpc('lifeos-movimentacoes', ['token' => $this->senha, 'action' => 'delete', 'id' => $id])->assertOk();
});

it('mesmos códigos de erro da movimentação', function (array $mov, string $erro) {
    ($this->mov)([...['name' => 'a', 'valor' => 1, 'date' => '2026-10-07', 'tipo' => ['Saida', 'Pix']], ...$mov])
        ->assertStatus(400)->assertExactJson(['ok' => false, 'error' => $erro]);
})->with([
    [['name' => ' '], 'invalid_name'],
    [['valor' => -1], 'invalid_valor'],
    [['valor' => 'abc'], 'invalid_valor'],
    [['date' => '07/10/2026'], 'invalid_date'],
    [['tipo' => []], 'invalid_tipo'],
    [['tipo' => ['Pix']], 'invalid_tipo'],
    [['tipo' => ['Entrada', 'Saida']], 'invalid_tipo'],
    [['tipo' => ['Saida', 'Cheque']], 'invalid_tipo'],
]);

it('update/create: ausências', function () {
    rpc('lifeos-movimentacoes', ['token' => $this->senha, 'action' => 'create'])->assertJsonPath('error', 'missing_movimentacao');
    rpc('lifeos-movimentacoes', ['token' => $this->senha, 'action' => 'update', 'patch' => ['valor' => 1]])->assertJsonPath('error', 'missing_id');
    rpc('lifeos-movimentacoes', ['token' => $this->senha, 'action' => 'update', 'id' => 'x', 'patch' => ['zzz' => 1]])->assertJsonPath('error', 'empty_patch');
    rpc('lifeos-movimentacoes', ['token' => $this->senha, 'action' => 'update', 'id' => '00000000-0000-4000-8000-000000000000', 'patch' => ['valor' => 1]])->assertStatus(404);
});

it('webhook: payload do Notion, senha na URL, só muda o host', function () {
    $payload = [
        'parent' => ['database_id' => 'abc'],
        'icon' => ['type' => 'icon', 'icon' => ['name' => 'cash', 'color' => 'gray']],
        'properties' => [
            'Name' => ['title' => [['text' => ['content' => 'Mer']], ['text' => ['content' => 'cado ']]]],
            'Valor' => ['number' => 42.5],
            'Tipo' => ['multi_select' => [['name' => 'Saida'], ['name' => 'Pix']]],
            'Date' => ['date' => ['start' => '2026-09-16']],
        ],
    ];

    $r = $this->postJson('/functions/v1/lifeos-ingest?token='.urlencode($this->senha), $payload)->assertOk();
    $mov = Movimentacao::query()->find($r->json('id'));
    expect($mov->mov_name)->toBe('Mercado')->and((float) $mov->mov_valor)->toBe(42.5)->and($mov->mov_tipo)->toBe(['Saida', 'Pix']);

    $this->postJson('/functions/v1/lifeos-ingest', $payload)->assertStatus(400)->assertJsonPath('error', 'missing_token');
    $this->postJson('/functions/v1/lifeos-ingest?token=errada', $payload)->assertStatus(401);
    $this->postJson('/functions/v1/lifeos-ingest?token='.urlencode($this->senha), ['properties' => []])->assertStatus(400)->assertJsonPath('error', 'invalid_name');

    $sem = $payload;
    unset($sem['properties']['Valor']);
    $this->postJson('/functions/v1/lifeos-ingest?token='.urlencode($this->senha), $sem)->assertStatus(400)->assertJsonPath('error', 'invalid_valor');
});

it('recorrências: CRUD, duplicata sem acento, validações', function () {
    $c = fn (array $r) => rpc('lifeos-recorrencias', ['token' => $this->senha, 'action' => 'create', 'recorrencia' => $r]);

    $a = $c(['nome' => 'Aluguel', 'direcao' => 'Saida', 'valor_min' => 900, 'valor_max' => 1000, 'meio' => 'Pix', 'dia' => 5])->assertOk();
    expect(array_keys($a->json('recorrencia')))->toBe(['id', 'nome', 'direcao', 'valor_min', 'valor_max', 'meio', 'dia', 'ativa', 'created_at', 'updated_at'])
        ->and($a->json('recorrencia.ativa'))->toBeTrue();

    $c(['nome' => ' ALUGUEL ', 'direcao' => 'Saida', 'valor_min' => 1, 'valor_max' => 2])->assertStatus(409)->assertJsonPath('error', 'nome_duplicado');
    $c(['nome' => 'Aluguel', 'direcao' => 'Entrada', 'valor_min' => 1, 'valor_max' => 2])->assertOk(); // outra direção
    $c(['nome' => 'Água', 'direcao' => 'Saida', 'valor_min' => 1, 'valor_max' => 2])->assertOk();
    $c(['nome' => 'agua', 'direcao' => 'Saida', 'valor_min' => 1, 'valor_max' => 2])->assertStatus(409);

    foreach ([
        [['nome' => ''], 'invalid_nome'], [['direcao' => 'X'], 'invalid_direcao'], [['valor_min' => 5, 'valor_max' => 1], 'invalid_valor'],
        [['valor_max' => null], 'invalid_valor'], [['meio' => 'Cheque'], 'invalid_meio'], [['dia' => 32], 'invalid_dia'],
        [['dia' => 1.5], 'invalid_dia'], [['ativa' => 'sim'], 'invalid_ativa'],
    ] as [$campo, $erro]) {
        $c([...['nome' => 'zz', 'direcao' => 'Saida', 'valor_min' => 1, 'valor_max' => 2], ...$campo])->assertStatus(400)->assertJsonPath('error', $erro);
    }

    $id = $a->json('recorrencia.id');
    rpc('lifeos-recorrencias', ['token' => $this->senha, 'action' => 'update', 'id' => $id, 'patch' => ['ativa' => false, 'dia' => null, 'meio' => '']])
        ->assertOk()->assertJsonPath('recorrencia.ativa', false)->assertJsonPath('recorrencia.dia', null)->assertJsonPath('recorrencia.meio', null);
    rpc('lifeos-recorrencias', ['token' => $this->senha, 'action' => 'update', 'id' => $id, 'patch' => ['valor_min' => 5]])->assertJsonPath('error', 'invalid_valor');
    rpc('lifeos-recorrencias', ['token' => $this->senha])->assertJsonCount(3, 'recorrencias');
    rpc('lifeos-recorrencias', ['token' => $this->senha, 'action' => 'delete', 'id' => $id])->assertOk();
});

it('renomear meio migra movimentações E recorrências; uso conta as duas', function () {
    Movimentacao::factory()->create(['mov_tipo' => ['Saida', 'Pix']]);
    Recorrencia::factory()->create(['rec_meio' => 'Pix']);

    $voc = rpc('lifeos-vocabularios', ['token' => $this->senha])->json('vocabularios.mov_meio');
    $pix = collect($voc)->firstWhere('valor', 'Pix');
    expect($pix['uso'])->toBe(2);

    rpc('lifeos-vocabularios', ['token' => $this->senha, 'action' => 'update', 'id' => $pix['id'], 'novo_valor' => 'PIX'])
        ->assertOk()->assertJsonPath('linhas_migradas', 2);
    expect(Movimentacao::query()->first()->mov_tipo)->toBe(['Saida', 'PIX'])->and(Recorrencia::query()->first()->rec_meio)->toBe('PIX');
});
