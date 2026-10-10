<?php

declare(strict_types=1);

namespace Modules\Financas\Actions;

use Modules\Financas\Models\Movimentacao;

final class ExcluirMovimentacao extends AbstractMovimentacaoAction
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
        $this->achar(Movimentacao::class, $data['id'])->delete();

        return $data['id'];
    }
}
