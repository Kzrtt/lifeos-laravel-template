<?php

declare(strict_types=1);

namespace Modules\Citacoes\Http\Resources;

use Modules\Citacoes\Models\Citacao;

/** Traduz cit_* de volta para as chaves que o hub lê hoje. */
final class CitacaoResource
{
    /** @return array<string, mixed> */
    public static function make(Citacao $c): array
    {
        return [
            'id' => $c->cit_id,
            'texto' => $c->cit_texto,
            'autor' => $c->cit_autor,
            'created_at' => $c->cit_created_at?->toIso8601String(),
        ];
    }
}
