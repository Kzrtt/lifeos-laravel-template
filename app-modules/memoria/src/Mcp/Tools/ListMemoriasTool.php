<?php

declare(strict_types=1);

namespace Modules\Memoria\Mcp\Tools;

use Modules\Core\Support\Mcp\Args;
use Modules\Core\Support\Mcp\Resultado;
use Modules\Memoria\Models\Memoria;

final class ListMemoriasTool extends AbstractMemoriaTool
{
    public function ordem(): int
    {
        return 15;
    }

    protected function arquivo(): string
    {
        return dirname(__DIR__, 3).'/resources/mcp/list_memorias.json';
    }

    public function chamar(array $args): array
    {
        $categoria = ! empty($args['categoria']) ? Args::str($args['categoria']) : '';
        $validas = $this->vocabulario->valores('memoria_categoria');
        if ($categoria !== '' && ! in_array($categoria, $validas, true)) {
            return Resultado::erro("Categoria inválida: {$categoria}. Valores aceitos: ".Args::juntar($validas).'.');
        }

        $memorias = $this->indice();
        if ($categoria !== '') {
            $memorias = $memorias->filter(fn (Memoria $m): bool => $m->mem_categoria === $categoria)->values();
        }

        return Resultado::json([
            'total' => $memorias->count(),
            'memorias' => $memorias->map(fn (Memoria $m): array => [
                'id' => $m->mem_id, 'titulo' => $m->mem_titulo, 'categoria' => $m->mem_categoria, 'descricao' => $m->mem_descricao,
                'registros' => $m->registros_count, 'atualizada_em' => $m->mem_updated_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
