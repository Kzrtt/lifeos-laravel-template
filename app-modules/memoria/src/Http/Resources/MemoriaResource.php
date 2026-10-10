<?php

declare(strict_types=1);

namespace Modules\Memoria\Http\Resources;

use Modules\Memoria\Models\Memoria;
use Modules\Memoria\Models\MemoriaRegistro;

final class MemoriaResource
{
    /** @return array<string, mixed> */
    public static function make(Memoria $m): array
    {
        return [
            'id' => $m->mem_id,
            'titulo' => $m->mem_titulo,
            'descricao' => $m->mem_descricao,
            'categoria' => $m->mem_categoria,
            'created_at' => $m->mem_created_at?->toIso8601String(),
            'updated_at' => $m->mem_updated_at?->toIso8601String(),
            'registros' => $m->registros->map(fn (MemoriaRegistro $r): array => self::registro($r))->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function registro(MemoriaRegistro $r): array
    {
        return [
            'id' => $r->mrg_id,
            'memoria_id' => $r->memoria_mem_id,
            'texto' => $r->mrg_texto,
            'origem' => $r->mrg_origem,
            'created_at' => $r->mrg_created_at?->toIso8601String(),
            'updated_at' => $r->mrg_updated_at?->toIso8601String(),
        ];
    }
}
