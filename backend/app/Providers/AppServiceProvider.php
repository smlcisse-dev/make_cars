<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Limites de débit de tous les endpoints publics (CLAUDE.md §4) — seul
     * endroit où leurs valeurs sont définies ; les routes n'y font référence
     * que par leur nom (`throttle:login`, `throttle:public`…). Le 429 est
     * rendu en français, avec `retry_after`, dans bootstrap/app.php.
     */
    private function configureRateLimiting(): void
    {
        // Connexion email/mot de passe : 5 échecs par minute pour un même
        // email depuis une même IP (force brute sur un compte), et 20
        // tentatives par minute par IP, tous emails confondus (essais sur
        // de nombreux comptes). Seuls les échecs comptent pour la paire
        // email + IP, et une connexion réussie remet ce compteur à zéro :
        // quelqu'un qui retrouve son mot de passe n'a pas à attendre.
        RateLimiter::for('login', function (Request $request) {
            $emailKey = Str::lower((string) $request->input('email')).'|'.$request->ip();

            return [
                Limit::perMinute(5)->by('email:'.$emailKey)->after(function (Response $response) use ($emailKey) {
                    if ($response->isSuccessful()) {
                        RateLimiter::clear(self::throttleKey('login', 'email:'.$emailKey));

                        return false;
                    }

                    return true;
                }),
                Limit::perMinute(20)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('login-google', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Inscription professionnelle et mot de passe oublié par code email
        // (ajouts v0.26 et v0.29) : freine la force brute sur le code à 6
        // chiffres et l'envoi massif d'emails.
        RateLimiter::for('email-code', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Demande de lien de réclamation d'un compte express : 3 envois par
        // minute pour une même adresse (pas de rafale d'emails vers une
        // boîte), 10 par IP.
        RateLimiter::for('express-claim', fn (Request $request) => [
            Limit::perMinute(3)->by('email:'.Str::lower((string) $request->input('email'))),
            Limit::perMinute(10)->by('ip:'.$request->ip()),
        ]);

        // Liens signés reçus par email (ajout v0.30) : la signature protège
        // déjà l'accès ; la limite borne les essais de signatures.
        RateLimiter::for('email-link', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Lectures publiques (santé, référentiel des localisations, listes et
        // recherche de l'app mobile) : large, pour ne jamais gêner un usage
        // normal, mais borne l'aspiration massive des données.
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }

    /**
     * Clé de cache qu'utilise le middleware `throttle` pour une limite
     * nommée (ThrottleRequests::handleRequestUsingNamedLimiter), pour
     * pouvoir la remettre à zéro.
     */
    private static function throttleKey(string $limiterName, string $key): string
    {
        return md5($limiterName.$key);
    }
}
