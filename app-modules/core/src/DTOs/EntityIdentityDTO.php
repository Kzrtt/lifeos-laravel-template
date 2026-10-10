<?php

declare(strict_types=1);

namespace Modules\Core\DTOs;

/**
 * Identidade pública de uma instância (o que alimenta o lifeos-config.js): nome,
 * subtítulo, imagens e tema padrão. Sem nada sensível.
 */
final readonly class EntityIdentityDTO
{
    public function __construct(
        public string $id,
        public string $nome,
        public ?string $sub,
        public ?string $banner,
        public ?string $avatar,
        public string $tema,
        public string $hubCapa,
        public bool $blogHabilitado,
    ) {}
}
