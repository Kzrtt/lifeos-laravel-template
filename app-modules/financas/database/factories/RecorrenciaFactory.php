<?php

declare(strict_types=1);

namespace Modules\Financas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Financas\Models\Recorrencia;

/** @extends Factory<Recorrencia> */
final class RecorrenciaFactory extends Factory
{
    protected $model = Recorrencia::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['rec_nome' => fake()->unique()->word(), 'rec_direcao' => 'Saida', 'rec_valor_min' => 10, 'rec_valor_max' => 20, 'rec_meio' => null, 'rec_dia' => null, 'rec_ativa' => true];
    }
}
