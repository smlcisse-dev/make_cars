<?php

namespace App\Mail;

use App\Models\Dispute;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifie l'automobiliste de la décision finale prise sur sa réclamation
 * (fondée ou rejetée) — CLAUDE.md §5, ajout v0.11. Seul canal disponible
 * pour l'instant : pas de notifications push (§7) et pas de chat
 * Admin↔Automobiliste (le chat existant est scopé Garage↔Automobiliste,
 * ajout v0.8).
 */
class DisputeDecisionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Dispute $dispute) {}

    public function envelope(): Envelope
    {
        $respondentName = $this->dispute->respondent->name;

        return new Envelope(
            subject: "Décision sur votre réclamation concernant {$respondentName} — Make Cars",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.dispute-decision',
        );
    }
}
