<?php

namespace App\Http\Controllers;

use App\Models\DocumentFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentFileController extends Controller
{
    public function download(DocumentFile $documentFile): StreamedResponse
    {
        Gate::authorize('download', $documentFile);

        $disk = Storage::disk($documentFile->storage_disk);

        if (! $disk->exists($documentFile->storage_path)) {
            abort(404, 'Berkas fisik tidak ditemukan di penyimpanan privat.');
        }

        return $disk->download(
            $documentFile->storage_path,
            $documentFile->original_filename,
            [
                'Content-Type' => $documentFile->mime_type,
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}