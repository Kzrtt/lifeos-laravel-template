<?php

declare(strict_types=1);

namespace Modules\Financas\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Financas\Models\Movimentacao;

/** @extends Factory<Movimentacao> */
final class MovimentacaoFactory extends Factory
{
    protected $model = Movimentacao::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['mov_name' => fake()->word(), 'mov_valor' => 10, 'mov_date' => '2026-10-01', 'mov_tipo' => ['Saida', 'Pix']];
    }
}
