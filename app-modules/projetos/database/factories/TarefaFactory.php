<?php

declare(strict_types=1);

namespace Modules\Projetos\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Projetos\Models\Projeto;
use Modules\Projetos\Models\Tarefa;

/** @extends Factory<Tarefa> */
final class TarefaFactory extends Factory
{
    protected $model = Tarefa::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'projeto_prj_id' => Projeto::factory(),
            'tar_name' => fake()->sentence(3),
            'tar_status' => 'Não Iniciado',
            'tar_tipo' => [],
        ];
    }
}
