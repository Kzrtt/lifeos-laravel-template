<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Porta de LEITURA do vocabulário editável (tags e status). Todo domínio valida
 * contra ele e o schema das tools do MCP lê dele. Implementada por `vocabularios`.
 * Se a leitura falhar, a implementação devolve os valores embutidos — o mesmo
 * fallback que as Edge Functions de hoje mantêm.
 */
interface VocabularioReadModel
{
    /**
     * Valores do domínio, na ordem de exibição.
     *
     * @return list<string>
     */
    public function valores(string $dominio): array;
}
