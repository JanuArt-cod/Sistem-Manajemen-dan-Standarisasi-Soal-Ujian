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

    public function preview(DocumentFile $documentFile): StreamedResponse
    {
        Gate::authorize('download', $documentFile);

        if ($documentFile->mime_type !== 'application/pdf') {
            abort(422, 'Pratinjau inline hanya tersedia untuk berkas PDF.');
        }

        $disk = Storage::disk($documentFile->storage_disk);

        if (! $disk->exists($documentFile->storage_path)) {
            abort(404, 'Berkas PDF tidak ditemukan di penyimpanan privat.');
        }

        $safeFilename = str_replace('"', '', $documentFile->original_filename);

        return $disk->response(
            $documentFile->storage_path,
            $documentFile->original_filename,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $safeFilename . '"',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}