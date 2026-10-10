<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Modules\Core\Actions\Input\HttpInputAdapter;
use Modules\Core\Actions\Input\McpInputAdapter;

it('funde rota, query e corpo num array canônico com precedência corpo > query > rota', function () {
    $request = Request::create(
        uri: '/v1/orders?ref=da-query&only_query=q',
        method: 'POST',
        parameters: ['ref' => 'do-corpo', 'amount' => 100],
    );

    $input = HttpInputAdapter::from($request, ['id' => 'da-rota', 'ref' => 'da-rota']);

    expect($input['ref'])->toBe('do-corpo')      // corpo vence query e rota
        ->and($input['only_query'])->toBe('q')   // query presente
        ->and($input['id'])->toBe('da-rota')     // rota presente
        ->and($input['amount'])->toBe(100);
});

it('adapter MCP normaliza argumentos para o mesmo array canônico', function () {
    $input = McpInputAdapter::from(['ref' => 'abc', 'amount' => 50]);

    expect($input)->toBe(['ref' => 'abc', 'amount' => 50]);
});
