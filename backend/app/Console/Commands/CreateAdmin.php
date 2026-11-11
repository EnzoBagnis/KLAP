<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Mail\AdminWelcomeMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Premier compte d'administratrice (#255). Sur une base neuve, personne ne peut inviter
 * personne : l'interface n'invite que des intervenant·e·s et des super-utilisatrices, et une
 * invitation a toujours un auteur. Cette commande, lancée sur le serveur, crée le compte avec un
 * mot de passe aléatoire que personne ne connaît, puis envoie un mail pour en choisir un.
 * Relancée pour la même adresse, elle renvoie seulement un nouveau lien.
 */
class CreateAdmin extends Command
{
    protected $signature = 'klap:admin {email : Adresse mail de l’administratrice} {nom : Prénom et nom, entre guillemets}';

    protected $description = 'Crée le compte d’une administratrice et lui envoie le lien pour choisir son mot de passe';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        $name = preg_replace('/\s+/u', ' ', trim((string) $this->argument('nom')));

        $validator = Validator::make(['email' => $email, 'nom' => $name], [
            'email' => ['required', 'email', 'max:190'],
            'nom' => ['required', 'string', 'max:160'],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::INVALID;
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($user && $user->role !== UserRole::Admin) {
            $this->error("Un compte existe déjà pour {$email}, avec un autre rôle : rien n’est modifié.");

            return self::FAILURE;
        }
        if ($user && ! $user->is_active) {
            $this->error("Le compte de {$email} est désactivé : réactivez-le depuis Gestion › Utilisateurs.");

            return self::FAILURE;
        }

        $created = $user === null;
        $user ??= User::query()->create([
            'name' => $name,
            'email' => $email,
            'role' => UserRole::Admin,
            'is_active' => true,
            'password' => Str::password(48),
        ]);

        $minutes = (int) config('auth.passwords.users.expire');
        $front = rtrim((string) config('app.frontend_url'), '/');
        $token = Password::broker()->createToken($user);
        Mail::to($user->email)->send(new AdminWelcomeMail(
            $user,
            $front.'/reinitialisation/'.$token.'?email='.rawurlencode($user->email),
            $minutes,
            $front.'/connexion',
        ));

        $this->info($created
            ? "Compte d’administratrice créé pour {$user->name} ({$user->email}). Le mail pour choisir le mot de passe est parti."
            : "Le compte de {$user->email} existait déjà : un nouveau lien vient de lui être envoyé.");

        return self::SUCCESS;
    }
}
