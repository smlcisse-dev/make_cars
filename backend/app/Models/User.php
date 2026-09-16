<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\AccountType;
use App\Enums\RegistrationStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'phone', 'role', 'password'])]
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

    public function isValidatedProfessional(): bool
    {
        if (! $this->role->requiresProfessionalValidation()) {
            return false;
        }

        return $this->professionalRegistration?->status === RegistrationStatus::Approved;
    }
}
