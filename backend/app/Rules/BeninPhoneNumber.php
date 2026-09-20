<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Numéro de téléphone béninois en numérotation à 10 chiffres (en vigueur
 * depuis 2024) : indicatif +229 suivi de 10 chiffres commençant par 01
 * (CLAUDE.md §5, ajout v0.21). Les espaces, tirets, points et parenthèses de
 * la saisie sont tolérés ; `00229` est accepté à la place de `+229`. La forme
 * canonique stockée en base est `+229` immédiatement suivi des 10 chiffres.
 */
class BeninPhoneNumber implements ValidationRule
{
    public const MESSAGE = 'Le numéro de téléphone doit être au format béninois : +229 suivi de 10 chiffres commençant par 01 (ex. +229 01 23 45 67 89).';

    /**
     * Forme canonique (`+2290123456789`), ou null si la saisie n'a pas la
     * structure attendue.
     */
    public static function normalize(string $value): ?string
    {
        $compact = preg_replace('/[\s\-.()]/', '', $value);

        if (str_starts_with($compact, '00229')) {
            $compact = '+229'.substr($compact, 5);
        }

        return preg_match('/^\+229(01\d{8})$/', $compact, $matches) === 1
            ? '+229'.$matches[1]
            : null;
    }

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || self::normalize($value) === null) {
            $fail(self::MESSAGE);
        }
    }
}
