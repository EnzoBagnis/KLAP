<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\SubProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fabrique de tests : un groupe.
 *
 * @extends Factory<SubProject>
 */
class SubProjectFactory extends Factory
{
    /**
     * Les valeurs par défaut.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->words(2, true),
            'city' => 'Marseille',
            'postal_code' => '13003',
        ];
    }
}
