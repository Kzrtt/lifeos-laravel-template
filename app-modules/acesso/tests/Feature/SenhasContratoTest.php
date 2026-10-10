<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Acesso\Models\AccessToken;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    [$this->entity, $this->senha] = lifeosInstance();
});

it('recusa sem senha, com senha errada e com senha não-mestre', function () {
    rpc('lifeos-senhas', ['action' => 'query'])
        ->assertStatus(400)->assertExactJson(['ok' => false, 'error' => 'missing_token']);

    rpc('lifeos-senhas', ['token' => 'errada', 'action' => 'query'])
        ->assertStatus(401)->assertExactJson(['ok' => false, 'error' => 'unauthorized']);

    app(Modules\Acesso\Services\TokenService::class)->create('so-de-pagina-123', 'visita', false);
    rpc('lifeos-senhas', ['token' => 'so-de-pagina-123', 'action' => 'query'])
        ->assertStatus(401);
});

it('GET nas functions devolve method_not_allowed', function () {
    $this->getJson('/functions/v1/lifeos-senhas')
        ->assertStatus(405)->assertExactJson(['ok' => false, 'error' => 'method_not_allowed']);
});

it('lista as senhas só com máscara, nunca o valor', function () {
    $r = rpc('lifeos-senhas', ['token' => $this->senha, 'action' => 'query'])->assertOk();

    expect($r->json('ok'))->toBeTrue()
        ->and($r->json('min_token_len'))->toBe(12)
        ->and($r->json('senhas.0.is_master'))->toBeTrue()
        ->and($r->json('senhas.0.token_masked'))->toBe('senh…este')
        ->and($r->json('senhas.0.token_len'))->toBe(strlen($this->senha))
        ->and($r->json('senhas.0.curta'))->toBeFalse()
        ->and($r->getContent())->not->toContain($this->senha);
});

it('guarda a senha como hash, nunca em claro', function () {
    $linha = AccessToken::query()->first();

    expect($linha->tok_hash)->not->toContain($this->senha)
        ->and(password_verify($this->senha, $linha->tok_hash))->toBeTrue();
});

it('cria senha, avisa quando curta e recusa duplicata', function () {
    $criar = fn (string $valor) => rpc('lifeos-senhas', [
        'token' => $this->senha, 'action' => 'create',
        'senha' => ['token' => $valor, 'label' => 'Visita'],
    ]);

    $criar('curta')->assertOk()->assertJsonPath('warning', 'token_curto')->assertJsonPath('senha.label', 'Visita');
    $criar('curta')->assertStatus(409)->assertExactJson(['ok' => false, 'error' => 'duplicate_token']);
    $criar('   ')->assertStatus(400)->assertJsonPath('error', 'invalid_token');

    rpc('lifeos-senhas', ['token' => $this->senha, 'action' => 'create'])
        ->assertStatus(400)->assertJsonPath('error', 'missing_senha');
});

it('protege a última senha mestre e a senha de quem chama', function () {
    $id = AccessToken::query()->first()->tok_id;

    rpc('lifeos-senhas', ['token' => $this->senha, 'action' => 'delete', 'id' => $id])
        ->assertStatus(409)->assertJsonPath('error', 'self_delete');

    rpc('lifeos-senhas', ['token' => $this->senha, 'action' => 'update', 'id' => $id, 'patch' => ['is_master' => false]])
        ->assertStatus(409)->assertJsonPath('error', 'last_master');
});

it('pede reauth ao trocar a própria senha, e a nova passa a valer', function () {
    $id = AccessToken::query()->first()->tok_id;

    rpc('lifeos-senhas', ['token' => $this->senha, 'action' => 'update', 'id' => $id, 'patch' => ['token' => 'outra-senha-bem-longa']])
        ->assertOk()->assertJsonPath('reauth', true);

    rpc('lifeos-senhas', ['token' => $this->senha, 'action' => 'query'])->assertStatus(401);
    rpc('lifeos-senhas', ['token' => 'outra-senha-bem-longa', 'action' => 'query'])->assertOk();
});

it('concede e revoga páginas só a senhas não-mestre', function () {
    $mestre = AccessToken::query()->first()->tok_id;
    rpc('lifeos-senhas', ['token' => $this->senha, 'action' => 'grant', 'id' => $mestre, 'page_slug' => 'retrato'])
        ->assertStatus(409)->assertJsonPath('error', 'master_no_scope');

    $visita = rpc('lifeos-senhas', ['token' => $this->senha, 'action' => 'create', 'senha' => ['token' => 'visita-senha-123']])
        ->json('senha.id');

    $grant = rpc('lifeos-senhas', ['token' => $this->senha, 'action' => 'grant', 'id' => $visita, 'page_slug' => 'retrato'])
        ->assertOk()->json('grant.id');

    rpc('lifeos-senhas', ['token' => $this->senha, 'action' => 'grant', 'id' => $visita, 'page_slug' => 'retrato'])
        ->assertStatus(409)->assertJsonPath('error', 'duplicate_grant');

    $this->postJson('/rest/v1/rpc/check_page_access', ['p_token' => 'visita-senha-123', 'p_page' => 'retrato'])
        ->assertOk()->assertExactJson([true]);
});
