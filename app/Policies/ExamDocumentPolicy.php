<?php

namespace App\Policies;

use App\Enums\DocumentStatus;
use App\Enums\ExamPeriodStatus;
use App\Models\ExamDocument;
use App\Models\User;

class ExamDocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, ExamDocument$examDocument): bool
    {
        return $user->is_active;
    }

    public function create(User $user): bool
    {
        return $user->is_active;
    }

    public function update(User $user, ExamDocument$examDocument): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($examDocument->current_status === DocumentStatus::ARCHIVED && !$user->isAdmin()) {
            return false;
        }

        return true;
    }

    public function delete(User $user, ExamDocument$examDocument): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if (in_array($examDocument->current_status, [DocumentStatus::QUEUED, DocumentStatus::PROCESSING], true)) {
            return false;
        }

        $isArchived =$examDocument->current_status === DocumentStatus::ARCHIVED
            || $examDocument->examPeriod?->status === ExamPeriodStatus::ARCHIVED;

        if ($isArchived && !$user->isAdmin()) {
            return false;
        }

        return true;
    }
}