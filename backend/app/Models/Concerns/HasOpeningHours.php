<?php

namespace App\Models\Concerns;

use App\Enums\DayOfWeek;
use Illuminate\Support\Carbon;

/**
 * Calcule le statut "ouvert maintenant" à partir des horaires déjà
 * enregistrés (GarageOpeningHour/MarketSpaceOpeningHour), comparés à l'heure
 * actuelle dans le fuseau horaire métier — CLAUDE.md §5, ajout v0.13.
 * Mutualisé entre Garage et MarketSpaceAccount : même forme de données
 * (day_of_week/is_closed/opens_at/closes_at) dans les deux cas.
 */
trait HasOpeningHours
{
    /**
     * null = aucun horaire enregistré pour le jour courant (statut inconnu,
     * distinct de "fermé" : le professionnel n'a simplement pas encore
     * renseigné ses horaires — CLAUDE.md §5, ajout v0.7 pour l'obligation de
     * fournir les 7 jours dès qu'un horaire est enregistré).
     */
    public function isOpenNow(): ?bool
    {
        $now = Carbon::now(config('geo.business_timezone'));
        $today = DayOfWeek::from($now->isoWeekday());

        $hour = $this->openingHours->first(fn ($openingHour) => $openingHour->day_of_week === $today);

        if ($hour === null) {
            return null;
        }

        if ($hour->is_closed || $hour->opens_at === null || $hour->closes_at === null) {
            return false;
        }

        $currentMinutes = $now->hour * 60 + $now->minute;

        return $currentMinutes >= self::minutesFromTimeString($hour->opens_at)
            && $currentMinutes <= self::minutesFromTimeString($hour->closes_at);
    }

    /**
     * Tolère "HH:MM" et "HH:MM:SS" (le driver de base de données peut
     * renvoyer l'un ou l'autre pour une colonne "time" selon le moteur).
     */
    private static function minutesFromTimeString(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * 60 + $minutes;
    }
}
