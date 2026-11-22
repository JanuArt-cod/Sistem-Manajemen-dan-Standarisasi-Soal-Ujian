<?php

namespace App\Policies;

use App\Models\DocumentTemplate;
use App\Models\User;

class DocumentTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function download(User $user, DocumentTemplate $template): bool
    {
        return $user->is_active && $template->hasCustomDocxFile();
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function update(User $user, DocumentTemplate $template): bool
    {
        return $user->is_active && $user->isAdmin();
    }
}