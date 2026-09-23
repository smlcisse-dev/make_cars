<?php

namespace App\Models;

use App\Enums\ReactivationRequestStatus;
use App\Enums\RegistrationDocumentType;
use App\Enums\RegistrationStatus;
use Database\Factories\ProfessionalRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['business_registration_number', 'ifu', 'npi', 'status', 'submitted_at', 'rejection_reason', 'reviewed_by', 'reviewed_at', 'suspension_reason', 'suspended_by', 'suspended_at'])]
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
            'submitted_at' => 'datetime',
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

    /**
     * Document du registre de commerce actuel : un seul à la fois, chaque
     * nouvel envoi remplace le précédent (CLAUDE.md §5, ajout v0.26).
     *
     * @return HasOne<RegistrationDocument, $this>
     */
    public function businessRegistrationDocument(): HasOne
    {
        return $this->latestDocumentOfType(RegistrationDocumentType::BusinessRegistration);
    }

    /**
     * Certificat d'Identification Personnelle (CIP) actuel, qui permet à
     * l'admin de vérifier le NPI : un seul à la fois, comme le registre de
     * commerce (CLAUDE.md §5, ajout v0.27).
     *
     * @return HasOne<RegistrationDocument, $this>
     */
    public function identityCertificateDocument(): HasOne
    {
        return $this->latestDocumentOfType(RegistrationDocumentType::IdentityCertificate);
    }

    /**
     * Document le plus récent d'un type donné.
     *
     * @return HasOne<RegistrationDocument, $this>
     */
    public function latestDocumentOfType(RegistrationDocumentType $type): HasOne
    {
        return $this->hasOne(RegistrationDocument::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->where('type', $type),
        );
    }

    /**
     * Historique des décisions admin, la plus récente en premier.
     *
     * @return HasMany<RegistrationDecision, $this>
     */
    public function decisions(): HasMany
    {
        return $this->hasMany(RegistrationDecision::class)->latest('decided_at')->latest('id');
    }

    /**
     * Demandes de réactivation (CLAUDE.md §5, ajout v0.28), la plus récente
     * en premier : l'historique complet, jamais purgé.
     *
     * @return HasMany<ReactivationRequest, $this>
     */
    public function reactivationRequests(): HasMany
    {
        return $this->hasMany(ReactivationRequest::class)->latest('id');
    }

    /**
     * Dernière demande de réactivation (bandeau de suspension, fiche admin).
     *
     * @return HasOne<ReactivationRequest, $this>
     */
    public function latestReactivationRequest(): HasOne
    {
        return $this->hasOne(ReactivationRequest::class)->latestOfMany('id');
    }

    /**
     * Demande en attente de décision : une seule à la fois.
     */
    public function pendingReactivationRequest(): ?ReactivationRequest
    {
        return $this->reactivationRequests()->where('status', ReactivationRequestStatus::Pending)->first();
    }

    /**
     * Profil public associé (Garage ou Market Space selon le rôle), créé dès
     * la vérification de l'email (CLAUDE.md §5, ajout v0.26).
     */
    public function profile(): Garage|MarketSpaceAccount|null
    {
        return $this->user?->professionalProfile();
    }

    /**
     * Informations légales privées manquantes pour pouvoir soumettre le
     * dossier : numéro RCCM et son document, IFU, NPI (CLAUDE.md §5, ajout
     * v0.26) et Certificat d'Identification Personnelle (ajout v0.27). Un
     * dossier déjà approuvé ou en cours d'examen sans CIP n'est pas bloqué :
     * cette liste n'est vérifiée qu'à la soumission.
     *
     * @return array<int, string>
     */
    public function missingLegalFields(): array
    {
        $missing = [];

        if (trim((string) $this->business_registration_number) === '') {
            $missing[] = 'business_registration_number';
        }

        if ($this->businessRegistrationDocument()->doesntExist()) {
            $missing[] = 'business_registration_document';
        }

        foreach (['ifu', 'npi'] as $field) {
            if (trim((string) $this->{$field}) === '') {
                $missing[] = $field;
            }
        }

        if ($this->identityCertificateDocument()->doesntExist()) {
            $missing[] = 'identity_certificate_document';
        }

        return $missing;
    }

    /**
     * @return array{is_complete: bool, missing_fields: array<int, string>}
     */
    public function legalStatus(): array
    {
        $missing = $this->missingLegalFields();

        return ['is_complete' => $missing === [], 'missing_fields' => $missing];
    }

    /**
     * Le professionnel peut (re)soumettre son dossier depuis ces statuts.
     */
    public function isSubmittable(): bool
    {
        return in_array($this->status, [RegistrationStatus::ProfileIncomplete, RegistrationStatus::Rejected], true);
    }
}
