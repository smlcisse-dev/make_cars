<?php

namespace Database\Factories\Concerns;

use App\Models\Arrondissement;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;
use App\Models\ProfessionalRegistration;

/**
 * État `complete()` commun aux factories Garage/MarketSpaceAccount : profil
 * satisfaisant toutes les exigences de complétude (CLAUDE.md §5, ajout
 * v0.20) — localisation, quartier, 7 jours d'horaires, au moins une photo.
 * À utiliser dans tout test qui appelle une route de l'espace pro autre que
 * la page de profil, désormais bloquée tant que le profil est incomplet.
 * Ajoute aussi un dossier approuvé si le compte n'en a pas encore.
 */
trait CompletesProfile
{
    public function complete(): static
    {
        $arrondissement = Arrondissement::query()->with('commune')->orderBy('id')->firstOrFail();

        return $this
            ->state(fn () => [
                'department_id' => $arrondissement->commune->department_id,
                'commune_id' => $arrondissement->commune_id,
                'arrondissement_id' => $arrondissement->id,
                'neighborhood' => 'Quartier test',
            ])
            ->afterCreating(function (Garage|MarketSpaceAccount $profile) {
                $profile->openingHours()->createMany(
                    array_map(fn (int $day) => [
                        'day_of_week' => $day,
                        'is_closed' => false,
                        'opens_at' => '08:00',
                        'closes_at' => '18:00',
                    ], range(1, 7)),
                );
                $profile->images()->create(['disk' => 'public', 'path' => 'test/photo.jpg', 'position' => 1]);

                // Les routes métier exigent aussi un dossier approuvé
                // (CLAUDE.md §5, ajout v0.26) : à défaut de dossier déjà créé
                // par le test, un dossier approuvé est ajouté.
                if ($profile->user->professionalRegistration()->doesntExist()) {
                    ProfessionalRegistration::factory()->approved()->for($profile->user)->create();
                }
            });
    }

    /**
     * Dossier approuvé pour le compte du profil, sans compléter le profil :
     * pour tester le blocage `profile_incomplete`, qui ne concerne qu'un
     * compte déjà approuvé (CLAUDE.md §5, ajouts v0.20 et v0.26).
     */
    public function withApprovedRegistration(): static
    {
        return $this->afterCreating(function (Garage|MarketSpaceAccount $profile) {
            if ($profile->user->professionalRegistration()->doesntExist()) {
                ProfessionalRegistration::factory()->approved()->for($profile->user)->create();
            }
        });
    }
}
