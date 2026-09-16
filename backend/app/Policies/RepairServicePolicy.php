<?php

namespace App\Policies;

use App\Enums\AccountType;
use App\Enums\RepairServiceStatus;
use App\Models\RepairService;
use App\Models\User;

class RepairServicePolicy
{
    /**
     * List every service (any status, any garage) — admin only (dashboard
     * Admin, CLAUDE.md §5 règle 8).
     */
    public function viewAny(User $user): bool
    {
        return $user->role === AccountType::Admin;
    }

    /**
     * View a single service — admin, or the garagiste that owns it.
     */
    public function view(User $user, RepairService $repairService): bool
    {
        return $user->role === AccountType::Admin || $user->id === $repairService->garage->user_id;
    }

    /**
     * Approve or reject a service — admin only, and only while pending.
     */
    public function review(User $user, RepairService $repairService): bool
    {
        return $user->role === AccountType::Admin
            && $repairService->status === RepairServiceStatus::Pending;
    }
}
