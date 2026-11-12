<?php

namespace Database\Factories;

use App\Models\Funder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fabrique de tests : un financeur.
 *
 * @extends Factory<Funder>
 */
class FunderFactory extends Factory
{
    /**
     * Les valeurs par défaut.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'type' => 'other',
        ];
    }
}
