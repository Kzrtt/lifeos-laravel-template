<?php

declare(strict_types=1);

namespace Modules\Tenancy\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Tenancy\Models\Entity;

/** @extends Factory<Entity> */
final class EntityFactory extends Factory
{
    protected $model = Entity::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'ent_name' => fake()->company(),
            'ent_email' => fake()->unique()->safeEmail(),
            'ent_slug' => fake()->unique()->slug(2),
        ];
    }
}
