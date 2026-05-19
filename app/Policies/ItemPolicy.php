<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Item;
use App\Models\User;

class ItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Item $item): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, [
            UserRole::OWNER,
            UserRole::ADMIN,
            UserRole::ADMIN_GUDANG,
        ], true);
    }

    public function update(User $user, Item $item): bool
    {
        return in_array($user->role, [
            UserRole::OWNER,
            UserRole::ADMIN,
        ], true);
    }

    public function delete(User $user, Item $item): bool
    {
        return in_array($user->role, [
            UserRole::OWNER,
            UserRole::ADMIN,
        ], true);
    }
}
