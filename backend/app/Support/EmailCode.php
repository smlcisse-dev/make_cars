<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Hash;

/**
 * Code à usage unique envoyé par email, commun à l'inscription
 * professionnelle (CLAUDE.md §5, ajout v0.26) et au mot de passe oublié
 * (ajout v0.29) : génération et attributs d'un code neuf, selon
 * config/email_codes.php.
 */
final class EmailCode
{
    /**
     * `random_int` (générateur cryptographique) puis complétion à gauche :
     * un code comme « 004217 » garde ses zéros en tête.
     */
    public static function generate(): string
    {
        $length = config('email_codes.length');

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Attributs d'un code tout juste envoyé : haché, validité et essais
     * remis à neuf.
     *
     * @return array{code_hash: string, code_expires_at: CarbonInterface, attempts_left: int, last_code_sent_at: CarbonInterface}
     */
    public static function freshAttributes(string $code): array
    {
        return [
            'code_hash' => Hash::make($code),
            'code_expires_at' => now()->addMinutes(config('email_codes.ttl_minutes')),
            'attempts_left' => config('email_codes.max_attempts'),
            'last_code_sent_at' => now(),
        ];
    }

    public static function ttlMinutes(): int
    {
        return config('email_codes.ttl_minutes');
    }

    /**
     * Règle de validation d'un code bien formé (seuls les chiffres, à la
     * bonne longueur) : un code mal formé est refusé sans consommer d'essai.
     */
    public static function formatRule(): string
    {
        return 'regex:/^\d{'.config('email_codes.length').'}$/';
    }

    public static function formatMessage(): string
    {
        return 'Le code doit comporter '.config('email_codes.length').' chiffres.';
    }

    public static function resendAvailableAt(CarbonInterface $lastSentAt): CarbonInterface
    {
        return $lastSentAt->copy()->addSeconds(config('email_codes.resend_cooldown_seconds'));
    }
}
