<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\StockInTransaction;
use App\Models\User;

class StockInPolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::OWNER,
            UserRole::ADMIN,
            UserRole::ADMIN_GUDANG,
        ], true);
    }

    public function view(User $user, StockInTransaction $stockInTransaction): bool
    {
        return $user->is_active;
    }
}
