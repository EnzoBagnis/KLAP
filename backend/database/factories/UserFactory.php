<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Fabrique de tests : un compte.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Le mot de passe haché, qu'on ne calcule qu'une fois pour tous les comptes d'un test.
     */
    protected static ?string $password;

    /**
     * Les valeurs par défaut.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Un compte du rôle donné.
     */
    public function role(UserRole $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role,
        ]);
    }

    /**
     * Un compte dont l'adresse n'est pas vérifiée.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
