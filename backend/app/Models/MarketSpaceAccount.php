<?php

namespace App\Models;

use App\Enums\City;
use App\Enums\Region;
use App\Enums\RegistrationStatus;
use App\Models\Concerns\HasOpeningHours;
use Database\Factories\MarketSpaceAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['name', 'description', 'address', 'city', 'region', 'latitude', 'longitude', 'phone'])]
class MarketSpaceAccount extends Model
{
    /** @use HasFactory<MarketSpaceAccountFactory> */
    use HasFactory, HasOpeningHours;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'city' => City::class,
            'region' => Region::class,
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
     * @return HasMany<MarketSpaceOpeningHour, $this>
     */
    public function openingHours(): HasMany
    {
        return $this->hasMany(MarketSpaceOpeningHour::class);
    }

    /**
     * @return HasMany<MarketSpaceImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(MarketSpaceImage::class)->orderBy('position');
    }

    /**
     * Le Market Space garde son propre stock/catalogue, distinct de celui
     * d'un garage même si la même structure détient les deux comptes
     * (CLAUDE.md §5, ajout v0.4).
     *
     * @return MorphMany<Product, $this>
     */
    public function products(): MorphMany
    {
        return $this->morphMany(Product::class, 'sellable');
    }

    /**
     * Un compte Market Space n'est visible publiquement (app mobile) que si
     * son inscription a été validée par un administrateur (CLAUDE.md §5, règle 4).
     */
    public function isPubliclyVisible(): bool
    {
        return $this->user->isValidatedProfessional();
    }

    /**
     * Même filtre qu'isPubliclyVisible(), utilisable directement dans une
     * requête de liste (recherche géolocalisée notamment — CLAUDE.md §5,
     * ajout v0.13) sans charger chaque boutique une par une.
     *
     * @param  Builder<MarketSpaceAccount>  $query
     * @return Builder<MarketSpaceAccount>
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->whereHas(
            'user.professionalRegistration',
            fn ($q) => $q->where('status', RegistrationStatus::Approved)->whereNull('suspended_at')
        );
    }

    /**
     * Commandes de ce Market Space, stock totalement distinct de celui d'un
     * garage (CLAUDE.md §5, ajout v0.9).
     *
     * @return MorphMany<Order, $this>
     */
    public function orders(): MorphMany
    {
        return $this->morphMany(Order::class, 'sellable');
    }

    /**
     * Avis laissés après un achat confirmé (CLAUDE.md §5, règle 7 et ajout
     * v0.10).
     *
     * @return MorphMany<Review, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * Réclamations visant ce Market Space, rattachées à une transaction
     * terminée (CLAUDE.md §5, ajout v0.11).
     *
     * @return MorphMany<Dispute, $this>
     */
    public function disputes(): MorphMany
    {
        return $this->morphMany(Dispute::class, 'respondent');
    }
}
