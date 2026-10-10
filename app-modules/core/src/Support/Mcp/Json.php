<?php

declare(strict_types=1);

namespace Modules\Core\Support\Mcp;

use stdClass;

/**
 * Equivalente de `JSON.stringify(valor, null, 2)`. O texto das tools é o que o
 * modelo lê, então o formato é parte do contrato: indentação de 2 espaços (o
 * pretty-print do PHP usa 4), `12.0` sai `12`, sem escapar unicode nem `/`.
 * Lista (array sequencial) vira array; array associativo e stdClass viram objeto.
 */
final class Json
{
    private const FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR;

    public static function pretty(mixed $valor): string
    {
        return self::escrever($valor, 0);
    }

    private static function escrever(mixed $v, int $nivel): string
    {
        if ($v instanceof stdClass) {
            $v = (array) $v;
            $objeto = true;
        } else {
            $objeto = is_array($v) && ! array_is_list($v);
        }

        if (is_array($v)) {
            if ($v === []) {
                return $objeto ? '{}' : '[]';
            }
            $pad = str_repeat('  ', $nivel + 1);
            $fim = str_repeat('  ', $nivel);

            if (! $objeto) {
                return "[\n".implode(",\n", array_map(fn ($i): string => $pad.self::escrever($i, $nivel + 1), $v))."\n{$fim}]";
            }

            $linhas = [];
            foreach ($v as $chave => $valor) {
                $linhas[] = $pad.json_encode((string) $chave, self::FLAGS).': '.self::escrever($valor, $nivel + 1);
            }

            return "{\n".implode(",\n", $linhas)."\n{$fim}}";
        }

        if (is_float($v) && is_finite($v) && floor($v) === $v && abs($v) < 1e15) {
            return (string) (int) $v;
        }

        return json_encode($v, self::FLAGS);
    }
}
