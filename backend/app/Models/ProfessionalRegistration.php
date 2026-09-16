<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use Database\Factories\ProfessionalRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['structure_name', 'address', 'business_registration_number', 'status', 'rejection_reason', 'reviewed_by', 'reviewed_at', 'suspension_reason', 'suspended_by', 'suspended_at'])]
class ProfessionalRegistration extends Model
{
    /** @use HasFactory<ProfessionalRegistrationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'reviewed_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return HasMany<RegistrationDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(RegistrationDocument::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    /**
     * Un compte suspendu reste "Approved" (le dossier KYC n'est pas remis en
     * cause) mais devient invisible côté mobile le temps de la suspension —
     * distinct d'un rejet, réversible via reactivate() (CLAUDE.md §5, ajout v0.6).
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }
}
