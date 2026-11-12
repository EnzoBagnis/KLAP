<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Mail\AdminWelcomeMail;
use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Premier compte d'administratrice (#255). Sur une base neuve, personne ne peut inviter
 * personne : l'interface n'invite que des intervenant·e·s et des super-utilisatrices, et une
 * invitation a toujours un auteur. On lance donc cette commande sur le serveur : elle crée le
 * compte avec un mot de passe aléatoire que personne ne connaît, puis envoie un mail pour en
 * choisir un. Si on la relance pour la même adresse, elle renvoie seulement un nouveau lien.
 */
class CreateAdmin extends Command
{
    /** @var string le nom de la commande et ses arguments */
    protected $signature = 'klap:admin {email : Adresse mail de l’administratrice} {nom : Prénom et nom, entre guillemets}';

    /** @var string ce qu'affiche `php artisan list` */
    protected $description = 'Crée le compte d’une administratrice et lui envoie le lien pour choisir son mot de passe';

    /**
     * On crée le compte s'il n'existe pas, puis on envoie le lien pour choisir le mot de passe.
     *
     * @return int le code de sortie de la commande
     */
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
        if ($user && ! $user->isAdmin()) {
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
        /** @var PasswordBroker $broker */
        $broker = Password::broker();
        $token = $broker->createToken($user);
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
