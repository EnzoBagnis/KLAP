<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fabrique de tests : un lieu actif à Marseille.
 *
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
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
            'address' => fake()->streetAddress(),
            'postal_code' => '13003',
            'city' => 'Marseille',
            'is_klap' => false,
            'is_active' => true,
        ];
    }

    /**
     * Un lieu désactivé.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
