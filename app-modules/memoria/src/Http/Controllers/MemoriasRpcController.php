<?php

declare(strict_types=1);

namespace Modules\Memoria\Http\Controllers;

use Modules\Core\Http\RpcController;
use Modules\Memoria\Actions\AtualizarMemoria;
use Modules\Memoria\Actions\AtualizarRegistro;
use Modules\Memoria\Actions\CriarMemoria;
use Modules\Memoria\Actions\CriarRegistro;
use Modules\Memoria\Actions\ExcluirMemoria;
use Modules\Memoria\Actions\ExcluirRegistro;
use Modules\Memoria\Actions\ListarMemorias;
use Modules\Memoria\Http\Resources\MemoriaResource;
use Modules\Memoria\Models\Memoria;
use Modules\Memoria\Models\MemoriaRegistro;

/** POST /functions/v1/lifeos-memorias — query, create, update, delete, registro_create/update/delete. */
final class MemoriasRpcController extends RpcController
{
    protected function actions(): array
    {
        $uma = fn (Memoria $m): array => ['memoria' => MemoriaResource::make($m)];
        $reg = fn (MemoriaRegistro $r): array => ['registro' => MemoriaResource::registro($r)];
        $id = fn (string $id): array => ['id' => $id];

        return [
            'query' => [ListarMemorias::class, fn (array $r): array => [
                'memorias' => $r['memorias']->map(fn (Memoria $m): array => MemoriaResource::make($m))->all(),
                'categorias' => $r['categorias'],
            ]],
            'create' => [CriarMemoria::class, $uma],
            'update' => [AtualizarMemoria::class, $uma],
            'delete' => [ExcluirMemoria::class, $id],
            'registro_create' => [CriarRegistro::class, $reg],
            'registro_update' => [AtualizarRegistro::class, $reg],
            'registro_delete' => [ExcluirRegistro::class, $id],
        ];
    }
}
