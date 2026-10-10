<?php

declare(strict_types=1);

namespace Modules\Financas\Actions;

use Modules\Core\Exceptions\LifeosException;
use Modules\Financas\Models\Movimentacao;

final class AtualizarMovimentacao extends AbstractMovimentacaoAction
{
    protected function sanitize(array $input): array
    {
        return [
            'id' => $this->texto($input['id'] ?? null),
            'patch' => is_array($input['patch'] ?? null) ? $input['patch'] : null,
        ];
    }

    protected function rules(): array
    {
        return ['id' => ['required', 'string'], 'patch' => ['required', 'array', 'min:1']];
    }

    protected function codes(): array
    {
        return ['id' => 'missing_id', 'patch' => 'empty_patch'];
    }

    protected function handle(array $data): mixed
    {
        $campos = $this->campos($data['patch'], false);
        $campos !== [] || throw new LifeosException('empty_patch');

        $mov = $this->achar(Movimentacao::class, $data['id']);
        $mov->update($campos);

        return $mov;
    }
}
