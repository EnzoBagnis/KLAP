<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Bienvenue de l'administratrice créée par `klap:admin` (#255) : choisir son mot de passe. */
class AdminWelcomeMail extends Mailable
{
    public function __construct(public User $user, public string $url, public int $minutes, public string $loginUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre compte d’administratrice sur le carnet de bord de KLAP');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.admin-welcome');
    }
}
