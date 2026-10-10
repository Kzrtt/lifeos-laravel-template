<?php

declare(strict_types=1);

namespace Modules\Agenda\Actions;

use Modules\Core\Actions\RpcAction;
use Modules\Core\Contracts\ProjetoReadModel;
use Modules\Core\Exceptions\LifeosException;

abstract class AbstractEventoAction extends RpcAction
{
    public function __construct(protected readonly ProjetoReadModel $projetos) {}

    /** O projeto, se informado, precisa existir na instância. */
    protected function checarProjeto(?string $id): void
    {
        if ($id !== null && $this->projetos->existentes([$id]) === []) {
            throw new LifeosException('invalid_projeto_id');
        }
    }

    /** Data real no formato Y-m-d (2026-02-31 não passa). */
    protected function dataReal(mixed $v): bool
    {
        if (! is_string($v) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) !== 1) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }
}
