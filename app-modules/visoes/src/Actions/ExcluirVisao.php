<?php

declare(strict_types=1);

namespace Modules\Visoes\Actions;

use Modules\Core\Actions\RpcAction;
use Modules\Visoes\Models\Visao;

final class ExcluirVisao extends RpcAction
{
    protected function sanitize(array $input): array
    {
        return ['id' => $this->texto($input['id'] ?? null)];
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
        $this->achar(Visao::class, $data['id'])->delete();

        return $data['id'];
    }
}
