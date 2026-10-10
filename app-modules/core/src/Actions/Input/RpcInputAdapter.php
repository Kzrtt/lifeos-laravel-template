<?php

declare(strict_types=1);

namespace Modules\Core\Actions\Input;

use Illuminate\Http\Request;

/**
 * Tradutor do envelope do LifeOS (`POST {token, action, ...}`) para o array
 * canônico da Action. `token` e `action` são do transporte, não do caso de uso:
 * saem antes de chegar à Action. Irmão do HttpInputAdapter e do McpInputAdapter.
 */
final class RpcInputAdapter
{
    /** @return array<string, mixed> */
    public static function from(Request $request): array
    {
        $body = $request->json()->all() ?: $request->post();

        unset($body['token'], $body['action']);

        return $body;
    }

    public static function action(Request $request, string $default = 'query'): string
    {
        $action = trim((string) $request->input('action', $default));

        return $action === '' ? $default : $action;
    }
}
