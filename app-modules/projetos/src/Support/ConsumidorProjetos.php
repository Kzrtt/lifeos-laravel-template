<?php

declare(strict_types=1);

namespace Modules\Projetos\Support;

use Modules\Core\Support\ConsumidorPorModelo;
use Modules\Projetos\Models\Projeto;
use Modules\Projetos\Models\Tarefa;

/** Os domínios de vocabulário que este módulo grava. */
final class ConsumidorProjetos extends ConsumidorPorModelo
{
    protected function mapa(): array
    {
        return [
            'projeto_status' => [Projeto::class, 'prj_status', false],
            'projeto_tag' => [Projeto::class, 'prj_tags', true],
            'tarefa_status' => [Tarefa::class, 'tar_status', false],
            'tarefa_tipo' => [Tarefa::class, 'tar_tipo', true],
        ];
    }
}
