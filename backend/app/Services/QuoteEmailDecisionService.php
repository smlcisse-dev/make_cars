<?php

namespace App\Services;

use App\Mail\QuoteDecisionMail;
use App\Models\Quote;
use App\Models\QuoteVersion;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Mécanisme de validation par email pour un client "compte express" sans
 * app (CLAUDE.md §5, ajout v0.9) : un lien signé, à usage limité, permet
 * d'accepter/refuser un devis directement depuis la boîte mail. L'action
 * reste digitale, explicite et tracée (mêmes garanties que la validation via
 * l'app — CLAUDE.md §5, ajout v0.8) ; seul le canal diffère.
 */
class QuoteEmailDecisionService
{
    private const EXPIRATION_DAYS = 7;

    /**
     * N'envoie l'email que pour un client "compte express" (sans app) muni
     * d'un email — un automobiliste classique reçoit déjà la notification et
     * décide depuis l'app (CLAUDE.md §5, ajout v0.8).
     */
    public function notifyIfExpressClient(Quote $quote, QuoteVersion $version): void
    {
        $client = $quote->user;

        if (! $client->is_express || $client->email === null) {
            return;
        }

        Mail::to($client->email)->send(new QuoteDecisionMail(
            $version,
            $this->signedUrl('quotes.email-decision.accept', $quote, $version),
            $this->signedUrl('quotes.email-decision.reject', $quote, $version),
        ));
    }

    private function signedUrl(string $routeName, Quote $quote, QuoteVersion $version): string
    {
        return URL::temporarySignedRoute(
            $routeName,
            now()->addDays(self::EXPIRATION_DAYS),
            ['quote' => $quote->id, 'version' => $version->id],
        );
    }
}
