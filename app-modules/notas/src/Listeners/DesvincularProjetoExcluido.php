<?php

declare(strict_types=1);

namespace Modules\Notas\Listeners;

use Modules\Core\Events\ProjetoExcluido;
use Modules\Core\Tenancy\TenantContext;
use Modules\Notas\Models\NotaProjeto;

/** Projeto excluído: as notas perdem o vínculo (o "cascade" do banco antigo). */
final class DesvincularProjetoExcluido
{
    public function handle(ProjetoExcluido $evento): void
    {
        TenantContext::runAs($evento->entityId, function () use ($evento): void {
            NotaProjeto::query()->where('projeto_prj_id', $evento->projetoId)->get()->each->delete();
        });
    }
}
