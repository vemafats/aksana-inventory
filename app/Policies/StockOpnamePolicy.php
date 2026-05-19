<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\StockOpnameTransaction;
use App\Models\User;

class StockOpnamePolicy
{
    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::OWNER,
            UserRole::ADMIN,
            UserRole::ADMIN_GUDANG,
            UserRole::PIC_BAZAR,
            UserRole::SALES,
        ], true);
    }

    public function validate(User $user): bool
    {
        return in_array($user->role, [
            UserRole::OWNER,
            UserRole::ADMIN,
        ], true);
    }

    public function view(User $user, StockOpnameTransaction $stockOpnameTransaction): bool
    {
        return $user->is_active;
    }
}
