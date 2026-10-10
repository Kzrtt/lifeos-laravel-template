<?php

declare(strict_types=1);

namespace Modules\Projetos\Http\Controllers;

use Modules\Core\Http\RpcController;
use Modules\Projetos\Actions\AtualizarTarefa;
use Modules\Projetos\Actions\CriarTarefa;
use Modules\Projetos\Actions\ExcluirTarefa;
use Modules\Projetos\Actions\ListarTarefas;
use Modules\Projetos\Http\Resources\TarefaResource;
use Modules\Projetos\Models\Tarefa;

/** POST /functions/v1/lifeos-tarefas — query (opcional projeto_id), create, update, delete. */
final class TarefasRpcController extends RpcController
{
    protected function actions(): array
    {
        $uma = fn (Tarefa $t): array => ['tarefa' => TarefaResource::make($t)];

        return [
            'query' => [ListarTarefas::class, fn ($rows): array => [
                'tarefas' => $rows->map(fn (Tarefa $t): array => TarefaResource::make($t))->all(),
            ]],
            'create' => [CriarTarefa::class, $uma],
            'update' => [AtualizarTarefa::class, $uma],
            'delete' => [ExcluirTarefa::class, fn (string $id): array => ['id' => $id]],
        ];
    }
}
