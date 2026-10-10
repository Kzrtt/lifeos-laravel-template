<?php

declare(strict_types=1);

namespace Modules\Acesso\Http\Resources;

use Illuminate\Support\Collection;
use Modules\Acesso\Models\AccessToken;
use Modules\Acesso\Services\TokenService;

/**
 * Traduz AccessToken para as chaves que senhas.js lê hoje. O valor da senha não
 * existe aqui para sair: só a máscara e o tamanho.
 */
final class SenhaResource
{
    /** @return array<string, mixed> */
    public static function make(AccessToken $token): array
    {
        /** @var Collection<int, \Modules\Acesso\Models\TokenPage> $pages */
        $pages = $token->pages->sortBy('tpg_page_slug')->values();

        return [
            'id' => $token->tok_id,
            'label' => $token->tok_label,
            'is_master' => $token->tok_is_master,
            'token_masked' => $token->tok_hint,
            'token_len' => $token->tok_len,
            'curta' => $token->tok_len < TokenService::MIN_LEN,
            'paginas' => $pages->map(fn ($p): array => [
                'id' => $p->tpg_id,
                'page_slug' => $p->tpg_page_slug,
            ])->all(),
        ];
    }
}
