<?php

declare(strict_types=1);

namespace Modules\Citacoes\Actions;

use Illuminate\Support\Str;
use Modules\Citacoes\Models\Citacao;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Exceptions\LifeosException;

final class ExcluirCitacao extends RpcAction
{
    protected function sanitize(array $input): array
    {
        return ['id' => trim((string) ($input['id'] ?? ''))];
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
        $citacao = Str::isUuid($data['id']) ? Citacao::query()->find($data['id']) : null;
        if ($citacao === null) {
            throw LifeosException::notFound();
        }

        $citacao->delete();

        return $data['id'];
    }
}
