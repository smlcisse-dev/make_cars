<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Code de vérification de l'email envoyé à l'inscription professionnelle
 * (CLAUDE.md §5, ajout v0.26) — aucun compte n'existe encore à ce stade.
 */
class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly int $validityMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre code de vérification Make Cars',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',
        );
    }
}
