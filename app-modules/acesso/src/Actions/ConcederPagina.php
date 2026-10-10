<?php

declare(strict_types=1);

namespace Modules\Acesso\Actions;

use Illuminate\Support\Str;
use Modules\Acesso\Models\AccessToken;
use Modules\Acesso\Models\TokenPage;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Exceptions\LifeosException;

final class ConcederPagina extends RpcAction
{
    protected function sanitize(array $input): array
    {
        $input['id'] = trim((string) ($input['id'] ?? ''));
        $input['page_slug'] = trim((string) ($input['page_slug'] ?? ''));

        return $input;
    }

    protected function rules(): array
    {
        return [
            'id' => ['required', 'string'],
            'page_slug' => ['required', 'string'],
        ];
    }

    protected function codes(): array
    {
        return ['id' => 'missing_id', 'page_slug' => 'missing_page_slug'];
    }

    protected function handle(array $data): mixed
    {
        $token = Str::isUuid($data['id']) ? AccessToken::query()->find($data['id']) : null;
        if ($token === null) {
            throw LifeosException::notFound();
        }

        // Mestre já passa em qualquer página: um escopo explícito só polui a lista.
        if ($token->tok_is_master) {
            throw LifeosException::conflict('master_no_scope');
        }

        $existe = TokenPage::query()
            ->where('access_token_tok_id', $token->tok_id)
            ->where('tpg_page_slug', $data['page_slug'])
            ->exists();
        if ($existe) {
            throw LifeosException::conflict('duplicate_grant');
        }

        return TokenPage::query()->create([
            'access_token_tok_id' => $token->tok_id,
            'tpg_page_slug' => $data['page_slug'],
        ]);
    }
}
