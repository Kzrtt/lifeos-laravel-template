<?php

declare(strict_types=1);

namespace Modules\Acesso\Actions;

use Modules\Acesso\Models\AccessToken;
use Modules\Core\Actions\RpcAction;

final class ListarSenhas extends RpcAction
{
    protected function rules(): array
    {
        return [];
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, AccessToken> */
    protected function handle(array $data): mixed
    {
        return AccessToken::query()
            ->with('pages')
            ->orderByDesc('tok_is_master')
            ->orderBy('tok_created_at')
            ->get();
    }
}
