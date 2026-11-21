<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User$target): bool
    {
        return $user->isAdmin();
    }

    public function toggleActive(User $user, User$target): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        return $user->id !==$target->id;
    }
}