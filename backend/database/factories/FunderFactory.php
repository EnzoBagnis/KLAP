<?php

namespace Database\Factories;

use App\Models\Funder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Funder>
 */
class FunderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'type' => 'other',
        ];
    }
}
