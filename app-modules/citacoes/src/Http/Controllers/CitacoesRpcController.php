<?php

declare(strict_types=1);

namespace Modules\Citacoes\Http\Controllers;

use Modules\Citacoes\Actions\AtualizarCitacao;
use Modules\Citacoes\Actions\CriarCitacao;
use Modules\Citacoes\Actions\ExcluirCitacao;
use Modules\Citacoes\Actions\ListarCitacoes;
use Modules\Citacoes\Http\Resources\CitacaoResource;
use Modules\Citacoes\Models\Citacao;
use Modules\Core\Http\RpcController;

/** POST /functions/v1/lifeos-citacoes — query, create, update, delete. */
final class CitacoesRpcController extends RpcController
{
    protected function actions(): array
    {
        $uma = fn (Citacao $c): array => ['citacao' => CitacaoResource::make($c)];

        return [
            'query' => [ListarCitacoes::class, fn ($rows): array => [
                'citacoes' => $rows->map(fn (Citacao $c): array => CitacaoResource::make($c))->all(),
            ]],
            'create' => [CriarCitacao::class, $uma],
            'update' => [AtualizarCitacao::class, $uma],
            'delete' => [ExcluirCitacao::class, fn (string $id): array => ['id' => $id]],
        ];
    }
}
