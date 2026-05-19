<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\TransferTransaction;
use App\Models\User;

class TransferPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::OWNER,
            UserRole::ADMIN,
            UserRole::ADMIN_GUDANG,
        ], true);
    }

    public function view(User $user, TransferTransaction $transferTransaction): bool
    {
        return $user->is_active;
    }
}
