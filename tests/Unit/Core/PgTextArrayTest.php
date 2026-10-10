<?php

declare(strict_types=1);

use Modules\Core\Casts\PgTextArray;

it('lê o literal de array do Postgres, com vírgulas, aspas e barras', function (string $literal, array $esperado) {
    expect(PgTextArray::parse($literal))->toBe($esperado);
})->with([
    'vazio' => ['{}', []],
    'simples' => ['{a,b,c}', ['a', 'b', 'c']],
    'com espaço (precisa de aspas)' => ['{"Análise de Leitura",Vida}', ['Análise de Leitura', 'Vida']],
    'com vírgula' => ['{"a,b",c}', ['a,b', 'c']],
    'com aspas escapadas' => ['{"c\\"d"}', ['c"d']],
    'com barra' => ['{"a\\\\b"}', ['a\\b']],
    'unicode' => ['{Lembranças,"Crédito"}', ['Lembranças', 'Crédito']],
    'string "NULL" entre aspas é texto' => ['{"NULL",x}', ['NULL', 'x']],
]);

it('ida e volta: o que sai de literal() entra igual em parse()', function () {
    $valores = ['Vida', 'Análise de Leitura', 'a,b', 'c"d', 'x\\y', "com 'aspa simples'", '{chaves}', ''];

    expect(PgTextArray::parse(PgTextArray::literal($valores)))->toBe($valores);
});
