<?php

declare(strict_types=1);

namespace Modules\Manifestacoes\Http\Resources;

use Modules\Manifestacoes\Models\Manifestacao;

final class ManifestacaoResource
{
    /** @return array<string, mixed> */
    public static function make(Manifestacao $m): array
    {
        return [
            'id' => $m->man_id,
            'name' => $m->man_name,
            'status' => $m->man_status,
            'tags' => $m->man_tags,
            'banner_url' => $m->man_banner_url,
            'descricao' => $m->man_descricao,
        ];
    }
}
