<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Mail « mot de passe oublié » (#81) : lien vers le frontend, avec le jeton en clair. */
class PasswordResetMail extends Mailable
{
    public function __construct(public User $user, public string $url, public int $minutes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nouveau mot de passe pour le carnet de bord de KLAP');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.password-reset');
    }
}
