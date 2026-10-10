<?php

declare(strict_types=1);

namespace Modules\Projetos\Http\Resources;

use Modules\Projetos\Models\Projeto;

final class ProjetoResource
{
    /** @return array<string, mixed> */
    public static function make(Projeto $p): array
    {
        return [
            'id' => $p->prj_id,
            'name' => $p->prj_name,
            'emoji' => $p->prj_emoji,
            'status' => $p->prj_status,
            'tags' => $p->prj_tags,
        ];
    }
}
