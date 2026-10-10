<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    [$this->entity, $this->senha] = lifeosInstance();
    $this->entity->update(['ent_sub' => 'gestão de vida', 'ent_tema' => 'abismo']);
    config(['lifeos.entity_slug' => $this->entity->ent_slug]);
});

function configJs(object $teste): array
{
    $js = $teste->get('/assets/js/lifeos-config.js')->assertOk()->getContent();
    expect($js)->toStartWith('window.LIFEOS_CONFIG = ')->toEndWith(";\n");

    return json_decode(substr($js, strlen('window.LIFEOS_CONFIG = '), -2), true, 512, JSON_THROW_ON_ERROR);
}

it('lifeos-config.js: mesmo formato e mesmas chaves, apontando para o próprio domínio', function () {
    $c = configJs($this);

    expect(array_keys($c))->toBe(['supabaseUrl', 'anonKey', 'identidade', 'blog', 'tema', 'hubCapa', 'sessionKey'])
        ->and($c['supabaseUrl'])->toBe(rtrim(url('/'), '/'))->and($c['anonKey'])->toBe('')
        ->and($c['identidade'])->toBe(['nome' => $this->entity->ent_name, 'sub' => 'gestão de vida', 'banner' => '../assets/images/banner_lifeos.png', 'avatar' => '../assets/images/profile_lifeos.jpeg'])
        ->and($c['blog'])->toBe(['habilitado' => false])->and($c['tema'])->toBe('abismo')->and($c['hubCapa'])->toBe('classica')
        ->and($c['sessionKey'])->toBe('financas_master');
});

it('a identidade vem da entidade (já prepara a segunda instância)', function () {
    $this->entity->update(['ent_name' => 'Outro LifeOS', 'ent_tema' => 'vinho', 'ent_hub_capa' => 'imersiva', 'ent_blog_habilitado' => false, 'ent_banner' => '/x.png']);

    $c = configJs($this);

    expect($c['identidade']['nome'])->toBe('Outro LifeOS')->and($c['identidade']['banner'])->toBe('/x.png')
        ->and($c['tema'])->toBe('vinho')->and($c['hubCapa'])->toBe('imersiva')->and($c['blog'])->toBe(['habilitado' => false]);
});

it('sem instância configurada, o config sai com os padrões em vez de quebrar', function () {
    config(['lifeos.entity_slug' => 'nao-existe']);

    $c = configJs($this);

    expect($c['identidade']['nome'])->toBe('LifeOS')->and($c['tema'])->toBe('abismo');
});

it('docs-site: serve as páginas e os assets públicos, com o tipo certo', function () {
    $this->get('/docs-site')->assertRedirect('/docs-site/index.html');
    $index = $this->get('/docs-site/index.html')->assertOk()->assertHeader('Content-Type', 'text/html; charset=utf-8')->getFile()->getContent();
    expect($index)->toContain('LifeOS · Mapa do Sistema');
    $this->get('/docs-site/features/notas.html')->assertOk();
    $this->get('/docs-site/assets/site.css')->assertOk()->assertHeader('Content-Type', 'text/css; charset=utf-8');
});

it('docs-site: não vaza nada fora da pasta nem arquivos de outro tipo', function () {
    $this->get('/docs-site/../composer.json')->assertNotFound();
    $this->get('/docs-site/..%2Fcomposer.json')->assertNotFound();
    $this->get('/docs-site/assets/../../.env')->assertNotFound();
    $this->get('/docs-site/inexistente.html')->assertNotFound();
});
