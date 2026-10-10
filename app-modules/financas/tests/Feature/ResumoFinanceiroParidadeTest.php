<?php

declare(strict_types=1);

use Modules\Core\Support\Mcp\Json;
use Modules\Financas\Services\ResumoFinanceiroService;

/*
| Paridade com o TypeScript original: o fixture traz as movimentações e, para cada
| cenário, a saída que o código de hoje (Node) produziu. O PHP precisa dar o mesmo
| texto, centavo a centavo.
*/

const MEIOS_PADRAO = ['Crédito', 'Débito', 'Pix', 'Vale', 'Boleto'];

function fixtureResumo(): array
{
    return json_decode(file_get_contents(__DIR__.'/../Fixtures/resumo-financeiro/fixture.json'), true, 512, JSON_THROW_ON_ERROR);
}

/** Replica o miolo de handleResumoFinanceiro com os dados em memória. */
function gerarResumo(array $movs, array $cenario): string
{
    $args = $cenario['args'];
    $hoje = $cenario['hoje'];
    $mesHoje = substr($hoje, 0, 7);
    $proximo = function (string $ym): string {
        [$y, $m] = array_map('intval', explode('-', $ym));

        return $m === 12 ? ($y + 1).'-01' : $y.'-'.str_pad((string) ($m + 1), 2, '0', STR_PAD_LEFT);
    };

    $de = (string) ($args['de'] ?? $args['mes'] ?? $mesHoje);
    $ate = (string) ($args['ate'] ?? $args['mes'] ?? $de);
    $incluir = ($args['incluir_movimentacoes'] ?? false) === true;
    $meses = [];
    for ($ym = $de; strcmp($ym, $ate) <= 0; $ym = $proximo($ym)) {
        $meses[] = $ym;
    }
    $limite = $proximo($proximo($proximo(strcmp($ate, $mesHoje) > 0 ? $ate : $mesHoje))).'-01';

    $todas = [];
    foreach ($movs as $i => $m) {
        if (strcmp($m['date'], $limite) < 0) {
            $todas[] = [...$m, '_i' => $i, 'valor' => (float) $m['valor']];
        }
    }

    $aberturas = [];
    foreach (array_unique([...$meses, $mesHoje]) as $ym) {
        $c = 0;
        foreach ($movs as $m) {
            if (strcmp($m['date'], $ym.'-01') >= 0) {
                continue;
            }
            $v = (int) round($m['valor'] * 100);
            if (in_array('Entrada', $m['tipo'], true)) {
                $c += $v;
            } elseif (in_array('Saida', $m['tipo'], true) && ! in_array('Crédito', $m['tipo'], true)) {
                $c -= $v;
            }
        }
        $aberturas[$ym] = $c / 100;
    }

    $servico = new ResumoFinanceiroService($todas, $hoje, MEIOS_PADRAO, $cenario['cad']);

    return Json::pretty($servico->gerar($meses, $aberturas, $incluir));
}

it('paridade com o TypeScript: cenário {nome}', function (string $nome) {
    $fixture = fixtureResumo();
    $cenario = collect($fixture['cenarios'])->firstWhere('nome', $nome);

    $obtido = gerarResumo($fixture['movimentacoes'], $cenario);

    // Em caso de divergência, o diff do Pest aponta a primeira linha diferente.
    expect($obtido)->toBe($cenario['esperado']);
})->with(array_map(fn (array $c): string => $c['nome'], json_decode(file_get_contents(__DIR__.'/../Fixtures/resumo-financeiro/fixture.json'), true)['cenarios']));
