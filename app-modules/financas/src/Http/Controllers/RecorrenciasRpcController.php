<?php

declare(strict_types=1);

namespace Modules\Financas\Http\Controllers;

use Modules\Core\Http\RpcController;
use Modules\Financas\Actions\AtualizarRecorrencia;
use Modules\Financas\Actions\CriarRecorrencia;
use Modules\Financas\Actions\ExcluirRecorrencia;
use Modules\Financas\Actions\ListarRecorrencias;
use Modules\Financas\Http\Resources\RecorrenciaResource;
use Modules\Financas\Models\Recorrencia;

/** POST /functions/v1/lifeos-recorrencias — query, create, update, delete. */
final class RecorrenciasRpcController extends RpcController
{
    protected function actions(): array
    {
        $uma = fn (Recorrencia $r): array => ['recorrencia' => RecorrenciaResource::make($r)];

        return [
            'query' => [ListarRecorrencias::class, fn ($rows): array => [
                'recorrencias' => $rows->map(fn (Recorrencia $r): array => RecorrenciaResource::make($r))->all(),
            ]],
            'create' => [CriarRecorrencia::class, $uma],
            'update' => [AtualizarRecorrencia::class, $uma],
            'delete' => [ExcluirRecorrencia::class, fn (string $id): array => ['id' => $id]],
        ];
    }
}
