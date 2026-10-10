<?php

declare(strict_types=1);

namespace Modules\Visoes\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Visoes\Models\Visao;

final class CriarVisao extends AbstractVisaoAction
{
    protected function sanitize(array $input): array
    {
        $v = $input['view'] ?? null;

        if (is_array($v)) {
            $v = [
                'tabela' => is_string($v['tabela'] ?? null) ? $v['tabela'] : '',
                'nome' => $this->texto($v['nome'] ?? null),
                'modo' => array_key_exists('modo', $v) ? (string) $v['modo'] : 'todas',
                'regras' => $v['regras'] ?? null,
                'padrao' => $v['padrao'] ?? false,
                'ordem' => (is_int($v['ordem'] ?? null) || is_float($v['ordem'] ?? null)) ? (int) $v['ordem'] : null,
            ];
        }

        return ['view' => is_array($v) ? $v : null];
    }

    protected function rules(): array
    {
        return [
            'view' => ['required', 'array'],
            'view.tabela' => ['required', 'in:'.implode(',', self::TABELAS)],
            'view.nome' => ['required', 'string'],
            'view.modo' => ['in:'.implode(',', self::MODOS)],
        ];
    }

    protected function codes(): array
    {
        return ['view' => 'missing_view', 'view.tabela' => 'invalid_tabela', 'view.nome' => 'invalid_nome', 'view.modo' => 'invalid_modo'];
    }

    protected function handle(array $data): mixed
    {
        $v = $data['view'];

        $regras = $this->regrasValidas($v['regras'], $v['tabela']) ?? $this->invalido('invalid_regras');
        is_bool($v['padrao']) || $this->invalido('invalid_padrao');

        return DB::transaction(function () use ($v, $regras): Visao {
            $ordem = $v['ordem'] ?? (($max = Visao::query()->where('vis_tabela', $v['tabela'])->max('vis_ordem')) === null ? 0 : (int) $max + 1);

            if ($v['padrao']) {
                $this->desmarcarPadrao($v['tabela']);
            }

            return Visao::query()->create([
                'vis_tabela' => $v['tabela'],
                'vis_nome' => $v['nome'],
                'vis_modo' => $v['modo'],
                'vis_regras' => $regras,
                'vis_ordem' => $ordem,
                'vis_padrao' => $v['padrao'],
            ]);
        });
    }
}
