<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Texto das `instructions` do handshake MCP (`initialize`): o cliente o injeta no
 * system prompt. O servidor o pede a CADA handshake — é dinâmico, por instância
 * (hoje: o índice das memórias). Implementada por `memoria`.
 */
interface InstrucoesDoServidor
{
    public function texto(): string;
}
