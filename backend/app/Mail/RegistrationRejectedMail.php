<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Dossier d'inscription professionnelle refusé, avec le motif de
 * l'administrateur (CLAUDE.md §5, ajout v0.26) : le professionnel corrige son
 * profil puis soumet à nouveau.
 */
class RegistrationRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $reason,
        public readonly string $profileUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre dossier Make Cars doit être corrigé',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.registration-rejected',
        );
    }
}
