<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Mail de bienvenue de l'administratrice qu'on crée avec `klap:admin` (#255) : il l'invite
 * à choisir son mot de passe.
 */
class AdminWelcomeMail extends Mailable
{
    /**
     * @param  string  $url  le lien pour choisir son mot de passe
     * @param  int  $minutes  la durée de validité du lien
     * @param  string  $loginUrl  l'adresse de la page de connexion
     */
    public function __construct(public User $user, public string $url, public int $minutes, public string $loginUrl) {}

    /**
     * L'objet du mail.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre compte d’administratrice sur le carnet de bord de KLAP');
    }

    /**
     * Le corps du mail, en texte brut : il passe mieux les filtres anti-spam et se lit partout.
     */
    public function content(): Content
    {
        return new Content(text: 'mail.admin-welcome');
    }
}
