<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Fabrique de tests : une invitation en cours, envoyée par une administratrice.
 *
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    /**
     * Les valeurs par défaut.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'full_name' => fake()->name(),
            'role' => UserRole::Contributor,
            'token_hash' => Invitation::hashToken(Str::random(64)),
            'invited_by' => User::factory()->role(UserRole::Admin),
            'sent_at' => now(),
            'expires_at' => now()->addDays(Invitation::VALID_DAYS),
        ];
    }

    /**
     * On fixe le jeton, pour pouvoir l'utiliser dans un test.
     */
    public function withToken(string $token): static
    {
        return $this->state(['token_hash' => Invitation::hashToken($token)]);
    }
}
