<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\ExamPeriodStatus;
use App\Enums\ProcessingRunStatus;
use App\Jobs\ProcessExamDocumentJob;
use App\Models\DocumentProcessingRun;
use App\Models\ExamDocument;
use App\Services\FormattingSnapshotBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DocumentProcessingController extends Controller
{
    public function store(
        Request $request,
        ExamDocument $examDocument,
        FormattingSnapshotBuilder $snapshotBuilder
    ): RedirectResponse {
        Gate::authorize('update', $examDocument);

        $examDocument->loadMissing(['examPeriod', 'sourceFile']);

        if ($examDocument->examPeriod?->status === ExamPeriodStatus::ARCHIVED) {
            return redirect()
                ->route('exam-documents.show', $examDocument->id)
                ->with('error', 'Dokumen pada periode ujian yang sudah diarsipkan tidak dapat diproses ulang.');
        }

        if ($examDocument->sourceFile === null) {
            return redirect()
                ->route('exam-documents.show', $examDocument->id)
                ->with('error', 'Dokumen ini belum memiliki file sumber asli (.docx) yang valid.');
        }

        if (in_array($examDocument->current_status, [DocumentStatus::QUEUED, DocumentStatus::PROCESSING], true)) {
            return redirect()
                ->route('exam-documents.show', $examDocument->id)
                ->with('warning', 'Dokumen ini sedang dalam antrean atau sedang diproses.');
        }

        $run = DB::transaction(function () use ($examDocument, $request, $snapshotBuilder) {
            $config = $snapshotBuilder->ensureForDocument($examDocument, $request->user());

            $createdRun = DocumentProcessingRun::query()->create([
                'exam_document_id' => $examDocument->id,
                'document_processing_config_id' => $config->id,
                'triggered_by' => $request->user()?->id,
                'target_type' => 'DOCX',
                'status' => ProcessingRunStatus::QUEUED,
                'input_checksum_sha256' => $examDocument->sourceFile->checksum_sha256,
            ]);

            $examDocument->update([
                'current_status' => DocumentStatus::QUEUED,
            ]);

            return $createdRun;
        });

        ProcessExamDocumentJob::dispatch($run->id);

        return redirect()
            ->route('exam-documents.show', $examDocument->id)
            ->with('success', 'Standarisasi dokumen DOCX telah dijalankan.');
    }
}