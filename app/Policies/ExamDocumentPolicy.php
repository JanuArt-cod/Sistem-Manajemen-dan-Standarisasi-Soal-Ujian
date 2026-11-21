<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Models\ExamDocument;
use App\Models\User;

class ExamDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, ExamDocument $examDocument): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, ExamDocument $examDocument): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($examDocument->current_status === DocumentStatus::ARCHIVED && ! $user->isAdmin()) {
            return false;
        }

        return true;
    }
}