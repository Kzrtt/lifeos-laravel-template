<?php

declare(strict_types=1);

namespace Modules\Financas\Actions;

use Modules\Core\Exceptions\LifeosException;

final class AtualizarRecorrencia extends AbstractRecorrenciaAction
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

        $rec = $this->achar(\Modules\Financas\Models\Recorrencia::class, $data['id']);

        if (isset($campos['rec_nome']) || isset($campos['rec_direcao'])) {
            $nome = $campos['rec_nome'] ?? $rec->rec_nome;
            $direcao = $campos['rec_direcao'] ?? $rec->rec_direcao;
            if ($this->duplicada($nome, $direcao, $rec->rec_id)) {
                throw LifeosException::conflict('nome_duplicado');
            }
        }

        $rec->update($campos);

        return $rec;
    }
}
