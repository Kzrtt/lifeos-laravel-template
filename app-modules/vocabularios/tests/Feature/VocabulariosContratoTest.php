<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\ConsumidorDeVocabulario;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Vocabularios\Models\Vocabulario;
use Modules\Vocabularios\Services\VocabularioService;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    [$this->entity, $this->senha] = lifeosInstance();
    app(VocabularioService::class)->semear($this->entity->ent_id);
});

it('lista tudo agrupado por domínio, com os 11 domínios do seed', function () {
    $r = rpc('lifeos-vocabularios', ['token' => $this->senha])->assertOk();

    expect($r->json('dominios'))->toHaveCount(11)
        ->and($r->json('dominios.mov_meio.array'))->toBeTrue()
        ->and($r->json('vocabularios.tarefa_status.0.valor'))->toBe('Não Iniciado')
        ->and($r->json('vocabularios.tarefa_status.0.protegido'))->toBeTrue()
        ->and($r->json('vocabularios.evento_tipo.0.cor'))->toBe('#5b8def')
        ->and(array_keys($r->json('vocabularios.nota_tipo.0')))->toBe(['id', 'valor', 'cor', 'ordem', 'protegido', 'uso']);
});

it('o read model devolve na ordem e cai no padrão se o domínio estiver vazio', function () {
    expect(app(VocabularioReadModel::class)->valores('mov_direcao'))->toBe(['Entrada', 'Saida']);

    Vocabulario::query()->where('voc_dominio', 'mov_direcao')->get()->each->delete();
    expect(app(VocabularioReadModel::class)->valores('mov_direcao'))->toBe(['Entrada', 'Saida']);
});

it('cria valores e recusa duplicata, domínio e cor inválidos', function () {
    $criar = fn (array $b) => rpc('lifeos-vocabularios', ['token' => $this->senha, 'action' => 'create', ...$b]);

    $criar(['dominio' => 'nota_tipo', 'valor' => 'Novo', 'cor' => '#ffffff'])
        ->assertOk()->assertJsonPath('vocabulario.valor', 'Novo')->assertJsonPath('vocabulario.cor', null)->assertJsonPath('vocabulario.uso', 0);
    $criar(['dominio' => 'evento_tipo', 'valor' => 'estudo', 'cor' => '#112233'])->assertJsonPath('vocabulario.cor', '#112233');
    $criar(['dominio' => 'nota_tipo', 'valor' => 'Novo'])->assertStatus(409)->assertJsonPath('error', 'valor_duplicado');
    $criar(['dominio' => 'xx', 'valor' => 'a'])->assertStatus(400)->assertJsonPath('error', 'dominio_invalido');
    $criar(['dominio' => 'nota_tipo', 'valor' => ' '])->assertStatus(400)->assertJsonPath('error', 'valor_vazio');
    $criar(['dominio' => 'evento_tipo', 'valor' => 'x', 'cor' => 'azul'])->assertStatus(400)->assertJsonPath('error', 'cor_invalida');
});

it('protegidos não se apagam; livres sem uso se apagam', function () {
    $protegido = Vocabulario::query()->where('voc_dominio', 'tarefa_status')->where('voc_valor', 'Feito')->first();
    rpc('lifeos-vocabularios', ['token' => $this->senha, 'action' => 'delete', 'id' => $protegido->voc_id])
        ->assertStatus(409)->assertJsonPath('error', 'protegido');

    $livre = Vocabulario::query()->where('voc_valor', 'Vale')->first();
    rpc('lifeos-vocabularios', ['token' => $this->senha, 'action' => 'delete', 'id' => $livre->voc_id])
        ->assertOk()->assertJsonPath('id', $livre->voc_id);
});

it('renomear passa pelos consumidores dentro da transação', function () {
    $consumidor = new class implements ConsumidorDeVocabulario
    {
        /** @var list<array{string, string, string}> */
        public array $chamadas = [];

        public function dominios(): array
        {
            return ['mov_meio'];
        }

        public function contarUso(string $dominio, string $valor): int
        {
            return $valor === 'Pix' ? 3 : 0;
        }

        public function renomear(string $dominio, string $de, string $para): int
        {
            $this->chamadas[] = [$dominio, $de, $para];

            return 7;
        }
    };
    app()->instance('consumidor.fake', $consumidor);
    app()->tag('consumidor.fake', ConsumidorDeVocabulario::TAG);

    $vida = Vocabulario::query()->where('voc_dominio', 'mov_meio')->where('voc_valor', 'Pix')->first();

    rpc('lifeos-vocabularios', ['token' => $this->senha, 'action' => 'delete', 'id' => $vida->voc_id])
        ->assertStatus(409)->assertExactJson(['ok' => false, 'error' => 'em_uso', 'uso' => 3]);

    rpc('lifeos-vocabularios', ['token' => $this->senha, 'action' => 'update', 'id' => $vida->voc_id, 'novo_valor' => 'PIX'])
        ->assertOk()->assertJsonPath('vocabulario.valor', 'PIX')->assertJsonPath('linhas_migradas', 7);
    expect($consumidor->chamadas)->toBe([['mov_meio', 'Pix', 'PIX']]);

    rpc('lifeos-vocabularios', ['token' => $this->senha, 'action' => 'update', 'id' => $vida->voc_id, 'novo_valor' => 'Boleto'])
        ->assertStatus(409)->assertJsonPath('error', 'valor_duplicado');
});

it('reordena em lote e valida o formato', function () {
    $a = Vocabulario::query()->where('voc_valor', 'Vale')->first();
    rpc('lifeos-vocabularios', ['token' => $this->senha, 'action' => 'reorder', 'ordens' => [['id' => $a->voc_id, 'ordem' => 5]]])
        ->assertOk()->assertJsonPath('total', 1);
    expect($a->fresh()->voc_ordem)->toBe(5);

    rpc('lifeos-vocabularios', ['token' => $this->senha, 'action' => 'reorder', 'ordens' => []])->assertJsonPath('error', 'ordens_vazio');
    rpc('lifeos-vocabularios', ['token' => $this->senha, 'action' => 'reorder', 'ordens' => [['id' => 'x']]])->assertJsonPath('error', 'ordens_invalido');
});
