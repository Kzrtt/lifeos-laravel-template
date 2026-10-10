<?php

declare(strict_types=1);

namespace Modules\Visoes\Actions;

use Modules\Visoes\Models\Visao;

final class ListarVisoes extends AbstractVisaoAction
{
    protected function sanitize(array $input): array
    {
        return ['tabela' => $this->texto($input['tabela'] ?? null)];
    }

    protected function rules(): array
    {
        return ['tabela' => ['required', 'in:'.implode(',', self::TABELAS)]];
    }

    protected function codes(): array
    {
        return ['tabela' => 'invalid_tabela'];
    }

    protected function handle(array $data): mixed
    {
        return Visao::query()->where('vis_tabela', $data['tabela'])
            ->orderBy('vis_ordem')->orderBy('vis_created_at')->get();
    }
}
