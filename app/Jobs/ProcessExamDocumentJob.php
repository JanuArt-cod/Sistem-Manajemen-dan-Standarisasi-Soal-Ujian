<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Enums\ProcessingRunStatus;
use App\Models\DocumentProcessingRun;
use App\Services\DocumentProcessing\DocxStandardizationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class ProcessExamDocumentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(
        public readonly int $processingRunId
    ) {}

    public function handle(DocxStandardizationService $service): void
    {
        $run = DocumentProcessingRun::query()->find($this->processingRunId);
        if ($run === null) {
            return;
        }

        $service->executeRun($run);
    }

    public function failed(?Throwable $exception): void
    {
        $run = DocumentProcessingRun::query()->with('examDocument')->find($this->processingRunId);
        if ($run === null) {
            return;
        }

        $run->update([
            'status' => ProcessingRunStatus::FAILED,
            'error_message' => Str::limit($exception?->getMessage() ?? 'Job antrean berhenti sebelum selesai.', 1000),
            'finished_at' => now(),
        ]);

        $run->examDocument?->update([
            'current_status' => DocumentStatus::FAILED,
        ]);
    }
}