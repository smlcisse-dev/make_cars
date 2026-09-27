<?php

namespace App\Support;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Envoi d'un email APRÈS la réponse HTTP : la personne voit la page
 * suivante sans attendre le fournisseur d'emails (plusieurs centaines de
 * millisecondes, parfois plus). Pas de file d'attente : l'hébergement
 * gratuit n'a pas de worker ; l'envoi a lieu dans le même processus, une
 * fois la réponse partie (PHP-FPM, `fastcgi_finish_request`). Sous
 * `php artisan serve`, la réponse n'est rendue qu'après l'envoi : aucun
 * changement en développement.
 *
 * - Jamais envoyé si la requête finit en erreur (statut ≥ 400) : un email
 *   ne part pas pour une action annulée.
 * - Hors requête HTTP (commande artisan, seeder), envoyé à la fin de la
 *   commande si elle réussit.
 * - Un échec d'envoi est écrit dans le journal (report) : la réponse est
 *   déjà partie, il ne peut plus être montré à la personne.
 */
class DeferredMail
{
    public static function send(string $to, Mailable $mail): void
    {
        defer(function () use ($to, $mail) {
            try {
                Mail::to($to)->send($mail);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
