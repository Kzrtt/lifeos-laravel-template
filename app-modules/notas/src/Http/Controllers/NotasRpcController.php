<?php

declare(strict_types=1);

namespace Modules\Notas\Http\Controllers;

use Modules\Core\Http\RpcController;
use Modules\Notas\Actions\AtualizarNota;
use Modules\Notas\Actions\CriarNota;
use Modules\Notas\Actions\ExcluirNota;
use Modules\Notas\Actions\ListarNotas;
use Modules\Notas\Http\Resources\NotaResource;
use Modules\Notas\Models\Nota;

/** POST /functions/v1/lifeos-notas — query, create, update, delete. */
final class NotasRpcController extends RpcController
{
    protected function actions(): array
    {
        $uma = fn (Nota $n): array => ['nota' => NotaResource::make($n)];

        return [
            'query' => [ListarNotas::class, fn ($rows): array => [
                'notas' => $rows->map(fn (Nota $n): array => NotaResource::make($n))->all(),
            ]],
            'create' => [CriarNota::class, $uma],
            'update' => [AtualizarNota::class, $uma],
            'delete' => [ExcluirNota::class, fn (string $id): array => ['id' => $id]],
        ];
    }
}
