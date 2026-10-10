<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Modules\Core\DTOs\ProjetoResumoDTO;

/**
 * Porta de LEITURA de projetos. Notas, agenda e as tools do MCP guardam o id do
 * projeto e, para validar e nomear, perguntam aqui em vez de importar o model de
 * `projetos`. Implementada por `projetos`.
 */
interface ProjetoReadModel
{
    /**
     * Dos ids informados, os que existem (não apagados) na instância atual.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    public function existentes(array $ids): array;

    /**
     * Todos os projetos da instância, por nome e id (a ordem do catálogo de hoje).
     *
     * @return list<ProjetoResumoDTO>
     */
    public function catalogo(): array;
}
