<?php

declare(strict_types=1);

namespace Modules\Agenda\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Agenda\Models\Evento;

/** @extends Factory<Evento> */
final class EventoFactory extends Factory
{
    protected $model = Evento::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['evt_name' => fake()->sentence(2), 'evt_date' => '2026-10-10', 'evt_date_fim' => null, 'evt_tipo' => 'vida'];
    }
}
