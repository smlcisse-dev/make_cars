<?php

namespace App\Models;

use App\Enums\RegistrationStatus;
use App\Models\Concerns\HasAdministrativeLocation;
use App\Models\Concerns\HasOpeningHours;
use App\Models\Concerns\HasProfileCompleteness;
use Database\Factories\GarageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['name', 'description', 'address', 'department_id', 'commune_id', 'arrondissement_id', 'neighborhood', 'latitude', 'longitude', 'phone'])]
class Garage extends Model
{
    /** @use HasFactory<GarageFactory> */
    use HasAdministrativeLocation, HasFactory, HasOpeningHours, HasProfileCompleteness;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
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
     * @return HasMany<GarageOpeningHour, $this>
     */
    public function openingHours(): HasMany
    {
        return $this->hasMany(GarageOpeningHour::class);
    }

    /**
     * @return HasMany<GarageImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(GarageImage::class)->orderBy('position');
    }

    /**
     * Mini-boutique du garage : un seul stock, partagé entre pièces d'atelier
     * et consommables courants (CLAUDE.md §5, ajout v0.4).
     *
     * @return MorphMany<Product, $this>
     */
    public function products(): MorphMany
    {
        return $this->morphMany(Product::class, 'sellable');
    }

    /**
     * @return HasMany<RepairService, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(RepairService::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Devis du garage, avec ou sans RDV associé (CLAUDE.md §5, ajout v0.9).
     *
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * @return MorphMany<Conversation, $this>
     */
    public function conversations(): MorphMany
    {
        return $this->morphMany(Conversation::class, 'sellable');
    }

    /**
     * Commandes isolées de la mini-boutique, sans prestation associée
     * (CLAUDE.md §5, ajout v0.9).
     *
     * @return MorphMany<Order, $this>
     */
    public function orders(): MorphMany
    {
        return $this->morphMany(Order::class, 'sellable');
    }

    /**
     * Un garage n'est visible publiquement (app mobile) que si son compte
     * Garagiste a été validé par un administrateur (CLAUDE.md §5, règle 4).
     */
    public function isPubliclyVisible(): bool
    {
        return $this->user->isValidatedProfessional();
    }

    /**
     * Structures au dossier approuvé, suspendues comprises : périmètre de la
     * supervision admin et des statistiques destinées aux autorités. Depuis
     * v0.26 un profil existe dès la vérification de l'email ; les dossiers
     * en cours se consultent via /admin/registrations (CLAUDE.md §5, ajout
     * v0.26).
     *
     * @param  Builder<Garage>  $query
     * @return Builder<Garage>
     */
    public function scopeWithApprovedRegistration(Builder $query): Builder
    {
        return $query->whereHas(
            'user.professionalRegistration',
            fn ($q) => $q->where('status', RegistrationStatus::Approved)
        );
    }

    /**
     * Vrai si le dossier du compte est approuvé (suspendu ou non) — même
     * périmètre que scopeWithApprovedRegistration().
     */
    public function hasApprovedRegistration(): bool
    {
        return $this->user?->professionalRegistration?->status === RegistrationStatus::Approved;
    }

    /**
     * Même filtre qu'isPubliclyVisible(), utilisable directement dans une
     * requête de liste (recherche géolocalisée notamment — CLAUDE.md §5,
     * ajout v0.13) sans charger chaque garage un par un.
     *
     * @param  Builder<Garage>  $query
     * @return Builder<Garage>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->whereHas(
            'user.professionalRegistration',
            fn ($q) => $q->where('status', RegistrationStatus::Approved)->whereNull('suspended_at')
        );
    }

    /**
     * Avis laissés après une prestation facturée (CLAUDE.md §5, règle 7 et
     * ajout v0.10).
     *
     * @return MorphMany<Review, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * Réclamations visant ce garage, rattachées à une transaction terminée
     * (CLAUDE.md §5, ajout v0.11).
     *
     * @return MorphMany<Dispute, $this>
     */
    public function disputes(): MorphMany
    {
        return $this->morphMany(Dispute::class, 'respondent');
    }
}
