<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SalesTransaction;
use App\Models\User;

class SalesTransactionPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::OWNER,
            UserRole::ADMIN,
            UserRole::PIC_BAZAR,
            UserRole::SALES,
        ], true);
    }

    public function view(User $user, SalesTransaction $salesTransaction): bool
    {
        return $user->is_active;
    }
}
