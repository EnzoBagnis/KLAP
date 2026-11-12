<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Mail d'invitation : on y met le lien d'activation vers le frontend, avec le jeton en clair.
 * C'est le seul endroit où le jeton apparaît : en base, on ne garde que son empreinte.
 */
class InvitationMail extends Mailable
{
    /**
     * @param  string  $url  le lien d'activation, jeton compris
     */
    public function __construct(public Invitation $invitation, public string $url) {}

    /**
     * L'objet du mail.
     */
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre accès au carnet de bord de KLAP');
    }

    /**
     * Le corps du mail, en texte brut : il passe mieux les filtres anti-spam et se lit partout.
     */
    public function content(): Content
    {
        return new Content(text: 'mail.invitation');
    }
}
