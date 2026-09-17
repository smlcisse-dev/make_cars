<?php

namespace App\Services;

use App\Mail\ExpressClientClaimMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/**
 * Réclamation d'un compte "express" par son propriétaire réel (CLAUDE.md §5,
 * ajout v0.17 — ferme le point ouvert correspondant, §7). Même mécanisme de
 * lien signé que la validation de devis par email (§5, ajout v0.9) : la
 * signature + expiration remplacent l'authentification, à usage limité.
 *
 * La vérification Laravel d'un lien signé ne porte que sur l'URL (chemin +
 * query), jamais sur la méthode HTTP ni le nom de route — le même lien signé
 * généré pour la route GET (affichage) reste donc valide sur la route POST
 * (confirmation du mot de passe) tant que le chemin est identique. Ça évite
 * de générer/exposer deux liens distincts pour une seule action perçue par
 * le client, et ça préserve la promesse du futur frontend Vue (CLAUDE.md §5,
 * ajout v0.9) : rediriger le clic vers une page Vue qui appelle ensuite ce
 * même endpoint en POST, sans aucun changement côté backend.
 */
class ExpressClientClaimService
{
    private const EXPIRATION_DAYS = 7;

    /**
     * @throws ValidationException si aucun compte express ne correspond à cet email.
     */
    public function requestClaim(string $email): void
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! $user->is_express) {
            throw ValidationException::withMessages([
                'email' => ['Aucun compte express en attente de réclamation ne correspond à cet email.'],
            ]);
        }

        Mail::to($email)->send(new ExpressClientClaimMail($user, $this->signedUrl($user)));
    }

    /**
     * @return array{name: string, email: ?string}
     */
    public function show(User $user): array
    {
        $this->assertClaimable($user);

        return ['name' => $user->name, 'email' => $user->email];
    }

    public function confirm(User $user, string $password): User
    {
        $this->assertClaimable($user);

        $user->update(['password' => $password, 'is_express' => false]);

        return $user;
    }

    /**
     * Lien déjà utilisé : le compte n'est plus "express", donc plus
     * réclamable — même famille d'erreur qu'une décision de devis déjà
     * tranchée (QuoteService::assertVersionIsDecidable).
     */
    private function assertClaimable(User $user): void
    {
        abort_if(! $user->is_express, 409, 'Ce compte a déjà été réclamé. Connectez-vous avec votre email et votre mot de passe.');
    }

    private function signedUrl(User $user): string
    {
        return URL::temporarySignedRoute(
            'express-clients.claim.show',
            now()->addDays(self::EXPIRATION_DAYS),
            ['user' => $user->id],
        );
    }
}
