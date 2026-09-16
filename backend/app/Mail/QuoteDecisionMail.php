<?php

namespace App\Mail;

use App\Models\QuoteVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Envoyé à un client "compte express" (sans app) lors de l'envoi d'un devis
 * (CLAUDE.md §5, ajout v0.9) : contient le PDF du devis en pièce jointe et
 * deux boutons (accepter/refuser) pointant vers les liens signés, sans
 * connexion à l'application requise.
 */
class QuoteDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly QuoteVersion $version,
        public readonly string $acceptUrl,
        public readonly string $rejectUrl,
    ) {}

    public function envelope(): Envelope
    {
        $garageName = $this->version->quote->garage->name;

        return new Envelope(
            subject: $this->version->version === 1
                ? "Devis de {$garageName} — Make Cars"
                : "Nouvelle proposition de devis de {$garageName} — Make Cars",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quote-decision',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk($this->version->pdf_disk, $this->version->pdf_path)
                ->as("devis-{$this->version->quote_id}-v{$this->version->version}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
