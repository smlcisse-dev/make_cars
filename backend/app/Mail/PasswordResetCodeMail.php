<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Code de réinitialisation du mot de passe (CLAUDE.md §5, ajout v0.29).
 */
class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly int $validityMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Réinitialisation de votre mot de passe Make Cars',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset-code',
        );
    }
}
