<?php

declare(strict_types=1);

namespace Modules\Core\Support\Mcp;

use Carbon\CarbonImmutable;
use DateTimeZone;

/**
 * Leitura e normalização dos argumentos das tools, com a semântica do JS de hoje
 * (`String(x ?? "").trim()`, `clampLimit`, `strArray`...).
 */
final class Args
{
    /** `String(v ?? "")` do JS. */
    public static function str(mixed $v): string
    {
        return match (true) {
            $v === null => '',
            is_bool($v) => $v ? 'true' : 'false',
            is_float($v) => floor($v) === $v && abs($v) < 1e15 ? (string) (int) $v : (string) $v,
            is_scalar($v) => (string) $v,
            default => '',
        };
    }

    public static function trim(mixed $v): string
    {
        return trim(self::str($v));
    }

    /** O argumento foi enviado (≠ undefined)? `null` conta como enviado. */
    public static function tem(array $args, string $chave): bool
    {
        return array_key_exists($chave, $args);
    }

    /** `args.x ? String(args.x).trim() : ""`. */
    public static function textoOpcional(array $args, string $chave): string
    {
        return ! empty($args[$chave]) ? self::trim($args[$chave]) : '';
    }

    /** `strArray` do JS: array vira lista de `String(x)` (null vira "null"); o resto, []. @return list<string> */
    public static function lista(mixed $v): array
    {
        return is_array($v)
            ? array_values(array_map(fn ($x): string => $x === null ? 'null' : self::str($x), $v))
            : [];
    }

    public static function limite(mixed $v, int $padrao = 20, int $max = 50): int
    {
        $n = is_numeric($v) ? (int) $v : 0;

        return max(1, min($max, $n !== 0 ? $n : $padrao));
    }

    public static function hojeSaoPaulo(): string
    {
        return CarbonImmutable::now(new DateTimeZone('America/Sao_Paulo'))->format('Y-m-d');
    }

    public static function dataValida(string $s): bool
    {
        return preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    /** Erro de MCP se alguma data (não vazia) for inválida; null se estiver tudo certo. */
    public static function erroDatas(string ...$datas): ?array
    {
        $invalidas = array_filter($datas, fn (string $d): bool => $d !== '' && ! self::dataValida($d));

        return $invalidas === [] ? null : Resultado::erro('Data(s) inválida(s): '.implode(', ', $invalidas).'. Use o formato YYYY-MM-DD.');
    }

    /** Padrão de ILIKE para "contém <trecho>": escapa `\ % _`; um `*` vira `_`. */
    public static function contem(string $trecho): string
    {
        $esc = preg_replace_callback('/[\\\\%_]/', fn (array $m): string => '\\'.$m[0], $trecho) ?? $trecho;

        return '%'.str_replace('*', '_', $esc).'%';
    }

    /** Resumo de uma nota em texto corrido (cópia de noteSnippet do front). */
    public static function snippet(?string $md, int $max = 220): string
    {
        if ($md === null || $md === '') {
            return '';
        }
        $s = $md;
        foreach ([
            ['/^#{1,6}\s+/m', ''], ['/^>\s?/m', ''], ['/^[-*+]\s+/m', ''], ['/[*_`]/', ''],
            ['/\[([^\]]*)\]\([^)]*\)/', '$1'], ['/\s+/u', ' '],
        ] as [$padrao, $troca]) {
            $s = preg_replace($padrao, $troca, $s) ?? $s;
        }
        $s = trim($s);

        return mb_strlen($s) > $max ? trim(mb_substr($s, 0, $max)).'…' : $s;
    }

    /** Lista humana "a, b, c". */
    public static function juntar(array $itens): string
    {
        return implode(', ', $itens);
    }
}
