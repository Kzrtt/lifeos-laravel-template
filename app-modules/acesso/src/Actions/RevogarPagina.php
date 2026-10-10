<?php

declare(strict_types=1);

namespace Modules\Acesso\Actions;

use Illuminate\Support\Str;
use Modules\Acesso\Models\TokenPage;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Exceptions\LifeosException;

/** `id` aqui é o id da LINHA de token_pages, não o da senha. */
final class RevogarPagina extends RpcAction
{
    protected function sanitize(array $input): array
    {
        $input['id'] = trim((string) ($input['id'] ?? ''));

        return $input;
    }

    protected function rules(): array
    {
        return ['id' => ['required', 'string']];
    }

    protected function codes(): array
    {
        return ['id' => 'missing_id'];
    }

    protected function handle(array $data): mixed
    {
        $grant = Str::isUuid($data['id']) ? TokenPage::query()->find($data['id']) : null;
        if ($grant === null) {
            throw LifeosException::notFound();
        }

        $grant->delete();

        return $data['id'];
    }
}
