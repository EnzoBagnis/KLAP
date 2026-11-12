<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * Compte d'une personne de l'équipe KLAP. Il n'y a pas d'inscription libre : on crée un
 * compte en acceptant une invitation, ou avec la commande klap:admin pour la première
 * administratrice.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property string|null $phone
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Pivot $pivot la ligne de liaison, quand on a chargé le modèle par une relation plusieurs-à-plusieurs
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * On branche les réactions du modèle à ses propres changements.
     */
    protected static function booted(): void
    {
        // Quand on désactive un compte, on supprime ses jetons : la personne est déconnectée
        // tout de suite, et une réactivation l'oblige à se reconnecter (#148).
        static::updated(function (User $user) {
            if ($user->wasChanged('is_active') && ! $user->is_active) {
                $user->tokens()->delete();
            }
        });
    }

    /**
     * On vérifie que le compte a l'un des rôles donnés.
     */
    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, strict: true);
    }

    /**
     * On vérifie que le compte est celui d'une administratrice.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }
}
