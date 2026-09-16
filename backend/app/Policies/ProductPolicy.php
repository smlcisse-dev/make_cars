<?php

namespace App\Policies;

use App\Enums\AccountType;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * List every product (any status, any vendor) — admin only (dashboard
     * Admin, CLAUDE.md §5 règle 8).
     */
    public function viewAny(User $user): bool
    {
        return $user->role === AccountType::Admin;
    }

    /**
     * View a single product — admin, or the garagiste/Market Space account
     * that owns it.
     */
    public function view(User $user, Product $product): bool
    {
        return $user->role === AccountType::Admin || $user->id === $product->ownerUserId();
    }

    /**
     * Approve or reject a product — admin only, and only while pending.
     */
    public function review(User $user, Product $product): bool
    {
        return $user->role === AccountType::Admin
            && $product->status === ProductStatus::Pending;
    }
}
