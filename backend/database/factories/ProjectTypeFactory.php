<?php

namespace Database\Factories;

use App\Models\ProjectType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fabrique de tests : un type de projet actif.
 *
 * @extends Factory<ProjectType>
 */
class ProjectTypeFactory extends Factory
{
    /**
     * Les valeurs par défaut.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'is_active' => true,
        ];
    }

    /**
     * Un type désactivé.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
