<?php

declare(strict_types=1);

namespace Modules\Core\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Cast de `text[]` do PostgreSQL para `list<string>` do PHP (e de volta).
 * Os arrays de tags/tipos do LifeOS continuam arrays no banco; é o motivo de
 * a suíte rodar contra Postgres e não contra sqlite.
 *
 * @implements CastsAttributes<list<string>, list<string>|null>
 */
final class PgTextArray implements CastsAttributes
{
    /** @return list<string> */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '{}' || $value === '') {
            return [];
        }

        return self::parse((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value === null) {
            return '{}';
        }

        if (! is_array($value)) {
            throw new InvalidArgumentException("{$key} espera uma lista de strings.");
        }

        return self::literal($value);
    }

    /**
     * Literal `{"a","b"}` do Postgres para uma lista de strings.
     *
     * @param  list<mixed>  $items
     */
    public static function literal(array $items): string
    {
        return '{'.implode(',', array_map(
            static fn (mixed $v): string => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], (string) $v).'"',
            array_values($items),
        )).'}';
    }

    /**
     * Parser do literal de array do Postgres: `{a,"b c","d\"e",NULL}`. Elementos entre aspas
     * aceitam `\\` e `\"`; fora das aspas, vírgula separa; `NULL` sem aspas vira string vazia.
     *
     * @return list<string>
     */
    public static function parse(string $literal): array
    {
        $literal = trim($literal);
        if ($literal === '' || $literal === '{}') {
            return [];
        }
        $corpo = substr($literal, 1, -1);

        $itens = [];
        $atual = '';
        $entreAspas = false;
        $teveAspas = false;
        $n = strlen($corpo);

        for ($i = 0; $i < $n; $i++) {
            $c = $corpo[$i];
            if ($entreAspas) {
                if ($c === '\\' && $i + 1 < $n) {
                    $atual .= $corpo[++$i];
                } elseif ($c === '"') {
                    $entreAspas = false;
                } else {
                    $atual .= $c;
                }

                continue;
            }
            if ($c === '"') {
                $entreAspas = true;
                $teveAspas = true;
            } elseif ($c === ',') {
                $itens[] = (! $teveAspas && $atual === 'NULL') ? '' : $atual;
                $atual = '';
                $teveAspas = false;
            } else {
                $atual .= $c;
            }
        }
        $itens[] = (! $teveAspas && $atual === 'NULL') ? '' : $atual;

        return $itens;
    }
}
