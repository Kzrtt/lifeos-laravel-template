<?php

declare(strict_types=1);

namespace Modules\Agenda\Listeners;

use Modules\Agenda\Models\Evento;
use Modules\Core\Events\ProjetoExcluido;
use Modules\Core\Tenancy\TenantContext;

/** Projeto excluído: o evento fica, sem projeto (o "set null" do banco antigo). */
final class RemoverProjetoDosEventos
{
    public function handle(ProjetoExcluido $evento): void
    {
        TenantContext::runAs($evento->entityId, function () use ($evento): void {
            Evento::query()->where('projeto_prj_id', $evento->projetoId)->get()
                ->each->update(['projeto_prj_id' => null]);
        });
    }
}
