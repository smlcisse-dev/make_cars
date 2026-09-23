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
     * Approve or reject a registration — admin only. The "only while pending"
     * rule is a business-state conflict (409 `invalid_status`), enforced by
     * ProfessionalRegistrationService rather than as an authorization denial.
     */
    public function review(User $user, ProfessionalRegistration $professionalRegistration): bool
    {
        return $user->role === AccountType::Admin;
    }

    /**
     * Suspend an already-approved, not-yet-suspended account — admin only.
     */
    public function suspend(User $user, ProfessionalRegistration $professionalRegistration): bool
    {
        return $user->role === AccountType::Admin
            && $professionalRegistration->status === RegistrationStatus::Approved
            && ! $professionalRegistration->isSuspended();
    }

    /**
     * Reactivate a currently-suspended account — admin only.
     */
    public function reactivate(User $user, ProfessionalRegistration $professionalRegistration): bool
    {
        return $user->role === AccountType::Admin && $professionalRegistration->isSuspended();
    }
}
