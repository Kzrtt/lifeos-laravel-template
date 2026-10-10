<?php

declare(strict_types=1);

namespace Modules\Visoes\Actions;

use Modules\Core\Actions\RpcAction;
use Modules\Core\Exceptions\LifeosException;
use Modules\Visoes\Models\Visao;

/** Regras compartilhadas de validação e do "uma padrão por tabela". */
abstract class AbstractVisaoAction extends RpcAction
{
    public const TABELAS = ['notas', 'tarefas'];

    public const MODOS = ['todas', 'qualquer'];

    public const OPERADORES = ['incluir', 'excluir'];

    public const CAMPOS_POR_TABELA = [
        'notas' => ['projeto', 'tipo'],
        'tarefas' => ['projeto', 'tipo', 'status'],
    ];

    /**
     * Regras limpas, ou null se qualquer uma for inválida. Dentro de UMA regra
     * vários valores são OU; o modo decide como regras de campos diferentes se
     * combinam.
     *
     * @return list<array{campo: string, operador: string, valores: list<string>}>|null
     */
    protected function regrasValidas(mixed $v, string $tabela): ?array
    {
        if (! is_array($v) || ! array_is_list($v)) {
            return $v === [] ? [] : null;
        }

        $out = [];
        foreach ($v as $r) {
            if (! is_array($r)) {
                return null;
            }
            $campo = (string) ($r['campo'] ?? '');
            $operador = (string) ($r['operador'] ?? '');
            $brutos = $r['valores'] ?? null;

            if (! in_array($campo, self::CAMPOS_POR_TABELA[$tabela], true)
                || ! in_array($operador, self::OPERADORES, true)
                || ! is_array($brutos) || $brutos === []) {
                return null;
            }

            $valores = [];
            foreach ($brutos as $valor) {
                $s = $this->texto($valor);
                if ($s === '') {
                    return null;
                }
                $valores[] = $s;
            }
            $out[] = ['campo' => $campo, 'operador' => $operador, 'valores' => $valores];
        }

        return $out;
    }

    protected function desmarcarPadrao(string $tabela, ?string $exceto = null): void
    {
        Visao::query()->where('vis_tabela', $tabela)->where('vis_padrao', true)
            ->when($exceto !== null, fn ($q) => $q->where('vis_id', '!=', $exceto))
            ->get()->each->update(['vis_padrao' => false]);
    }

    protected function invalido(string $code): never
    {
        throw new LifeosException($code);
    }
}
