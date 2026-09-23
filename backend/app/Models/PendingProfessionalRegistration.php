<?php

namespace App\Models;

use App\Enums\AccountType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Demande d'inscription professionnelle en attente de vérification de
 * l'email (CLAUDE.md §5, ajout v0.26). N'est jamais un compte : le `User`
 * n'est créé qu'une fois le code saisi correctement, puis la demande est
 * supprimée. `password` est déjà haché, `code_hash` est le code haché.
 */
#[Fillable(['uuid', 'account_type', 'first_name', 'last_name', 'email', 'phone', 'password', 'code_hash', 'code_expires_at', 'attempts_left', 'last_code_sent_at'])]
#[Hidden(['password', 'code_hash'])]
class PendingProfessionalRegistration extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'account_type' => AccountType::class,
            'code_expires_at' => 'datetime',
            'last_code_sent_at' => 'datetime',
            'attempts_left' => 'integer',
        ];
    }

    /**
     * Demandes plus anciennes que la durée de vie configurée
     * (`registration.pending_lifetime_hours`) : purgées au fil de l'eau.
     *
     * @param  Builder<PendingProfessionalRegistration>  $query
     */
    public function scopeStale(Builder $query): void
    {
        $query->where('created_at', '<', now()->subHours(config('registration.pending_lifetime_hours')));
    }

    public function isStale(): bool
    {
        return $this->created_at->lt(now()->subHours(config('registration.pending_lifetime_hours')));
    }

    public function resendAvailableAt(): CarbonInterface
    {
        return $this->last_code_sent_at->copy()->addSeconds(config('registration.resend_cooldown_seconds'));
    }
}
