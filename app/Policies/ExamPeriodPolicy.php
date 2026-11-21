<?php

namespace App\Policies;

use App\Enums\ExamPeriodStatus;
use App\Models\ExamPeriod;
use App\Models\User;

class ExamPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, ExamPeriod $examPeriod): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($examPeriod->isArchived() && ! $user->isAdmin()) {
            return false;
        }

        return true;
    }

    public function archive(User $user, ExamPeriod $examPeriod): bool
    {
        return $user->is_active && $examPeriod->status !== ExamPeriodStatus::ARCHIVED;
    }

    public function reopen(User $user, ExamPeriod $examPeriod): bool
    {
        return $user->is_active && $user->isAdmin() && $examPeriod->isArchived();
    }
}