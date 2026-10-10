<?php

declare(strict_types=1);

namespace Modules\Core\Support\Mcp;

/** Resultado de tool na semântica MCP: o texto vira contexto para o modelo ler. */
final class Resultado
{
    /** @return array{content: list<array{type: string, text: string}>, isError: bool} */
    public static function texto(string $texto, bool $erro = false): array
    {
        return ['content' => [['type' => 'text', 'text' => $texto]], 'isError' => $erro];
    }

    /** Erro de DOMÍNIO: o modelo lê o motivo e se corrige (não é erro JSON-RPC). */
    public static function erro(string $texto): array
    {
        return self::texto($texto, true);
    }

    /** @return array{content: list<array{type: string, text: string}>, isError: bool} */
    public static function json(mixed $dados): array
    {
        return self::texto(Json::pretty($dados));
    }
}
