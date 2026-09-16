<?php

namespace App\Policies;

use App\Enums\AccountType;
use App\Enums\RegistrationStatus;
use App\Models\ProfessionalRegistration;
use App\Models\User;

class ProfessionalRegistrationPolicy
{
    /**
     * List every registration — admin only (dashboard Admin, CLAUDE.md §5 règle 8).
     */
    public function viewAny(User $user): bool
    {
        return $user->role === AccountType::Admin;
    }

    /**
     * View a single registration — admin, or the professional it belongs to.
     */
    public function view(User $user, ProfessionalRegistration $professionalRegistration): bool
    {
        return $user->role === AccountType::Admin || $user->id === $professionalRegistration->user_id;
    }

    /**
     * Approve or reject a registration — admin only, and only while pending.
     */
    public function review(User $user, ProfessionalRegistration $professionalRegistration): bool
    {
        return $user->role === AccountType::Admin
            && $professionalRegistration->status === RegistrationStatus::Pending;
    }
}
