<?php

declare(strict_types=1);

namespace Modules\Core\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Um projeto foi excluído (soft delete). Quem guarda o id dele — eventos (que
 * ficam sem projeto) e notas (que perdem o vínculo) — reage; o dono do
 * projeto não conhece os ouvintes. É o "on delete set null" do banco antigo
 * que o SoftDeletes não dispara sozinho.
 */
final class ProjetoExcluido
{
    use Dispatchable;

    public function __construct(
        public readonly string $entityId,
        public readonly string $projetoId,
    ) {}
}
