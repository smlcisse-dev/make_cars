<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Envoyé quand un client "compte express" demande à récupérer son compte
 * (CLAUDE.md §5, ajout v0.17) : contient un lien signé permettant de définir
 * un mot de passe, valable 7 jours, à usage unique.
 */
class ExpressClientClaimMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $claimUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Récupérez votre compte Make Cars',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.express-client-claim',
        );
    }
}
