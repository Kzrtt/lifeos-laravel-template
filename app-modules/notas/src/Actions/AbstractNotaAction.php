<?php

declare(strict_types=1);

namespace Modules\Notas\Actions;

use Modules\Core\Actions\RpcAction;
use Modules\Notas\Models\Nota;

/** Base de Criar/Atualizar: normalização e sincronização dos projetos vinculados. */
abstract class AbstractNotaAction extends RpcAction
{
    /**
     * Ausente => []; não-array => 'invalido' (falha a regra `array`). Cada id é
     * aparado; um vazio falha em `required`.
     *
     * @param  array<string, mixed>  $bruto
     * @return list<string>|string
     */
    protected function projetoIds(array $bruto, string $chave): array|string
    {
        if (! array_key_exists($chave, $bruto)) {
            return [];
        }
        if (! is_array($bruto[$chave])) {
            return 'invalido';
        }

        return array_values(array_map(fn ($p): string => $this->texto($p), $bruto[$chave]));
    }

    /** @param list<string> $ids */
    protected function sincronizar(Nota $nota, array $ids): void
    {
        $nota->vinculos()->get()->each->delete();
        foreach ($ids as $id) {
            $nota->vinculos()->create(['projeto_prj_id' => $id]);
        }
    }
}
