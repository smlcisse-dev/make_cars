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

#[Fillable(['name', 'first_name', 'last_name', 'email', 'phone', 'role', 'password', 'google_id', 'is_express'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * `name` reste le nom d'affichage lu partout (PDF, chat, ressources,
     * emails) : plutôt que de faire évoluer chacun de ces usages, il est
     * recomposé en « Prénom Nom » dès que les deux champs séparés sont
     * renseignés (CLAUDE.md §5, ajout v0.26). Un compte sans prénom/nom
     * (automobiliste, compte express, Google, comptes antérieurs) garde son
     * `name` tel quel.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if (filled($user->first_name) && filled($user->last_name)) {
                $user->name = trim($user->first_name).' '.trim($user->last_name);
            }
        });
    }

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
     * Profil public du professionnel (Garage ou Market Space selon le rôle),
     * null pour les autres rôles. Créé dès la vérification de l'email, avant
     * même la soumission du dossier (CLAUDE.md §5, ajout v0.26).
     */
    public function professionalProfile(): Garage|MarketSpaceAccount|null
    {
        return match ($this->role) {
            AccountType::Garagiste => $this->garage,
            AccountType::MarketSpace => $this->marketSpaceAccount,
            default => null,
        };
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

    /**
     * Notifications push reçues par ce compte, tous rôles confondus
     * (CLAUDE.md §5, ajout v0.12). Distinct de notifications() (trait
     * Notifiable) qui reste inutilisé côté notifications intégrées Laravel.
     *
     * @return HasMany<PushNotification, $this>
     */
    public function pushNotifications(): HasMany
    {
        return $this->hasMany(PushNotification::class);
    }

    /**
     * Jetons FCM des appareils de ce compte — un utilisateur peut en avoir
     * plusieurs (CLAUDE.md §5, ajout v0.12).
     *
     * @return HasMany<DeviceToken, $this>
     */
    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }
}
