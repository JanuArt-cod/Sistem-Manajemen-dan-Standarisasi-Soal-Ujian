<?php

namespace App\Policies;

use App\Models\DocumentFile;
use App\Models\User;

class DocumentFilePolicy
{
    public function download(User $user, DocumentFile $documentFile): bool
    {
        return $user->is_active && $documentFile->examDocument !== null;
    }
}