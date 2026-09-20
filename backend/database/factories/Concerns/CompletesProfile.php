<?php

namespace Database\Factories\Concerns;

use App\Models\Arrondissement;
use App\Models\Garage;
use App\Models\MarketSpaceAccount;

/**
 * État `complete()` commun aux factories Garage/MarketSpaceAccount : profil
 * satisfaisant toutes les exigences de complétude (CLAUDE.md §5, ajout
 * v0.20) — localisation, quartier, 7 jours d'horaires, au moins une photo.
 * À utiliser dans tout test qui appelle une route de l'espace pro autre que
 * la page de profil, désormais bloquée tant que le profil est incomplet.
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
            });
    }
}
