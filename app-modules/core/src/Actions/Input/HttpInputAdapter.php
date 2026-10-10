<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Input;

use Illuminate\Http\Request;

/**
 * Adapta uma Request HTTP (API REST) para o **array canônico** que a Action espera.
 *
 * É um dos tradutores de entrada (o outro incluído no template é o McpInputAdapter).
 * A Action não sabe de qual canal o dado veio — ela só recebe um array. Adicionar
 * um canal novo (CLI, fila interna, GraphQL) é escrever mais um adapter; nenhuma
 * regra de negócio muda. É isso que garante zero regra duplicada entre canais.
 */
final class HttpInputAdapter
{
    /**
     * Funde os parâmetros de rota, a query string e o corpo num único array. A
     * precedência (corpo > query > rota) cobre o caso comum de uma chave de
     * idempotência chegar pela query (`POST /v1/orders?ref=...`) e os dados do
     * recurso no corpo.
     *
     * @param  array<string, mixed>  $routeParams  parâmetros vindos da rota (ex.: {id})
     * @return array<string, mixed>
     */
    public static function from(Request $request, array $routeParams = []): array
    {
        return [
            ...$routeParams,
            ...$request->query(),
            ...$request->all(),
        ];
    }
}
