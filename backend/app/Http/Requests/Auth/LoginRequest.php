<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Validation du formulaire de connexion avec protection anti-force brute (Rate Limiting).
 * Bloque automatiquement les attaques par dictionnaire après 5 tentatives échouées par minute.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => "L'adresse e-mail est obligatoire.",
            'email.email' => "L'adresse e-mail doit être valide.",
            'password.required' => 'Le mot de passe est obligatoire.',
        ];
    }

    /**
     * Tente d'authentifier l'utilisateur en appliquant le rate limiting.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => trim((string) $this->input('email')),
            'password' => (string) $this->input('password'),
        ];

        if (! Auth::attempt($credentials, $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects ou compte inactif.'],
            ]);
        }

        // Vérification que le compte est bien actif
        if (! Auth::user()->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => ['Ce compte utilisateur a été désactivé par l’administration.'],
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Vérifie que l'utilisateur n'a pas dépassé 5 tentatives en 1 minute.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => ["Trop de tentatives de connexion. Veuillez réessayer dans {$seconds} secondes."],
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower(trim((string) $this->input('email'))).'|'.$this->ip());
    }
}
