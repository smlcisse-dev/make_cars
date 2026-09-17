<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AccountType;
use App\Enums\RegistrationStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'role', 'password', 'google_id', 'is_express'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => AccountType::class,
            'is_express' => 'boolean',
        ];
    }

    /**
     * @return HasOne<ProfessionalRegistration, $this>
     */
    public function professionalRegistration(): HasOne
    {
        return $this->hasOne(ProfessionalRegistration::class);
    }

    /**
     * @return HasOne<Garage, $this>
     */
    public function garage(): HasOne
    {
        return $this->hasOne(Garage::class);
    }

    /**
     * @return HasOne<MarketSpaceAccount, $this>
     */
    public function marketSpaceAccount(): HasOne
    {
        return $this->hasOne(MarketSpaceAccount::class);
    }

    /**
     * RDV demandés par cet automobiliste (CLAUDE.md §5, ajout v0.7).
     *
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Conversations avec des garages (CLAUDE.md §5, ajout v0.8).
     *
     * @return HasMany<Conversation, $this>
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function isValidatedProfessional(): bool
    {
        if (! $this->role->requiresProfessionalValidation()) {
            return false;
        }

        $registration = $this->professionalRegistration;

        return $registration?->status === RegistrationStatus::Approved && ! $registration->isSuspended();
    }

    /**
     * Commandes passées par cet automobiliste (CLAUDE.md §5, ajout v0.9).
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Devis reçus par cet automobiliste, avec ou sans RDV associé
     * (CLAUDE.md §5, ajout v0.9).
     *
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Avis laissés par cet automobiliste, uniquement après une transaction
     * terminée (CLAUDE.md §5, règle 7 et ajout v0.10).
     *
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Réclamations déposées par cet automobiliste, uniquement sur une
     * transaction terminée (CLAUDE.md §5, ajout v0.11).
     *
     * @return HasMany<Dispute, $this>
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }
}
