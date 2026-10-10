<?php

declare(strict_types=1);

namespace Modules\Acesso\Services;

use Modules\Acesso\Models\TokenPage;
use Modules\Core\Contracts\AcessoAPagina;
use Modules\Core\Tenancy\TenantContext;

/**
 * check_page_access: mestre passa em QUALQUER página, sem precisar de linha em
 * token_pages; as demais senhas, só nas páginas concedidas.
 */
final class GateDePaginas implements AcessoAPagina
{
    public function __construct(private readonly TokenService $tokens) {}

    public function entidadeSeAutorizado(string $senha, string $pagina): ?string
    {
        $token = $this->tokens->findByPlain($senha);
        if ($token === null) {
            return null;
        }

        if ($token->tok_is_master) {
            return $token->entity_ent_id;
        }

        $concedida = TenantContext::runAs($token->entity_ent_id, fn (): bool => TokenPage::query()
            ->where('access_token_tok_id', $token->tok_id)
            ->where('tpg_page_slug', $pagina)
            ->exists());

        return $concedida ? $token->entity_ent_id : null;
    }
}
