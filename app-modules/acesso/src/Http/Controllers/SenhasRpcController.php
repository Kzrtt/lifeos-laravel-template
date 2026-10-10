<?php

declare(strict_types=1);

namespace Modules\Acesso\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Acesso\Actions\AtualizarSenha;
use Modules\Acesso\Actions\ConcederPagina;
use Modules\Acesso\Actions\CriarSenha;
use Modules\Acesso\Actions\ExcluirSenha;
use Modules\Acesso\Actions\ListarSenhas;
use Modules\Acesso\Actions\RevogarPagina;
use Modules\Acesso\Http\Resources\SenhaResource;
use Modules\Acesso\Models\AccessToken;
use Modules\Acesso\Services\TokenService;
use Modules\Core\Http\RpcController;

/** POST /functions/v1/lifeos-senhas — query, create, update, delete, grant, revoke. */
final class SenhasRpcController extends RpcController
{
    protected function actions(): array
    {
        return [
            'query' => [ListarSenhas::class, fn ($tokens): array => [
                'senhas' => $tokens->map(fn (AccessToken $t): array => SenhaResource::make($t))->all(),
                'min_token_len' => TokenService::MIN_LEN,
            ]],
            'create' => [CriarSenha::class, fn (array $r): array => [
                'senha' => SenhaResource::make($r['token']),
                ...($r['warning'] !== null ? ['warning' => $r['warning']] : []),
            ]],
            'update' => [AtualizarSenha::class, fn (array $r): array => [
                'senha' => SenhaResource::make($r['token']),
                'reauth' => $r['reauth'],
                ...($r['warning'] !== null ? ['warning' => $r['warning']] : []),
            ]],
            'delete' => [ExcluirSenha::class, fn (string $id): array => ['id' => $id]],
            'grant' => [ConcederPagina::class, fn ($g): array => ['grant' => [
                'id' => $g->tpg_id,
                'page_slug' => $g->tpg_page_slug,
            ]]],
            'revoke' => [RevogarPagina::class, fn (string $id): array => ['id' => $id]],
        ];
    }

    /** A senha de quem chama entra no input: reauth e self_delete dependem dela. */
    protected function input(Request $request): array
    {
        return [
            ...parent::input($request),
            '_caller_id' => $request->attributes->get('lifeos.token_id'),
        ];
    }
}
