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

/*
| O tools/list de hoje (extraído do TypeScript do Supabase com o vocabulário
| padrão) é o arquivo de referência. O novo precisa bater: nomes, ordem,
| descrições e schemas.
*/
it('tools/list é idêntico ao de referência', function () {
    $referencia = json_decode(file_get_contents(base_path('tests/Fixtures/mcp/tools-list.reference.json')), true, 512, JSON_THROW_ON_ERROR);
    $obtido = mcp($this->token, 'tools/list')->assertOk()->json('result.tools');

    expect(array_column($obtido, 'name'))->toBe(array_column($referencia, 'name'))->and(count($obtido))->toBe(24);

    foreach ($referencia as $i => $tool) {
        expect($obtido[$i])->toEqual($tool, "tool {$tool['name']} divergiu");
    }
});

it('os enum dos schemas acompanham o vocabulário editado', function () {
    rpc('lifeos-vocabularios', ['token' => $this->senha, 'action' => 'create', 'dominio' => 'nota_tipo', 'valor' => 'Diário'])->assertOk();

    $tool = collect(mcp($this->token, 'tools/list')->json('result.tools'))->firstWhere('name', 'create_nota');

    expect($tool['inputSchema']['properties']['tipo']['items']['enum'])->toContain('Diário');
});

it('cada tool tem nome único e schema de objeto', function () {
    $tools = mcp($this->token, 'tools/list')->json('result.tools');

    expect(array_unique(array_column($tools, 'name')))->toHaveCount(24);
    foreach ($tools as $t) {
        expect($t['inputSchema']['type'])->toBe('object')->and($t['description'])->not->toBeEmpty();
    }
});
