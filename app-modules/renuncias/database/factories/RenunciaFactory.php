<?php

declare(strict_types=1);

namespace Modules\Renuncias\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Renuncias\Models\Renuncia;

/** @extends Factory<Renuncia> */
final class RenunciaFactory extends Factory
{
    protected $model = Renuncia::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['ren_nome' => fake()->unique()->word(), 'ren_emoji' => '🚭', 'ren_desde' => now()->subDays(3), 'ren_arquivada' => false];
    }
}
