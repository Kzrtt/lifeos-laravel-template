<?php

declare(strict_types=1);

namespace Modules\Visoes\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Visoes\Models\Visao;

final class AtualizarVisao extends AbstractVisaoAction
{
    protected function sanitize(array $input): array
    {
        $patch = $input['patch'] ?? null;

        if (is_array($patch)) {
            if (array_key_exists('nome', $patch)) {
                $patch['nome'] = $this->texto($patch['nome']);
            }
            if (array_key_exists('modo', $patch)) {
                $patch['modo'] = (string) ($patch['modo'] ?? '');
            }
        }

        return ['id' => $this->texto($input['id'] ?? null), 'patch' => is_array($patch) ? $patch : null];
    }

    protected function rules(): array
    {
        return [
            'id' => ['required', 'string'],
            'patch' => ['required', 'array', 'min:1'],
        ];
    }

    protected function codes(): array
    {
        return ['id' => 'missing_id', 'patch' => 'empty_patch'];
    }

    protected function handle(array $data): mixed
    {
        $patch = $data['patch'];
        $visao = $this->achar(Visao::class, $data['id']);

        $update = [];
        if (array_key_exists('nome', $patch)) {
            $patch['nome'] !== '' || $this->invalido('invalid_nome');
            $update['vis_nome'] = $patch['nome'];
        }
        if (array_key_exists('modo', $patch)) {
            in_array($patch['modo'], self::MODOS, true) || $this->invalido('invalid_modo');
            $update['vis_modo'] = $patch['modo'];
        }
        if (array_key_exists('regras', $patch)) {
            $update['vis_regras'] = $this->regrasValidas($patch['regras'], $visao->vis_tabela) ?? $this->invalido('invalid_regras');
        }
        if (array_key_exists('padrao', $patch)) {
            is_bool($patch['padrao']) || $this->invalido('invalid_padrao');
            $update['vis_padrao'] = $patch['padrao'];
        }
        $update !== [] || $this->invalido('empty_patch');

        return DB::transaction(function () use ($visao, $update): Visao {
            if (($update['vis_padrao'] ?? false) === true) {
                $this->desmarcarPadrao($visao->vis_tabela, $visao->vis_id);
            }
            $visao->update($update);

            return $visao;
        });
    }
}
