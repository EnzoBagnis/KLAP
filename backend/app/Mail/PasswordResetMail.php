<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Mail « mot de passe oublié » (#81) : on y met le lien vers le frontend, avec le jeton en clair.
 */
class PasswordResetMail extends Mailable
{
    /**
     * @param  string  $url  le lien de réinitialisation, jeton compris
     * @param  int  $minutes  la durée de validité du lien
     */
    public function __construct(public User $user, public string $url, public int $minutes) {}

    /**
     * L'objet du mail.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nouveau mot de passe pour le carnet de bord de KLAP');
    }

    /**
     * Le corps du mail, en texte brut : il passe mieux les filtres anti-spam et se lit partout.
     */
    public function content(): Content
    {
        return new Content(text: 'mail.password-reset');
    }
}
