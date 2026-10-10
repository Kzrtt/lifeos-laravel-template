<?php

declare(strict_types=1);

namespace Modules\Financas\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Financas\Actions\CriarMovimentacao;
use Modules\Financas\Support\NotionPayloadAdapter;

/**
 * POST /functions/v1/lifeos-ingest?token=<senha mestre> — o webhook do celular.
 * Mesmo caminho, mesmo `?token=` na URL e mesmo payload do Notion: o atalho só
 * troca o host. A senha é validada pelo middleware `lifeos.senha`.
 */
final class IngestController
{
    public function __invoke(Request $request, CriarMovimentacao $action): JsonResponse
    {
        $mov = $action->execute(NotionPayloadAdapter::from($request));

        return response()->json(['ok' => true, 'id' => $mov->mov_id]);
    }
}
