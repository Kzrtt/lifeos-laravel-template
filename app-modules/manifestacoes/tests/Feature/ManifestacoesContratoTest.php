<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Vocabularios\Services\VocabularioService;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    [$this->entity, $this->senha] = lifeosInstance();
    app(VocabularioService::class)->semear($this->entity->ent_id);
    Storage::fake('public');
});

it('cria sem banner e lista', function () {
    $r = rpc('lifeos-manifestacoes', ['token' => $this->senha, 'action' => 'create', 'manifestacao' => ['name' => 'Casa', 'status' => 'Em Progresso', 'tags' => ['Vida']]])->assertOk();
    expect(array_keys($r->json('manifestacao')))->toBe(['id', 'name', 'status', 'tags', 'banner_url', 'descricao'])
        ->and($r->json('manifestacao.banner_url'))->toBeNull()
        ->and($r->json())->not->toHaveKey('banner_error');

    rpc('lifeos-manifestacoes', ['token' => $this->senha])->assertJsonCount(1, 'manifestacoes');
});

it('grava o banner no disco e devolve a URL', function () {
    $png = base64_encode('conteudo-da-imagem');
    $r = rpc('lifeos-manifestacoes', ['token' => $this->senha, 'action' => 'create', 'manifestacao' => ['name' => 'Casa', 'status' => 'Feito'], 'banner_base64' => $png, 'banner_content_type' => 'image/png'])->assertOk();

    $id = $r->json('manifestacao.id');
    Storage::disk('public')->assertExists("manifestacoes/{$id}.png");
    expect($r->json('manifestacao.banner_url'))->toBe("/storage/manifestacoes/{$id}.png");
});

it('imagem quebrada não derruba a criação: banner_error', function () {
    rpc('lifeos-manifestacoes', ['token' => $this->senha, 'action' => 'create', 'manifestacao' => ['name' => 'a', 'status' => 'Feito'], 'banner_base64' => '@@@não-é-base64@@@'])
        ->assertOk()->assertJsonPath('banner_error', true)->assertJsonPath('manifestacao.banner_url', null);
});

it('mesmos códigos de erro', function (array $body, string $erro) {
    rpc('lifeos-manifestacoes', ['token' => $this->senha, 'action' => 'create', ...$body])->assertStatus(400)->assertJsonPath('error', $erro);
})->with([
    [[], 'missing_manifestacao'],
    [['manifestacao' => ['name' => '', 'status' => 'Feito']], 'invalid_name'],
    [['manifestacao' => ['name' => 'a', 'status' => 'x']], 'invalid_status'],
    [['manifestacao' => ['name' => 'a', 'status' => 'Feito', 'tags' => ['x']]], 'invalid_tags'],
    [['manifestacao' => ['name' => 'a', 'status' => 'Feito'], 'banner_base64' => str_repeat('a', 6 * 1024 * 1024 + 1)], 'banner_too_large'],
]);
