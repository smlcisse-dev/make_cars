<?php

namespace App\Models;

use Database\Factories\GarageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['name', 'description', 'address', 'latitude', 'longitude', 'phone'])]
class Garage extends Model
{
    /** @use HasFactory<GarageFactory> */
    use HasFactory;

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
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
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
     * Avis laissés après une prestation facturée (CLAUDE.md §5, règle 7 et
     * ajout v0.10).
     *
     * @return MorphMany<Review, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }
}
