<?php

declare(strict_types=1);

namespace Modules\Projetos\Actions;

use Modules\Core\Actions\RpcAction;
use Modules\Core\Events\ProjetoExcluido;
use Modules\Core\Exceptions\LifeosException;
use Modules\Projetos\Models\Projeto;

final class ExcluirProjeto extends RpcAction
{
    protected function sanitize(array $input): array
    {
        return ['id' => $this->texto($input['id'] ?? null)];
    }

    protected function rules(): array
    {
        return ['id' => ['required', 'string']];
    }

    protected function codes(): array
    {
        return ['id' => 'missing_id'];
    }

    protected function handle(array $data): mixed
    {
        $projeto = $this->achar(Projeto::class, $data['id']);

        // O banco barra por FK restrict, mas o projeto é "apagado" por
        // SoftDeletes e a FK não dispara: a regra é garantida aqui.
        if ($projeto->tarefas()->exists()) {
            throw LifeosException::conflict('has_tarefas');
        }

        $projeto->delete();

        // Eventos ficam sem projeto e notas perdem o vínculo (ouvintes nos módulos donos).
        ProjetoExcluido::dispatch($projeto->entity_ent_id, $projeto->prj_id);

        return $data['id'];
    }
}
