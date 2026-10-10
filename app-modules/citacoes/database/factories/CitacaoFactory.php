<?php

declare(strict_types=1);

namespace Modules\Citacoes\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Citacoes\Models\Citacao;

/** @extends Factory<Citacao> */
final class CitacaoFactory extends Factory
{
    protected $model = Citacao::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'cit_texto' => fake()->sentence(),
            'cit_autor' => fake()->name(),
        ];
    }
}
