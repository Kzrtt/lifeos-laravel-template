<?php

declare(strict_types=1);

namespace Modules\Renuncias\Http\Controllers;

use Modules\Core\Http\RpcController;
use Modules\Renuncias\Actions\AtualizarRenuncia;
use Modules\Renuncias\Actions\CriarRenuncia;
use Modules\Renuncias\Actions\ExcluirRenuncia;
use Modules\Renuncias\Actions\ListarRenuncias;
use Modules\Renuncias\Actions\RegistrarRecaida;
use Modules\Renuncias\Http\Resources\RenunciaResource;
use Modules\Renuncias\Models\Renuncia;

/** POST /functions/v1/lifeos-renuncias — query, create, update, delete, recaida. */
final class RenunciasRpcController extends RpcController
{
    protected function actions(): array
    {
        $uma = fn (Renuncia $r): array => ['renuncia' => RenunciaResource::make($r)];

        return [
            'query' => [ListarRenuncias::class, fn ($rows): array => [
                'renuncias' => $rows->map(fn (Renuncia $r): array => RenunciaResource::make($r))->all(),
            ]],
            'create' => [CriarRenuncia::class, $uma],
            'update' => [AtualizarRenuncia::class, $uma],
            'delete' => [ExcluirRenuncia::class, fn (string $id): array => ['id' => $id]],
            'recaida' => [RegistrarRecaida::class, $uma],
        ];
    }
}
