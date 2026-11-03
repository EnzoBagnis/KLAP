<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->sentence(3),
            'project_type' => 'Partenariat lycée',
            'planned_sessions' => 20,
            'start_date' => '2026-09-15',
            'end_date' => '2027-06-19',
            'status' => 'active',
            'default_location_id' => Location::factory(),
            'created_by' => User::factory(),
        ];
    }
}
