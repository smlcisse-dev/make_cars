<?php

namespace App\Services;

use App\Mail\QuoteDecisionMail;
use App\Models\Quote;
use App\Models\QuoteVersion;
use App\Support\FrontendUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Mécanisme de validation par email pour un client "compte express" sans
 * app (CLAUDE.md §5, ajout v0.9) : un lien signé, à usage limité, permet
 * d'accepter/refuser un devis sans connexion. L'action reste digitale,
 * explicite et tracée (mêmes garanties que la validation via l'app —
 * CLAUDE.md §5, ajout v0.8) ; seul le canal diffère.
 *
 * Depuis l'ajout v0.30, les boutons de l'email mènent à une page du
 * frontend, qui lit le devis (GET, sans effet) puis décide sur une action
 * explicite du client (POST). Un GET ne décide jamais : les messageries et
 * antivirus ouvrent souvent les liens des emails pour les analyser.
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

        $link = rawurlencode($this->signedPath($quote, $version));

        Mail::to($client->email)->send(new QuoteDecisionMail(
            $version,
            FrontendUrl::to("devis/decision?link={$link}&choix=accepter"),
            FrontendUrl::to("devis/decision?link={$link}&choix=refuser"),
        ));
    }

    /**
     * Lecture seule, pour la page de décision : aucun effet sur le devis.
     * `$isDecidable` vient de QuoteService::isVersionDecidable, calculé par
     * l'appelant (QuoteService dépend déjà de ce service : pas d'injection
     * dans l'autre sens).
     *
     * @return array<string, mixed>
     */
    public function show(Quote $quote, QuoteVersion $version, bool $isDecidable, ?int $expires): array
    {
        $version->load('lines');

        return [
            'quote_id' => $quote->id,
            'version_id' => $version->id,
            'version' => $version->version,
            'garage_name' => $quote->garage->name,
            'client_name' => $quote->user->name,
            'lines' => $version->lines->map(fn ($line) => [
                'label' => $line->label,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'line_total' => $line->line_total,
            ])->values(),
            'total' => $version->total(),
            'status' => $quote->status,
            'decision' => $version->decision,
            'decided_at' => $version->decided_at,
            'is_decidable' => $isDecidable,
            'expires_at' => $expires !== null ? Carbon::createFromTimestamp($expires) : null,
        ];
    }

    /**
     * Signature relative (chemin + paramètres, sans hôte) : le frontend
     * appelle l'API par `VITE_API_BASE_URL`, dont l'hôte peut différer de
     * `APP_URL` (`127.0.0.1` contre `localhost`) — une signature absolue ne
     * serait alors plus reconnue. Vérifiée par le middleware `signed:relative`.
     */
    private function signedPath(Quote $quote, QuoteVersion $version): string
    {
        return URL::temporarySignedRoute(
            'quotes.email-decision',
            now()->addDays(self::EXPIRATION_DAYS),
            ['quote' => $quote->id, 'version' => $version->id],
            absolute: false,
        );
    }
}
