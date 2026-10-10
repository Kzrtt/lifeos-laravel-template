<?php

declare(strict_types=1);

namespace Modules\Notas\Actions;

use Modules\Core\Actions\RpcAction;
use Modules\Notas\Models\Nota;

final class ExcluirNota extends RpcAction
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
        $this->achar(Nota::class, $data['id'])->delete();

        return $data['id'];
    }
}
