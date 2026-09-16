<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Compte automobiliste "express" créé par un garagiste pour un client
 * présent physiquement, sans app ni compte (CLAUDE.md §5, ajout v0.9) :
 * chaque prestation doit rester traçable et évaluable, y compris pour un
 * client walk-in — c'est la donnée qui fait la valeur de la plateforme pour
 * assainir le secteur (CLAUDE.md §1). Ce compte pourra être "réclamé" plus
 * tard par son propriétaire réel (cf. CLAUDE.md §7, point ouvert).
 */
class ExpressClientService
{
    /**
     * Email prioritaire sur téléphone en cas de correspondances
     * contradictoires (cas limite non arbitré plus finement en V1).
     *
     * @param  array{name: string, email: string, phone: ?string}  $data
     */
    public function findOrCreateExpress(array $data): User
    {
        $existing = $this->findExistingAutomobiliste($data['email'], $data['phone'] ?? null);

        if ($existing) {
            return $existing;
        }

        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => AccountType::Automobiliste,
            'is_express' => true,
        ]);
    }

    private function findExistingAutomobiliste(string $email, ?string $phone): ?User
    {
        $match = User::where('email', $email)->first()
            ?? ($phone !== null ? User::where('phone', $phone)->first() : null);

        if ($match === null) {
            return null;
        }

        if ($match->role !== AccountType::Automobiliste) {
            throw ValidationException::withMessages([
                'email' => ['Cet email ou ce numéro est associé à un compte professionnel ; impossible de créer un compte client express avec ces informations.'],
            ]);
        }

        return $match;
    }
}
