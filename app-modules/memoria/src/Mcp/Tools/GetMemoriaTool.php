<?php

declare(strict_types=1);

namespace Modules\Memoria\Mcp\Tools;

use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Memoria\Models\Memoria;
use Modules\Memoria\Models\MemoriaRegistro;

final class GetMemoriaTool extends AbstractMemoriaTool
{
    public function ordem(): int
    {
        return 16;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/get_memoria.json';
    }

    public function chamar(array $args): array
    {
        $refs = array_values(array_filter(array_map('trim', Args::lista($args['memorias'] ?? null)), fn (string $s): bool => $s !== ''));
        if ($refs === []) {
            return Resultado::erro('O parâmetro memorias é obrigatório -- envie ao menos um título ou id.');
        }

        $indice = $this->indice();
        $ids = [];
        $warnings = [];
        foreach ($refs as $ref) {
            ['hit' => $hit, 'erro' => $erro] = $this->resolver($indice, $ref);
            if ($hit !== null) {
                if (! in_array($hit->mem_id, $ids, true)) {
                    $ids[] = $hit->mem_id;
                }
            } else {
                $warnings[] = $erro;
            }
        }
        if ($ids === []) {
            return Resultado::erro(implode("\n", $warnings));
        }

        $memorias = Memoria::query()->with('registros')->whereKey($ids)->get()
            ->sortBy(fn (Memoria $m): int => (int) array_search($m->mem_id, $ids, true))->values();

        return Resultado::json([
            ...($warnings !== [] ? ['warnings' => $warnings] : []),
            'memorias' => $memorias->map(fn (Memoria $m): array => [
                'id' => $m->mem_id, 'titulo' => $m->mem_titulo, 'categoria' => $m->mem_categoria, 'descricao' => $m->mem_descricao,
                'atualizada_em' => $m->mem_updated_at?->toIso8601String(),
                'registros' => $m->registros->map(fn (MemoriaRegistro $r): array => [
                    'id' => $r->mrg_id, 'texto' => $r->mrg_texto, 'origem' => $r->mrg_origem,
                    'data' => $r->mrg_created_at?->toIso8601String(),
                    ...($r->mrg_updated_at?->notEqualTo($r->mrg_created_at) ? ['editado_em' => $r->mrg_updated_at->toIso8601String()] : []),
                ])->all(),
            ])->all(),
        ]);
    }
}
