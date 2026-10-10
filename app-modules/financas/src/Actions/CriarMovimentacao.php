<?php

declare(strict_types=1);

namespace Modules\Financas\Actions;

use Modules\Financas\Models\Movimentacao;

/** Canal da tela e do webhook: ambos chegam aqui com o array canônico. */
final class CriarMovimentacao extends AbstractMovimentacaoAction
{
    protected function sanitize(array $input): array
    {
        $m = $input['movimentacao'] ?? null;

        return ['movimentacao' => is_array($m) ? $m : null];
    }

    protected function rules(): array
    {
        return ['movimentacao' => ['required', 'array']];
    }

    protected function codes(): array
    {
        return ['movimentacao' => 'missing_movimentacao'];
    }

    protected function handle(array $data): mixed
    {
        return Movimentacao::query()->create($this->campos($data['movimentacao'], true));
    }
}
