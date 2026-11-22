<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Enums\ExamPeriodStatus;
use App\Enums\WarningSeverity;
use App\Http\Requests\Documents\ResolveDocumentWarningRequest;
use App\Http\Requests\Documents\SubmitDocumentReviewRequest;
use App\Models\DocumentWarning;
use App\Models\ExamDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class DocumentReviewController extends Controller
{
    public function resolveWarning(
        ResolveDocumentWarningRequest $request,
        ExamDocument $examDocument,
        DocumentWarning $documentWarning
    ): RedirectResponse {
        Gate::authorize('update', $examDocument);

        if ($documentWarning->exam_document_id !== $examDocument->id) {
            abort(404);
        }

        $examDocument->loadMissing('examPeriod');

        if ($examDocument->examPeriod?->status === ExamPeriodStatus::ARCHIVED) {
            return redirect()
                ->route('exam-documents.show', $examDocument->id)
                ->with('error', 'Dokumen pada periode ujian yang sudah diarsipkan tidak dapat diubah.');
        }

        $note = $request->validated('resolution_note');

        $documentWarning->update([
            'is_resolved' => true,
            'resolved_by' => $request->user()?->id,
            'resolved_at' => now(),
            'resolution_note' => filled($note) ? trim((string) $note) : null,
        ]);

        return redirect()
            ->route('exam-documents.show', $examDocument->id)
            ->with('success', 'Catatan peringatan telah ditandai selesai diperiksa.');
    }

    public function store(
        SubmitDocumentReviewRequest $request,
        ExamDocument $examDocument
    ): RedirectResponse {
        Gate::authorize('update', $examDocument);

        $examDocument->loadMissing(['examPeriod', 'outputDocxFile', 'latestProcessingRun.warnings']);

        if ($examDocument->examPeriod?->status === ExamPeriodStatus::ARCHIVED) {
            return redirect()
                ->route('exam-documents.show', $examDocument->id)
                ->with('error', 'Dokumen pada periode ujian yang sudah diarsipkan tidak dapat direview.');
        }

        if ($examDocument->outputDocxFile === null || in_array($examDocument->current_status, [
            DocumentStatus::UPLOADED,
            DocumentStatus::VALIDATION_FAILED,
            DocumentStatus::QUEUED,
            DocumentStatus::PROCESSING,
            DocumentStatus::FAILED,
        ], true)) {
            return redirect()
                ->route('exam-documents.show', $examDocument->id)
                ->with('error', 'Dokumen harus selesai distandarisasi terlebih dahulu sebelum disetujui atau diminta revisi.');
        }

        $decision = (string) $request->validated('decision');
        $rawNotes = $request->validated('review_notes');
        $reviewNotes = filled($rawNotes) ? trim((string) $rawNotes) : null;
        $actorId = $request->user()?->id;

        if ($decision === DocumentStatus::APPROVED->value) {
            $latestRun = $examDocument->latestProcessingRun;
            $unresolvedCriticalCount = $latestRun
                ? $latestRun->warnings
                    ->where('severity', WarningSeverity::REVIEW_REQUIRED)
                    ->where('is_resolved', false)
                    ->count()
                : 0;

            if ($unresolvedCriticalCount > 0) {
                return redirect()
                    ->route('exam-documents.show', $examDocument->id)
                    ->with('error', 'Selesaikan seluruh peringatan dengan tingkat Perlu Tinjauan terlebih dahulu sebelum menyetujui dokumen.');
            }

            $examDocument->update([
                'current_status' => DocumentStatus::APPROVED,
                'approved_by' => $actorId,
                'approved_at' => now(),
                'reviewed_by' => $actorId,
                'reviewed_at' => now(),
                'review_notes' => $reviewNotes,
            ]);

            return redirect()
                ->route('exam-documents.show', $examDocument->id)
                ->with('success', 'Dokumen soal resmi disetujui (APPROVED) dan siap dicetak/diarsipkan.');
        }

        $examDocument->update([
            'current_status' => DocumentStatus::REVISION_REQUIRED,
            'approved_by' => null,
            'approved_at' => null,
            'reviewed_by' => $actorId,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);

        return redirect()
            ->route('exam-documents.show', $examDocument->id)
            ->with('warning', 'Dokumen ditandai Perlu Revisi (REVISION_REQUIRED) beserta catatan pemeriksaan.');
    }
}