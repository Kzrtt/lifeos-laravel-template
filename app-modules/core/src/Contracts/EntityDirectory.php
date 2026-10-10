<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Modules\Core\DTOs\EntityIdentityDTO;

/**
 * Porta de LEITURA da identidade das instâncias. Antes do login ninguém sabe
 * de quem é a página; quem precisa (painel, arquivo) pergunta aqui em vez de
 * importar o model de tenancy. Implementada por `tenancy`.
 */
interface EntityDirectory
{
    public function bySlug(string $slug): ?EntityIdentityDTO;
}
