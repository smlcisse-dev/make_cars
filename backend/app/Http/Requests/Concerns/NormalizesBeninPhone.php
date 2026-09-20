<?php

namespace App\Http\Requests\Concerns;

use App\Rules\BeninPhoneNumber;

/**
 * Remplace la saisie du champ `phone` par sa forme canonique (+229 puis 10
 * chiffres) avant validation, pour que la valeur stockée — et l'unicité
 * `users.phone` — ne dépende jamais des espaces/tirets tapés (CLAUDE.md §5,
 * ajout v0.21). Une saisie invalide est laissée telle quelle : c'est la règle
 * BeninPhoneNumber qui la rejette.
 */
trait NormalizesBeninPhone
{
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (is_string($phone) && ($normalized = BeninPhoneNumber::normalize($phone)) !== null) {
            $this->merge(['phone' => $normalized]);
        }
    }
}
