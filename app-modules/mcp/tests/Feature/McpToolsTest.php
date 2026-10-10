<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Agenda\Models\Evento;
use Modules\Financas\Models\Movimentacao;
use Modules\Financas\Models\Recorrencia;
use Modules\Manifestacoes\Models\Manifestacao;
use Modules\Notas\Models\Nota;
use Modules\Projetos\Models\Projeto;
use Modules\Renuncias\Models\Renuncia;
use Modules\Vocabularios\Services\VocabularioService;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    [$this->entity, $this->senha] = lifeosInstance();
    app(VocabularioService::class)->semear($this->entity->ent_id);
    $this->token = lifeosMcpToken();
    $this->tool = fn (string $nome, array $args = []) => mcpTool($this->token, $nome, $args);
    $this->json = function (string $nome, array $args = []) {
        [$texto, $erro] = ($this->tool)($nome, $args);
        expect($erro)->toBeFalse("tool {$nome} devolveu erro: {$texto}");

        return json_decode($texto, true, 512, JSON_THROW_ON_ERROR);
    };
    $this->erro = function (string $nome, array $args = []) {
        [$texto, $erro] = ($this->tool)($nome, $args);
        expect($erro)->toBeTrue("tool {$nome} deveria dar erro, deu: {$texto}");

        return $texto;
    };
});

it('o texto das tools é JSON com 2 espaços de indentação, como o JSON.stringify', function () {
    Projeto::factory()->create(['prj_name' => 'Alfa', 'prj_emoji' => '🚀']);

    [$texto] = ($this->tool)('search_projetos');

    expect($texto)->toStartWith("{\n  \"total_matches\": 1,\n  \"returned\": 1,\n  \"truncated\": false,\n  \"projetos\": [\n    {\n      \"id\"");
});

// ───────────────────────── notas

it('notas: create → search (filtros e rótulo do projeto) → update (substituição completa)', function () {
    Projeto::factory()->create(['prj_name' => 'LifeOS', 'prj_emoji' => '🧬']);
    Projeto::factory()->create(['prj_name' => 'Faculdade']);

    $c = ($this->json)('create_nota', ['name' => 'Ideia', 'tipo' => ['Pessoal'], 'projetos' => ['lifeos'], 'conteudo_md' => "  # Título\n\ntexto com **negrito** e [link](http://x)  "]);
    expect($c['ok'])->toBeTrue()->and($c['nota']['data'])->toBe(Carbon::now('America/Sao_Paulo')->format('Y-m-d'))
        ->and($c['nota']['projetos'][0]['name'])->toBe('🧬 LifeOS')
        ->and($c['nota']['conteudo_md'])->toBe("# Título\n\ntexto com **negrito** e [link](http://x)");

    $s = ($this->json)('search_notas', ['nome' => 'idei', 'tipo' => ['Pessoal'], 'projetos' => ['life']]);
    expect($s['total_matches'])->toBe(1)->and($s['notas'][0]['snippet'])->toBe('Título texto com negrito e link')
        ->and($s)->not->toHaveKey('warnings');

    $u = ($this->json)('update_nota', ['id' => $c['nota']['id'], 'name' => 'Ideia 2', 'tipo' => ['Vida'], 'projetos' => ['Faculdade', 'LifeOS'], 'conteudo_md' => 'novo texto inteiro']);
    expect($u['nota']['projetos'])->toHaveCount(2)->and($u['nota']['name'])->toBe('Ideia 2');
    expect(Nota::query()->first()->vinculos()->count())->toBe(2);
});

it('notas: filtro por projeto inexistente avisa e não vai ao banco; erros em texto', function () {
    Nota::factory()->create();
    $s = ($this->json)('search_notas', ['projetos' => ['nada']]);
    expect($s)->toBe(['total_matches' => 0, 'returned' => 0, 'truncated' => false, 'warnings' => ['nenhum projeto encontrado com "nada"'], 'notas' => []]);

    expect(($this->erro)('search_notas', ['tipo' => ['Zzz']]))->toContain('Tipo(s) inválido(s): Zzz. Valores aceitos: Lembranças,')
        ->and(($this->erro)('search_notas', ['data_inicio' => '2026-13-45']))->toBe('Data(s) inválida(s): 2026-13-45. Use o formato YYYY-MM-DD.')
        ->and(($this->erro)('create_nota', ['tipo' => ['Vida'], 'projetos' => ['x'], 'conteudo_md' => 'x']))->toBe('O parâmetro name (nome da nota) é obrigatório e não pode ser vazio.')
        ->and(($this->erro)('create_nota', ['name' => 'a', 'tipo' => ['Vida'], 'projetos' => ['Fantasma'], 'conteudo_md' => 'x']))->toContain('Projeto(s) não encontrado(s) ou ambíguo(s): Fantasma. Projetos existentes:')
        ->and(($this->erro)('update_nota', ['id' => '00000000-0000-4000-8000-000000000000', 'name' => 'a', 'tipo' => ['Vida'], 'projetos' => [], 'conteudo_md' => 'x']))
        ->toBe('Nenhuma nota encontrada com id 00000000-0000-4000-8000-000000000000.');
});

// ───────────────────────── tarefas e projetos

it('tarefas: create/search/update trocando de projeto', function () {
    $a = Projeto::factory()->create(['prj_name' => 'Casa']);
    $b = Projeto::factory()->create(['prj_name' => 'Trabalho']);

    $c = ($this->json)('create_tarefa', ['name' => 'Pintar', 'projeto' => 'casa', 'tipo' => ['Vida'], 'data_entrega' => '2026-10-20']);
    expect($c['tarefa']['status'])->toBe('Não Iniciado')->and($c['tarefa']['projeto']['id'])->toBe($a->prj_id);

    $u = ($this->json)('update_tarefa', ['id' => $c['tarefa']['id'], 'status' => 'Feito', 'projeto' => 'Trabalho', 'data_entrega' => '']);
    expect($u['tarefa']['projeto_id'])->toBe($b->prj_id)->and($u['tarefa']['data_entrega'])->toBeNull();

    $s = ($this->json)('search_tarefas', ['status' => 'Feito', 'projetos' => ['trab']]);
    expect($s['tarefas'][0]['projeto']['name'])->toBe('Trabalho')->and($s['total_matches'])->toBe(1);

    expect(($this->erro)('update_tarefa', ['id' => $c['tarefa']['id']]))->toBe('Nenhum campo pra atualizar foi enviado -- envie ao menos um de: name, status, tipo, data_entrega, projeto.')
        ->and(($this->erro)('create_tarefa', ['name' => 'x']))->toBe('O parâmetro projeto é obrigatório -- toda tarefa do LifeOS pertence a um projeto.');
});

it('projetos: search por nome, status e tags', function () {
    Projeto::factory()->create(['prj_name' => 'Beta', 'prj_status' => 'Pausado', 'prj_tags' => ['Pessoal']]);
    Projeto::factory()->create(['prj_name' => 'Alfa', 'prj_status' => 'Feito', 'prj_tags' => ['Acadêmico', 'Pessoal']]);

    expect(($this->json)('search_projetos')['projetos'][0]['name'])->toBe('Alfa')
        ->and(($this->json)('search_projetos', ['tags' => ['Acadêmico']])['total_matches'])->toBe(1)
        ->and(($this->json)('search_projetos', ['tags' => ['Pessoal', 'Acadêmico']])['total_matches'])->toBe(2)
        ->and(($this->json)('search_projetos', ['status' => 'Pausado'])['projetos'][0]['name'])->toBe('Beta');
});

// ───────────────────────── eventos

it('eventos: create/search (sobreposição de datas)/update validando o par de datas', function () {
    $p = Projeto::factory()->create(['prj_name' => 'Faculdade']);
    $c = ($this->json)('create_evento', ['name' => 'Semana de provas', 'date' => '2026-10-05', 'date_fim' => '2026-10-09', 'tipo' => 'faculdade', 'projeto' => 'Faculdade']);
    expect($c['evento']['projeto'])->toBe(['id' => $p->prj_id, 'name' => 'Faculdade']);

    $s = ($this->json)('search_eventos', ['data_inicio' => '2026-10-07', 'data_fim' => '2026-10-31']);
    expect($s['total_matches'])->toBe(1);
    expect(($this->json)('search_eventos', ['data_inicio' => '2026-10-10'])['total_matches'])->toBe(0);

    expect(($this->erro)('update_evento', ['id' => $c['evento']['id'], 'date' => '2026-10-20']))->toBe('date_fim não pode ser anterior a date.');
    $u = ($this->json)('update_evento', ['id' => $c['evento']['id'], 'date_fim' => null, 'projeto' => null]);
    expect($u['evento']['projeto_id'])->toBeNull();
    expect(Evento::query()->first()->evt_date_fim)->toBeNull();

    expect(($this->erro)('create_evento', ['name' => 'x', 'date' => '10/10/2026', 'tipo' => 'vida']))->toBe('O parâmetro date é obrigatório e precisa estar no formato YYYY-MM-DD.')
        ->and(($this->erro)('create_evento', ['name' => 'x', 'date' => '2026-10-10', 'tipo' => 'nada']))->toContain('Tipo inválido: nada.');
});

// ───────────────────────── manifestações, citações

it('manifestações e citações', function () {
    Manifestacao::query()->create(['man_name' => 'Viajar', 'man_status' => 'Feito', 'man_tags' => ['Lazer']]);
    expect(($this->json)('search_manifestacoes', ['tags' => ['Lazer']])['manifestacoes'][0])->toBe([
        'id' => Manifestacao::query()->first()->man_id, 'name' => 'Viajar', 'status' => 'Feito', 'tags' => ['Lazer'], 'descricao' => null, 'banner_url' => null,
    ]);

    $c = ($this->json)('create_citacao', ['texto' => ' A *pressa* ', 'autor' => ' Alguém ']);
    expect($c['citacao']['texto'])->toBe('A *pressa*');
    expect(($this->json)('search_citacoes', ['autor' => 'algu'])['total_matches'])->toBe(1);
    expect(($this->erro)('create_citacao', ['texto' => str_repeat('a', 2001), 'autor' => 'x']))->toBe('O texto passa de 2000 caracteres -- uma citação precisa ser mais curta.');
});

// ───────────────────────── renúncias

it('renúncias: tempo sem, marcos, recorde e histórico, em hora de Brasília', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-08 15:00:00', 'UTC'));
    $r = Renuncia::factory()->create(['ren_nome' => 'Cigarro', 'ren_desde' => Carbon::parse('2026-09-01 12:00:00', 'UTC')]); // 37d 3h
    $r->tentativas()->create(['rtn_inicio' => Carbon::parse('2026-01-01 00:00:00', 'UTC'), 'rtn_fim' => Carbon::parse('2026-02-10 00:00:00', 'UTC')]); // 40d
    Renuncia::factory()->create(['ren_nome' => 'Velha', 'ren_arquivada' => true, 'ren_desde' => Carbon::parse('2026-01-01', 'UTC')]);

    $s = ($this->json)('search_renuncias', ['historico' => true]);

    expect($s['consultado_em'])->toBe('2026-10-08 12:00 (America/Sao_Paulo)')->and($s['total_matches'])->toBe(1);
    $c = $s['renuncias'][0];
    expect($c['tempo_sem'])->toBe('37d 3h')->and($c['dias_sem'])->toBe(37)->and($c['desde'])->toBe('2026-09-01 09:00')
        ->and($c['ultimo_marco'])->toBe('1 mês')->and($c['marcos_conquistados'])->toBe('6/15')
        ->and($c['proximo_marco']['marco'])->toBe('2 meses')->and($c['proximo_marco']['progresso_pct'])->toBe(62)
        ->and($c['recaidas'])->toBe(1)->and($c['recorde'])->toBe('40d 0h')->and($c['recorde_e_a_atual'])->toBeFalse()
        ->and($c['historico'][0])->toBe(['inicio' => '2025-12-31 21:00', 'fim' => '2026-02-09 21:00', 'duracao' => '40d 0h']);

    $todas = ($this->json)('search_renuncias', ['incluir_arquivadas' => true]);
    $velha = collect($todas['renuncias'])->firstWhere('nome', 'Velha');
    expect($velha['contagem'])->toBe('encerrada — arquivada, não conta tempo nem marcos')->and($velha['recorde'])->toBeNull();
    Carbon::setTestNow();
});

// ───────────────────────── memória

it('memória: create → list → get (com aviso) → add_registro → update_memoria → update_registro', function () {
    $c = ($this->json)('create_memoria', ['titulo' => 'Quem sou', 'descricao' => 'resumo do dono', 'categoria' => 'Perfil', 'registros' => ['fato 1'], 'origem' => 'claude.ai']);
    expect($c['memoria']['registros'])->toBe(1);

    expect(($this->erro)('create_memoria', ['titulo' => 'QUEM SOU', 'descricao' => 'x', 'categoria' => 'Perfil']))->toContain('Já existe a memória "Quem sou"');

    $l = ($this->json)('list_memorias', ['categoria' => 'Perfil']);
    expect($l['total'])->toBe(1)->and($l['memorias'][0]['registros'])->toBe(1);

    $g = ($this->json)('get_memoria', ['memorias' => ['quem', 'inexistente']]);
    expect($g['warnings'][0])->toContain('Nenhuma memória encontrada com "inexistente"')->and($g['memorias'][0]['registros'][0]['origem'])->toBe('claude.ai')
        ->and($g['memorias'][0]['registros'][0])->not->toHaveKey('editado_em');

    $a = ($this->json)('add_registro', ['memoria' => 'Quem sou', 'texto' => 'fato 2']);
    expect($a['registro']['origem'])->toBe('mcp');

    $u = ($this->json)('update_memoria', ['memoria' => 'quem sou', 'categoria' => 'Vida', 'titulo' => 'Quem eu sou']);
    expect($u['memoria']['titulo'])->toBe('Quem eu sou')->and($u['memoria']['categoria'])->toBe('Vida');

    Carbon::setTestNow(now()->addMinute());
    $r = ($this->json)('update_registro', ['id' => $a['registro']['id'], 'texto' => 'fato 2 corrigido']);
    expect($r['registro']['texto'])->toBe('fato 2 corrigido');
    $g2 = ($this->json)('get_memoria', ['memorias' => ['Quem eu sou']]);
    expect(collect($g2['memorias'][0]['registros'])->firstWhere('id', $a['registro']['id']))->toHaveKey('editado_em');
    Carbon::setTestNow();

    expect(($this->erro)('get_memoria', ['memorias' => []]))->toBe('O parâmetro memorias é obrigatório -- envie ao menos um título ou id.')
        ->and(($this->erro)('add_registro', ['memoria' => 'zzz', 'texto' => 'x']))->toContain('Nenhuma memória encontrada com "zzz"');
});

it('memória: referência ambígua lista os candidatos', function () {
    ($this->json)('create_memoria', ['titulo' => 'Projeto A', 'descricao' => 'x', 'categoria' => 'Projetos']);
    ($this->json)('create_memoria', ['titulo' => 'Projeto B', 'descricao' => 'x', 'categoria' => 'Projetos']);

    expect(($this->erro)('add_registro', ['memoria' => 'projeto', 'texto' => 'x']))->toBe('"projeto" é ambíguo -- bate com: Projeto A, Projeto B.');
});

// ───────────────────────── finanças

it('movimentações: create (direcao+meio) → search → update recompõe o tipo sem perder a outra metade', function () {
    $c = ($this->json)('create_movimentacao', ['name' => 'Mercado', 'valor' => 42.5, 'date' => '2026-10-07', 'direcao' => 'Saida', 'meio' => ['Pix']]);
    expect($c['movimentacao']['tipo'])->toBe(['Saida', 'Pix'])->and($c['movimentacao']['valor'])->toBe(42.5);

    $u = ($this->json)('update_movimentacao', ['id' => $c['movimentacao']['id'], 'meio' => ['Débito']]);
    expect($u['movimentacao']['tipo'])->toBe(['Saida', 'Débito']);
    $u = ($this->json)('update_movimentacao', ['id' => $c['movimentacao']['id'], 'direcao' => 'Entrada']);
    expect($u['movimentacao']['tipo'])->toBe(['Entrada', 'Débito']);

    $s = ($this->json)('search_movimentacoes', ['direcao' => 'Entrada', 'meio' => ['Débito', 'Pix'], 'valor_min' => 40, 'data_inicio' => '2026-10-01', 'data_fim' => '2026-10-31']);
    expect($s['total_matches'])->toBe(1);
    expect(($this->json)('search_movimentacoes', ['valor_max' => 10])['total_matches'])->toBe(0);

    expect(($this->erro)('search_movimentacoes', ['valor_min' => 'abc']))->toBe('valor_min e valor_max precisam ser números.')
        ->and(($this->erro)('create_movimentacao', ['name' => 'x', 'valor' => -1, 'date' => '2026-10-07', 'direcao' => 'Saida']))->toBe('O parâmetro valor é obrigatório e precisa ser um número não-negativo.')
        ->and(($this->erro)('update_movimentacao', ['id' => '00000000-0000-4000-8000-000000000000', 'meio' => ['Pix']]))->toBe('Nenhuma movimentação encontrada com id 00000000-0000-4000-8000-000000000000.');
});

it('search_movimentacoes: intervalo fechado de datas sobe o teto para 300', function () {
    Movimentacao::factory()->count(60)->create();

    expect(($this->json)('search_movimentacoes', ['limit' => 500])['returned'])->toBe(50)
        ->and(($this->json)('search_movimentacoes', ['limit' => 500, 'data_inicio' => '2026-10-01', 'data_fim' => '2026-10-31'])['returned'])->toBe(60);
});

it('resumo_financeiro: fluxo completo pelo MCP (hoje em Brasília, cadastro, intervalo)', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-08 15:00:00', 'UTC'));
    Movimentacao::factory()->create(['mov_name' => 'Salário', 'mov_valor' => 3000, 'mov_date' => '2026-10-05', 'mov_tipo' => ['Entrada', 'Pix']]);
    Movimentacao::factory()->create(['mov_name' => 'Mercado', 'mov_valor' => 200, 'mov_date' => '2026-10-06', 'mov_tipo' => ['Saida', 'Pix']]);
    Movimentacao::factory()->create(['mov_name' => 'Notebook', 'mov_valor' => 1000, 'mov_date' => '2026-10-07', 'mov_tipo' => ['Saida', 'Crédito']]);
    Recorrencia::factory()->create(['rec_nome' => 'Aluguel', 'rec_direcao' => 'Saida', 'rec_valor_min' => 900, 'rec_valor_max' => 1000]);

    $r = ($this->json)('resumo_financeiro');
    $m = $r['meses'][0];

    expect($r['hoje'])->toBe('2026-10-08')->and($m['mes'])->toBe('2026-10')->and($m['parcial'])->toBeTrue()
        ->and($m['entradas'])->toBe(3000)->and($m['saidas_caixa'])->toBe(200)->and($m['saidas_com_credito'])->toBe(1200)
        ->and($m['saldo'])->toBe(2800)->and($m['fatura_projetada'][0]['fatura'])->toBe('2026-11')
        ->and($m['compromissos']['fonte'])->toBe('cadastro')->and($r['projecao']['meses'])->toHaveCount(2);

    $i = ($this->json)('resumo_financeiro', ['de' => '2026-09', 'ate' => '2026-10']);
    expect($i['comparativo'])->toHaveCount(2)->and($i)->toHaveKey('comparativo_por_nome');

    $l = ($this->json)('resumo_financeiro', ['mes' => '2026-10', 'incluir_movimentacoes' => true]);
    expect($l['meses'][0]['lista_movimentacoes']['colunas'])->toBe(['id', 'data', 'nome', 'valor', 'direcao', 'meio', 'marca']);

    expect(($this->erro)('resumo_financeiro', ['mes' => '2026-13']))->toBe('Meses no formato YYYY-MM (ex.: 2026-09).')
        ->and(($this->erro)('resumo_financeiro', ['de' => '2026-10', 'ate' => '2026-09']))->toBe('O parâmetro ate não pode ser anterior a de.')
        ->and(($this->erro)('resumo_financeiro', ['de' => '2025-01', 'ate' => '2026-10']))->toBe('No máximo 12 meses por chamada.')
        ->and(($this->erro)('resumo_financeiro', ['de' => '2026-05', 'ate' => '2026-10', 'incluir_movimentacoes' => true]))->toContain('Com incluir_movimentacoes, no máximo 3 meses por chamada (o intervalo pedido tem 6)');
    Carbon::setTestNow();
});

it('resumo_financeiro via MCP bate com o TypeScript (mesmas movimentações, mesmo texto)', function () {
    $fixture = json_decode(file_get_contents(base_path('app-modules/financas/tests/Fixtures/resumo-financeiro/fixture.json')), true);
    $cenario = collect($fixture['cenarios'])->firstWhere('nome', 'cadastro_ago_com_lista');

    foreach ($fixture['movimentacoes'] as $m) {
        Movimentacao::query()->create([
            'mov_id' => $m['id'], 'mov_name' => $m['name'], 'mov_valor' => $m['valor'], 'mov_date' => $m['date'], 'mov_tipo' => $m['tipo'],
            'mov_created_at' => $m['created_at'], 'mov_updated_at' => $m['created_at'],
        ]);
    }
    foreach ($cenario['cad'] as $c) {
        Recorrencia::query()->create(['rec_nome' => $c['nome'], 'rec_direcao' => $c['direcao'], 'rec_valor_min' => $c['valor_min'], 'rec_valor_max' => $c['valor_max'], 'rec_meio' => $c['meio'], 'rec_dia' => $c['dia']]);
    }
    Carbon::setTestNow(Carbon::parse($cenario['hoje'].' 15:00:00', 'America/Sao_Paulo'));

    [$texto] = ($this->tool)('resumo_financeiro', $cenario['args']);

    expect($texto)->toBe($cenario['esperado']);
    Carbon::setTestNow();
});
