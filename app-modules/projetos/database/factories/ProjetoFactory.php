<?php

declare(strict_types=1);

namespace Modules\Projetos\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projetos\Models\Projeto;

/** @extends Factory<Projeto> */
final class ProjetoFactory extends Factory
{
    protected $model = Projeto::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['prj_name' => fake()->words(2, true), 'prj_emoji' => null, 'prj_status' => 'Em Progresso', 'prj_tags' => []];
    }
}
