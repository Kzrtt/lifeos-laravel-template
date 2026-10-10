<?php

declare(strict_types=1);

namespace Modules\Acesso\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Acesso\Actions\VerificarAcessoAPagina;

/**
 * POST /rest/v1/rpc/check_page_access — compat com o PostgREST: o corpo é
 * `{p_token, p_page}` e a resposta é o JSON cru `true` / `false`, sem envelope.
 */
final class PageAccessController
{
    public function __invoke(Request $request, VerificarAcessoAPagina $action): JsonResponse
    {
        return response()->json($action->execute([
            'p_token' => $request->input('p_token'),
            'p_page' => $request->input('p_page'),
        ]));
    }
}
