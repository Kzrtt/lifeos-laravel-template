<?php

declare(strict_types=1);

namespace Modules\Agenda\Actions;

use Modules\Agenda\Models\Evento;
use Modules\Core\Actions\RpcAction;

final class ExcluirEvento extends RpcAction
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
        $this->achar(Evento::class, $data['id'])->delete();

        return $data['id'];
    }
}
