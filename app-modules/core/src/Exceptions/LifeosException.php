<?php

declare(strict_types=1);

namespace Modules\Core\Exceptions;

use RuntimeException;

/**
 * Recusa de negócio no formato do contrato do LifeOS: o handler de exceções em
 * bootstrap/app.php converte para `{ok:false, error:"<codigo>"}` com o status
 * que a Edge Function equivalente usava (400, 401, 404, 409...).
 *
 * `$extra` entra no corpo ao lado de `error` (ex.: `antes_do_inicio` devolve a
 * data limite). O código curto é a parte estável do contrato — o front compara
 * com ele; a mensagem de exceção do PHP é o mesmo código.
 */
final class LifeosException extends RuntimeException
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        public readonly string $errorCode,
        public readonly int $status = 400,
        public readonly array $extra = [],
    ) {
        parent::__construct($errorCode);
    }

    public static function notFound(string $code = 'not_found'): self
    {
        return new self($code, 404);
    }

    /** @param array<string, mixed> $extra */
    public static function conflict(string $code, array $extra = []): self
    {
        return new self($code, 409, $extra);
    }
}
