<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Location;
use App\Models\Project;
use App\Models\ScheduledSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fabrique de tests : une séance prévue.
 *
 * @extends Factory<ScheduledSession>
 */
class ScheduledSessionFactory extends Factory
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
            'session_date' => '2026-10-05',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'location_id' => Location::factory(),
            'referent_id' => User::factory()->role(UserRole::Contributor),
            'session_type' => 'practice_workshop',
            'status' => 'planned',
            'created_by' => User::factory(),
        ];
    }
}
