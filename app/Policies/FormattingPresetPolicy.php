<?php

namespace App\Policies;

use App\Models\FormattingPreset;
use App\Models\User;

class FormattingPresetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function update(User $user, FormattingPreset $preset): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}