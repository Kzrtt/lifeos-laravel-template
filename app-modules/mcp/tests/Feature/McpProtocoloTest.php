<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Vocabularios\Services\VocabularioService;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    [$this->entity, $this->senha] = lifeosInstance();
    app(VocabularioService::class)->semear($this->entity->ent_id);
    $this->token = lifeosMcpToken();
});

it('recusa antes de ler o corpo: sem token, token errado, e sem token configurado', function () {
    $this->postJson('/functions/v1/lifeos-mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertStatus(401)->assertExactJson(['error' => 'unauthorized']);
    mcp('errado', 'tools/list')->assertStatus(401)->assertExactJson(['error' => 'unauthorized']);
    // a senha mestre NÃO é o token do conector
    mcp($this->senha, 'tools/list')->assertStatus(401);
});

it('GET não é permitido', function () {
    $this->getJson('/functions/v1/lifeos-mcp/'.$this->token)->assertStatus(405);
});

it('initialize devolve versão, capacidades e as instructions com o índice da memória', function () {
    $r = mcp($this->token, 'initialize', ['protocolVersion' => '2025-03-26'])->assertOk();

    expect($r->json('jsonrpc'))->toBe('2.0')->and($r->json('id'))->toBe(1)
        ->and($r->json('result.protocolVersion'))->toBe('2025-03-26')
        ->and($r->json('result.serverInfo'))->toBe(['name' => 'lifeos-mcp', 'version' => '2.7.2'])
        ->and($r->json('result.capabilities'))->toHaveKey('tools')
        ->and($r->json('result.instructions'))->toContain('MEMÓRIA DE LONGO PRAZO')
        ->and($r->json('result.instructions'))->toContain('Índice de memória: vazio');

    mcp($this->token, 'initialize')->assertJsonPath('result.protocolVersion', '2025-06-18');
});

it('o índice das instructions agrupa por categoria e acompanha o que foi escrito', function () {
    mcpTool($this->token, 'create_memoria', ['titulo' => 'Quem sou', 'descricao' => 'resumo', 'categoria' => 'Perfil', 'registros' => ['a', 'b']]);
    mcpTool($this->token, 'create_memoria', ['titulo' => 'Stack', 'descricao' => 'stack técnica', 'categoria' => 'Projetos', 'registros' => ['x']]);

    $texto = mcp($this->token, 'initialize')->json('result.instructions');

    expect($texto)->toContain("Índice de memória:\n[Perfil]\n- Quem sou — resumo (2 registros)")
        ->and($texto)->toContain("[Projetos]\n- Stack — stack técnica (1 registro)");
});

it('ping, notificação, método e tool desconhecidos, corpo ilegível', function () {
    mcp($this->token, 'ping')->assertOk()->assertJsonPath('result', []);

    $this->postJson('/functions/v1/lifeos-mcp/'.$this->token, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'])->assertStatus(202);

    mcp($this->token, 'resources/list')->assertOk()->assertJsonPath('error.code', -32601)->assertJsonPath('error.message', 'Method not found: resources/list');
    mcp($this->token, 'tools/call', ['name' => 'apagar_tudo'])->assertOk()->assertJsonPath('error.code', -32602)->assertJsonPath('error.message', 'Unknown tool: apagar_tudo');

    $this->call('POST', '/functions/v1/lifeos-mcp/'.$this->token, [], [], [], ['CONTENT_TYPE' => 'application/json'], '{quebrado')
        ->assertOk()->assertJsonPath('error.code', -32700)->assertJsonPath('error.message', 'Parse error');
});

it('o token do conector resolve a instância certa (cada uma tem o seu)', function () {
    $outra = Modules\Tenancy\Models\Entity::factory()->create();
    Modules\Core\Tenancy\TenantContext::runAs($outra->ent_id, function () {
        app(Modules\Acesso\Services\SegredoService::class)->set('mcp_token', 'token-da-outra-instancia-9999');
        app(VocabularioService::class)->semear(Modules\Core\Tenancy\TenantContext::id());
        Modules\Citacoes\Models\Citacao::query()->create(['cit_texto' => 'DA OUTRA', 'cit_autor' => 'x']);
    });
    Modules\Core\Tenancy\TenantContext::runAs($this->entity->ent_id, fn () => Modules\Citacoes\Models\Citacao::query()->create(['cit_texto' => 'MINHA', 'cit_autor' => 'y']));

    [$minha] = mcpTool($this->token, 'search_citacoes');
    [$delas] = mcpTool('token-da-outra-instancia-9999', 'search_citacoes');

    expect($minha)->toContain('MINHA')->not->toContain('DA OUTRA')
        ->and($delas)->toContain('DA OUTRA')->not->toContain('MINHA');
});

it('nenhuma tool apaga: nenhuma classe em Mcp/Tools usa uma Action Excluir*', function () {
    $excluir = collect(glob(base_path('app-modules/*/src/Actions/Excluir*.php')))
        ->map(fn (string $f): string => 'Modules\\'.Str::studly(basename(dirname($f, 3))).'\\Actions\\'.basename($f, '.php'))->all();

    expect($excluir)->not->toBeEmpty();
    foreach (glob(base_path('app-modules/*/src/Mcp/Tools/*.php')) as $arquivo) {
        $codigo = file_get_contents($arquivo);
        foreach ($excluir as $classe) {
            expect($codigo)->not->toContain(class_basename($classe));
        }
    }
});
