<?php

namespace App\Models\Concerns;

/**
 * Complétude du profil public d'un professionnel (Garage ou Market Space) :
 * tant qu'il manque un élément, l'espace pro est bloqué sauf la page de
 * profil (CLAUDE.md §5, ajout v0.20). Mutualisé entre Garage et
 * MarketSpaceAccount, qui partagent les mêmes champs et relations
 * (`openingHours`, `images`).
 */
trait HasProfileCompleteness
{
    public const REQUIRED_OPENING_HOURS_DAYS = 7;

    /**
     * Clés des éléments manquants : champs du profil par leur nom de colonne,
     * plus `opening_hours` (les 7 jours enregistrés) et `images` (au moins
     * une photo).
     *
     * @return array<int, string>
     */
    public function missingProfileFields(): array
    {
        $missing = [];

        foreach (['name', 'address', 'phone', 'neighborhood'] as $field) {
            if (trim((string) $this->{$field}) === '') {
                $missing[] = $field;
            }
        }

        foreach (['latitude', 'longitude', 'department_id', 'commune_id', 'arrondissement_id'] as $field) {
            if ($this->{$field} === null) {
                $missing[] = $field;
            }
        }

        if ($this->openingHours()->count() < self::REQUIRED_OPENING_HOURS_DAYS) {
            $missing[] = 'opening_hours';
        }

        if ($this->images()->doesntExist()) {
            $missing[] = 'images';
        }

        return $missing;
    }

    public function isProfileComplete(): bool
    {
        return $this->missingProfileFields() === [];
    }

    /**
     * @return array{is_complete: bool, missing_fields: array<int, string>}
     */
    public function profileStatus(): array
    {
        $missing = $this->missingProfileFields();

        return ['is_complete' => $missing === [], 'missing_fields' => $missing];
    }
}
