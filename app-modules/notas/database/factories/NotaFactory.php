<?php

declare(strict_types=1);

namespace Modules\Notas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Notas\Models\Nota;

/** @extends Factory<Nota> */
final class NotaFactory extends Factory
{
    protected $model = Nota::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['not_name' => fake()->sentence(3), 'not_tipo' => [], 'not_data' => null, 'not_conteudo_md' => null];
    }
}
