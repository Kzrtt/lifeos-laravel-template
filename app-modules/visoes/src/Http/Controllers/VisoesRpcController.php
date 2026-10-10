<?php

declare(strict_types=1);

namespace Modules\Visoes\Http\Controllers;

use Modules\Core\Http\RpcController;
use Modules\Visoes\Actions\AtualizarVisao;
use Modules\Visoes\Actions\CriarVisao;
use Modules\Visoes\Actions\ExcluirVisao;
use Modules\Visoes\Actions\ListarVisoes;
use Modules\Visoes\Http\Resources\VisaoResource;
use Modules\Visoes\Models\Visao;

/** POST /functions/v1/lifeos-views — query (exige `tabela`), create, update, delete. */
final class VisoesRpcController extends RpcController
{
    protected function actions(): array
    {
        $uma = fn (Visao $v): array => ['view' => VisaoResource::make($v)];

        return [
            'query' => [ListarVisoes::class, fn ($rows): array => [
                'views' => $rows->map(fn (Visao $v): array => VisaoResource::make($v))->all(),
            ]],
            'create' => [CriarVisao::class, $uma],
            'update' => [AtualizarVisao::class, $uma],
            'delete' => [ExcluirVisao::class, fn (string $id): array => ['id' => $id]],
        ];
    }
}
