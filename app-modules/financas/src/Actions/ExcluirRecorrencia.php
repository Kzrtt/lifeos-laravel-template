<?php

declare(strict_types=1);

namespace Modules\Financas\Actions;

use Modules\Financas\Models\Recorrencia;

final class ExcluirRecorrencia extends AbstractRecorrenciaAction
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
        $this->achar(Recorrencia::class, $data['id'])->delete();

        return $data['id'];
    }
}
