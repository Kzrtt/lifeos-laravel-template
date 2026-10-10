<?php

declare(strict_types=1);

namespace Modules\Projetos\Actions;

use Modules\Core\Actions\RpcAction;
use Modules\Projetos\Models\Tarefa;

final class ExcluirTarefa extends RpcAction
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
        $this->achar(Tarefa::class, $data['id'])->delete();

        return $data['id'];
    }
}
