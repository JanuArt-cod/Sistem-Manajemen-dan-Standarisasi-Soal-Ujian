<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\ExamPeriodStatus;
use App\Models\ExamDocument;
use App\Services\DocumentProcessing\DocxStandardizationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentPdfExportController extends Controller
{
    public function store(
        Request $request,
        ExamDocument $examDocument,
        DocxStandardizationService $service
    ): RedirectResponse {
        Gate::authorize('update', $examDocument);

        $examDocument->loadMissing(['examPeriod', 'outputDocxFile']);

        if ($examDocument->examPeriod?->status === ExamPeriodStatus::ARCHIVED) {
            return redirect()
                ->route('exam-documents.show', $examDocument->id)
                ->with('error', 'Dokumen pada periode ujian yang sudah diarsipkan tidak dapat diekspor ulang.');
        }

        if ($examDocument->outputDocxFile === null) {
            return redirect()
                ->route('exam-documents.show', $examDocument->id)
                ->with('error', 'Hasil standarisasi DOCX belum tersedia. Jalankan proses standarisasi DOCX terlebih dahulu.');
        }

        if (in_array($examDocument->current_status, [DocumentStatus::QUEUED, DocumentStatus::PROCESSING], true)) {
            return redirect()
                ->route('exam-documents.show', $examDocument->id)
                ->with('warning', 'Dokumen ini sedang diproses.');
        }

        $run = $service->exportPdfOnly($examDocument, $request->user()?->id);

        if ($run->pdf_status === 'COMPLETED') {
            return redirect()
                ->route('exam-documents.show', $examDocument->id)
                ->with('success', 'Berkas PDF F4 berhasil diekspor dari hasil standarisasi DOCX.');
        }

        return redirect()
            ->route('exam-documents.show', $examDocument->id)
            ->with('warning', $run->pdf_error_message ?: 'Ekspor PDF belum berhasil, namun hasil DOCX tetap tersedia.');
    }
}