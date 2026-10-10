<?php

declare(strict_types=1);

namespace Modules\Agenda\Http\Controllers;

use Modules\Agenda\Actions\AtualizarEvento;
use Modules\Agenda\Actions\CriarEvento;
use Modules\Agenda\Actions\ExcluirEvento;
use Modules\Agenda\Actions\ListarEventos;
use Modules\Agenda\Http\Resources\EventoResource;
use Modules\Agenda\Models\Evento;
use Modules\Core\Http\RpcController;

/** POST /functions/v1/lifeos-eventos — query (exige from/to), create, update, delete. */
final class EventosRpcController extends RpcController
{
    protected function actions(): array
    {
        $um = fn (Evento $e): array => ['evento' => EventoResource::make($e)];

        return [
            'query' => [ListarEventos::class, fn ($rows): array => [
                'eventos' => $rows->map(fn (Evento $e): array => EventoResource::make($e))->all(),
            ]],
            'create' => [CriarEvento::class, $um],
            'update' => [AtualizarEvento::class, $um],
            'delete' => [ExcluirEvento::class, fn (string $id): array => ['id' => $id]],
        ];
    }
}
