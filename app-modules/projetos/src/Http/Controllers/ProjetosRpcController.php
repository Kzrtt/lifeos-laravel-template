<?php

declare(strict_types=1);

namespace Modules\Projetos\Http\Controllers;

use Modules\Core\Http\RpcController;
use Modules\Projetos\Actions\AtualizarProjeto;
use Modules\Projetos\Actions\CriarProjeto;
use Modules\Projetos\Actions\ExcluirProjeto;
use Modules\Projetos\Actions\ListarProjetos;
use Modules\Projetos\Http\Resources\ProjetoResource;
use Modules\Projetos\Models\Projeto;

/** POST /functions/v1/lifeos-projetos — query, create, update, delete. */
final class ProjetosRpcController extends RpcController
{
    protected function actions(): array
    {
        $um = fn (Projeto $p): array => ['projeto' => ProjetoResource::make($p)];

        return [
            'query' => [ListarProjetos::class, fn ($rows): array => [
                'projetos' => $rows->map(fn (Projeto $p): array => ProjetoResource::make($p))->all(),
            ]],
            'create' => [CriarProjeto::class, $um],
            'update' => [AtualizarProjeto::class, $um],
            'delete' => [ExcluirProjeto::class, fn (string $id): array => ['id' => $id]],
        ];
    }
}
