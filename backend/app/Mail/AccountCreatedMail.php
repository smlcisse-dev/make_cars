<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Envoyé une fois l'email vérifié et le compte professionnel créé
 * (CLAUDE.md §5, ajout v0.26) : invite à compléter le profil avant de
 * soumettre le dossier. Pas de connexion automatique : le lien mène à la page
 * profil, atteinte après connexion.
 */
class AccountCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $profileUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre compte Make Cars a été créé',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-created',
        );
    }
}
