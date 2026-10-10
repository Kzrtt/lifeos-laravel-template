<?php

declare(strict_types=1);

namespace Modules\Tenancy\ReadModels;

use Modules\Core\Contracts\EntityDirectory;
use Modules\Core\DTOs\EntityIdentityDTO;
use Modules\Tenancy\Models\Entity;

final class EloquentEntityDirectory implements EntityDirectory
{
    public function bySlug(string $slug): ?EntityIdentityDTO
    {
        $entity = Entity::query()->where('ent_slug', $slug)->first();

        return $entity === null ? null : new EntityIdentityDTO(
            id: $entity->ent_id,
            nome: $entity->ent_name,
            sub: $entity->ent_sub,
            banner: $entity->ent_banner,
            avatar: $entity->ent_avatar,
            tema: $entity->ent_tema,
            hubCapa: $entity->ent_hub_capa,
            blogHabilitado: $entity->ent_blog_habilitado,
        );
    }
}
