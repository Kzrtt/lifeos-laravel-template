<?php

declare(strict_types=1);

namespace Modules\Memoria\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Memoria\Models\Memoria;

/** @extends Factory<Memoria> */
final class MemoriaFactory extends Factory
{
    protected $model = Memoria::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['mem_titulo' => fake()->unique()->sentence(3), 'mem_descricao' => '', 'mem_categoria' => 'Perfil'];
    }
}
