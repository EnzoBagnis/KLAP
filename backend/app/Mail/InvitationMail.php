<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Mail d'invitation : lien d'activation vers le frontend, avec le jeton en clair. */
class InvitationMail extends Mailable
{
    public function __construct(public Invitation $invitation, public string $url) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre accès au carnet de bord de KLAP');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.invitation');
    }
}
