<?php

declare(strict_types=1);

namespace Modules\Financas\Actions;

use Modules\Core\Exceptions\LifeosException;
use Modules\Financas\Models\Recorrencia;

final class CriarRecorrencia extends AbstractRecorrenciaAction
{
    protected function sanitize(array $input): array
    {
        return ['recorrencia' => is_array($input['recorrencia'] ?? null) ? $input['recorrencia'] : null];
    }

    protected function rules(): array
    {
        return ['recorrencia' => ['required', 'array']];
    }

    protected function codes(): array
    {
        return ['recorrencia' => 'missing_recorrencia'];
    }

    protected function handle(array $data): mixed
    {
        $campos = $this->campos($data['recorrencia'], true);

        if ($this->duplicada($campos['rec_nome'], $campos['rec_direcao'])) {
            throw LifeosException::conflict('nome_duplicado');
        }

        // `ativa` e `meio`/`dia` nascem com o default do banco antigo.
        return Recorrencia::query()->create([
            'rec_ativa' => true, 'rec_meio' => null, 'rec_dia' => null, ...$campos,
        ]);
    }
}
