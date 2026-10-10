<?php

declare(strict_types=1);

namespace Modules\Vocabularios\Http\Resources;

use Modules\Vocabularios\Models\Vocabulario;

final class VocabularioResource
{
    /** @return array<string, mixed> */
    public static function make(Vocabulario $v, ?int $uso = null, bool $comUso = false): array
    {
        return [
            'id' => $v->voc_id,
            'dominio' => $v->voc_dominio,
            'valor' => $v->voc_valor,
            'cor' => $v->voc_cor,
            'ordem' => $v->voc_ordem,
            'protegido' => $v->voc_protegido,
            'created_at' => $v->voc_created_at?->toIso8601String(),
            ...($comUso ? ['uso' => $uso] : []),
        ];
    }
}
