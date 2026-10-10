<?php

declare(strict_types=1);

namespace Modules\Manifestacoes\Http\Controllers;

use Modules\Core\Http\RpcController;
use Modules\Manifestacoes\Actions\CriarManifestacao;
use Modules\Manifestacoes\Actions\ListarManifestacoes;
use Modules\Manifestacoes\Http\Resources\ManifestacaoResource;
use Modules\Manifestacoes\Models\Manifestacao;

/** POST /functions/v1/lifeos-manifestacoes — query e create (sem update/delete, como hoje). */
final class ManifestacoesRpcController extends RpcController
{
    protected function actions(): array
    {
        return [
            'query' => [ListarManifestacoes::class, fn ($rows): array => [
                'manifestacoes' => $rows->map(fn (Manifestacao $m): array => ManifestacaoResource::make($m))->all(),
            ]],
            'create' => [CriarManifestacao::class, fn (array $r): array => [
                'manifestacao' => ManifestacaoResource::make($r['manifestacao']),
                ...($r['banner_error'] ? ['banner_error' => true] : []),
            ]],
        ];
    }
}
