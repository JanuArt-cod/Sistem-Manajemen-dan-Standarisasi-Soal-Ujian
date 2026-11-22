<?php

namespace App\Services\DocumentProcessing;

use App\Enums\DocumentFileRole;
use App\Enums\DocumentStatus;
use App\Enums\ProcessingRunStatus;
use App\Enums\WarningSeverity;
use App\Exceptions\PdfConversionException;
use App\Models\DocumentFile;
use App\Models\DocumentProcessingRun;
use App\Models\DocumentWarning;
use App\Models\ExamDocument;
use App\Services\FormattingSnapshotBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DocxStandardizationService
{
    public function __construct(
        private readonly FormattingSnapshotBuilder $snapshotBuilder,
        private readonly LibreOfficePdfConverter $pdfConverter
    ) {}

    /**
     * Menjalankan standarisasi DOCX diikuti konversi PDF di direktori kerja terisolasi.
     * Jika konversi PDF gagal, hasil DOCX tetap dipertahankan dan status dokumen menjadi PDF_FAILED.
     */
    public function executeRun(DocumentProcessingRun $run): DocumentProcessingRun
    {
        $startTime = microtime(true);
        $examDocument = $run->examDocument()->with(['sourceFile', 'processingConfig'])->firstOrFail();

        $run->update([
            'status' => ProcessingRunStatus::PROCESSING,
            'pdf_status' => 'PENDING',
            'started_at' => now(),
            'error_message' => null,
            'pdf_error_message' => null,
        ]);

        $examDocument->update([
            'current_status' => DocumentStatus::PROCESSING,
        ]);

        $workDir = storage_path('app/private/tmp_runs/run_' . $run->id . '_' . Str::uuid());

        try {
            $sourceFile = $examDocument->sourceFile;
            if ($sourceFile === null) {
                throw new RuntimeException('File sumber asli (.docx) tidak ditemukan pada dokumen ini.');
            }

            $sourceDisk = Storage::disk($sourceFile->storage_disk);
            if (! $sourceDisk->exists($sourceFile->storage_path)) {
                throw new RuntimeException('Berkas sumber asli tidak ditemukan di penyimpanan privat.');
            }

            $config = $run->processingConfig ?? $this->snapshotBuilder->ensureForDocument($examDocument);
            if ($run->document_processing_config_id === null) {
                $run->update(['document_processing_config_id' => $config->id]);
            }

            File::ensureDirectoryExists($workDir);

            // Salin file sumber ke direktori sementara agar file sumber asli 100% immutable
            $tempInputPath = $workDir . DIRECTORY_SEPARATOR . 'input.docx';
            $tempOutputPath = $workDir . DIRECTORY_SEPARATOR . 'output.docx';
            $tempConfigPath = $workDir . DIRECTORY_SEPARATOR . 'snapshot.json';
            $tempReportPath = $workDir . DIRECTORY_SEPARATOR . 'report.json';

            $sourceBinary = $sourceDisk->get($sourceFile->storage_path);
            if ($sourceBinary === null || $sourceBinary === '') {
                throw new RuntimeException('Berkas sumber asli kosong atau gagal dibaca.');
            }
            file_put_contents($tempInputPath, $sourceBinary);

            // Siapkan aset pendukung dari disk privat ke direktori kerja sementara
            $snapshotPayload = $config->snapshot_payload ?? [];
            $runtimePaths = [];

            $logoDiskName = $snapshotPayload['school']['logo_disk'] ?? null;
            $logoStoragePath = $snapshotPayload['school']['logo_path'] ?? null;
            if (is_string($logoDiskName) && is_string($logoStoragePath) && $logoDiskName !== '' && $logoStoragePath !== '') {
                $logoDisk = Storage::disk($logoDiskName);
                if ($logoDisk->exists($logoStoragePath)) {
                    $ext = pathinfo($logoStoragePath, PATHINFO_EXTENSION) ?: 'png';
                    $tempLogoPath = $workDir . DIRECTORY_SEPARATOR . 'school_logo.' . $ext;
                    file_put_contents($tempLogoPath, (string) $logoDisk->get($logoStoragePath));
                    $runtimePaths['logo_abs_path'] = $tempLogoPath;
                }
            }

            $tplDiskName = $snapshotPayload['template']['template_file_disk'] ?? null;
            $tplStoragePath = $snapshotPayload['template']['template_file_path'] ?? null;
            if (is_string($tplDiskName) && is_string($tplStoragePath) && $tplDiskName !== '' && $tplStoragePath !== '') {
                $tplDisk = Storage::disk($tplDiskName);
                if ($tplDisk->exists($tplStoragePath)) {
                    $tempTplPath = $workDir . DIRECTORY_SEPARATOR . 'custom_template.docx';
                    file_put_contents($tempTplPath, (string) $tplDisk->get($tplStoragePath));
                    $runtimePaths['template_abs_path'] = $tempTplPath;
                }
            }

            $snapshotPayload['runtime'] = $runtimePaths;

            file_put_contents(
                $tempConfigPath,
                (string) json_encode($snapshotPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
            );

            $pythonBinary = (string) config('simsoal.python_binary', 'python');
            $scriptRelPath = (string) config('simsoal.engine_script', 'engine/simsoal_docx/cli.py');
            $scriptAbsPath = str_starts_with($scriptRelPath, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:\\\\/', $scriptRelPath)
                ? $scriptRelPath
                : base_path($scriptRelPath);

            if (! is_file($scriptAbsPath)) {
                throw new RuntimeException('Skrip mesin standarisasi dokumen tidak ditemukan di server.');
            }

            $timeoutSeconds = max(30, (int) config('simsoal.process_timeout', 120));

            $processResult = Process::timeout($timeoutSeconds)->run([
                $pythonBinary,
                $scriptAbsPath,
                '--input',
                $tempInputPath,
                '--config',
                $tempConfigPath,
                '--output',
                $tempOutputPath,
                '--report',
                $tempReportPath,
            ]);

            $reportJsonRaw = is_file($tempReportPath)
                ? (string) file_get_contents($tempReportPath)
                : trim($processResult->output());

            $report = json_decode($reportJsonRaw, true);

            if (! is_array($report)) {
                Log::error('SIMSOAL Python engine returned invalid JSON', [
                    'run_id' => $run->id,
                    'exit_code' => $processResult->exitCode(),
                    'stderr' => $processResult->errorOutput(),
                ]);
                throw new RuntimeException('Mesin standarisasi menghasilkan laporan yang tidak valid.');
            }

            $engineStatus = (string) ($report['status'] ?? 'FAILED');
            if (! $processResult->successful() || ! in_array($engineStatus, ['COMPLETED', 'COMPLETED_WITH_WARNINGS'], true)) {
                $engineError = (string) ($report['error_message'] ?? 'Proses standarisasi DOCX gagal dijalankan oleh mesin dokumen.');
                throw new RuntimeException($engineError);
            }

            if (! is_file($tempOutputPath) || filesize($tempOutputPath) <= 0) {
                throw new RuntimeException('File keluaran .docx tidak terbentuk setelah proses selesai.');
            }

            // Pastikan sumber asli di penyimpanan privat tetap utuh dan tidak berubah
            $currentSourceHash = hash('sha256', (string) $sourceDisk->get($sourceFile->storage_path));
            if ($currentSourceHash !== $sourceFile->checksum_sha256) {
                throw new RuntimeException('Pelanggaran integritas: checksum file sumber asli berubah.');
            }

            $outputBinary = (string) file_get_contents($tempOutputPath);
            $outputChecksum = hash('sha256', $outputBinary);
            $outputSize = strlen($outputBinary);

            $outputStorageDisk = $sourceFile->storage_disk;
            $outputStoragePath = sprintf(
                'exam-documents/%d/outputs/%s.docx',
                $examDocument->exam_period_id,
                Str::uuid()->toString()
            );

            $stored = Storage::disk($outputStorageDisk)->put($outputStoragePath, $outputBinary);
            if (! $stored) {
                throw new RuntimeException('Gagal menyimpan file hasil standarisasi .docx ke penyimpanan privat.');
            }

            $needsManualReview = (bool) ($report['needs_manual_review'] ?? false);
            $warningsData = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
            $stem = pathinfo($sourceFile->original_filename, PATHINFO_FILENAME);
            $safeStem = Str::slug($stem, '_') ?: ('dokumen_' . $examDocument->id);

            DB::transaction(function () use (
                $run,
                $examDocument,
                $sourceFile,
                $outputStorageDisk,
                $outputStoragePath,
                $outputSize,
                $outputChecksum,
                $safeStem,
                $report,
                $engineStatus,
                $needsManualReview,
                $warningsData
            ) {
                DocumentFile::query()
                    ->where('exam_document_id', $examDocument->id)
                    ->where('file_role', DocumentFileRole::OUTPUT_DOCX)
                    ->update(['is_current' => false]);

                DocumentFile::query()->create([
                    'exam_document_id' => $examDocument->id,
                    'processing_run_id' => $run->id,
                    'file_role' => DocumentFileRole::OUTPUT_DOCX,
                    'original_filename' => "STD_{$safeStem}.docx",
                    'storage_disk' => $outputStorageDisk,
                    'storage_path' => $outputStoragePath,
                    'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'extension' => 'docx',
                    'size_bytes' => $outputSize,
                    'checksum_sha256' => $outputChecksum,
                    'is_current' => true,
                    'created_by' => $run->triggered_by,
                ]);

                foreach ($warningsData as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $rawSeverity = strtoupper((string) ($item['severity'] ?? 'WARNING'));
                    $severity = WarningSeverity::tryFrom($rawSeverity) ?? WarningSeverity::WARNING;

                    DocumentWarning::query()->create([
                        'exam_document_id' => $examDocument->id,
                        'document_processing_run_id' => $run->id,
                        'code' => Str::limit((string) ($item['code'] ?? 'ENGINE_WARNING'), 80, ''),
                        'severity' => $severity,
                        'message' => (string) ($item['message'] ?? 'Peringatan struktur dokumen.'),
                        'location' => isset($item['location']) ? Str::limit((string) $item['location'], 120, '') : null,
                        'is_resolved' => false,
                    ]);
                }

                $runStatus = $engineStatus === 'COMPLETED_WITH_WARNINGS'
                    ? ProcessingRunStatus::COMPLETED_WITH_WARNINGS
                    : ProcessingRunStatus::COMPLETED;

                $run->update([
                    'status' => $runStatus,
                    'engine_name' => $report['engine_name'] ?? 'simsoal-docx-engine',
                    'engine_version' => $report['engine_version'] ?? null,
                    'python_version' => $report['python_version'] ?? null,
                    'docx_library_version' => $report['docx_library_version'] ?? null,
                    'input_checksum_sha256' => $report['input_checksum'] ?? $sourceFile->checksum_sha256,
                    'output_docx_checksum_sha256' => $outputChecksum,
                    'needs_manual_review' => $needsManualReview,
                    'metrics_json' => is_array($report['metrics'] ?? null) ? $report['metrics'] : [],
                    'error_message' => null,
                ]);
            });

            // Langkah 2 Terisolasi: Konversi DOCX terstandarisasi ke PDF menggunakan LibreOffice
            $this->convertAndPersistPdf(
                $run,
                $examDocument,
                $tempOutputPath,
                $workDir,
                $outputStorageDisk,
                $safeStem,
                $needsManualReview,
                $startTime
            );
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            Log::error('SIMSOAL DOCX standardization failed', [
                'run_id' => $run->id,
                'exam_document_id' => $examDocument->id,
                'message' => $e->getMessage(),
            ]);

            $run->update([
                'status' => ProcessingRunStatus::FAILED,
                'pdf_status' => 'SKIPPED',
                'error_message' => Str::limit($e->getMessage(), 1000),
                'finished_at' => now(),
                'duration_ms' => $durationMs,
            ]);

            $examDocument->update([
                'current_status' => DocumentStatus::FAILED,
            ]);
        } finally {
            if (File::isDirectory($workDir)) {
                File::deleteDirectory($workDir);
            }
        }

        return $run->fresh(['warnings', 'outputDocxFile', 'outputPdfFile']);
    }

    /**
     * Mengekspor ulang PDF dari file OUTPUT_DOCX yang sudah tersedia tanpa mengubah file sumber maupun DOCX.
     */
    public function exportPdfOnly(ExamDocument $examDocument, ?int $triggeredBy = null): DocumentProcessingRun
    {
        $startTime = microtime(true);
        $examDocument->loadMissing(['outputDocxFile', 'sourceFile', 'processingConfig']);

        $outputDocx = $examDocument->outputDocxFile;
        if ($outputDocx === null) {
            throw new RuntimeException('File hasil standarisasi DOCX belum tersedia untuk diekspor ke PDF.');
        }

        $docxDisk = Storage::disk($outputDocx->storage_disk);
        if (! $docxDisk->exists($outputDocx->storage_path)) {
            throw new RuntimeException('Berkas hasil DOCX tidak ditemukan di penyimpanan privat.');
        }

        $latestDocxRun = DocumentProcessingRun::query()
            ->where('exam_document_id', $examDocument->id)
            ->where('target_type', 'DOCX')
            ->whereNotNull('output_docx_checksum_sha256')
            ->latest('id')
            ->first();

        $needsManualReview = (bool) ($latestDocxRun?->needs_manual_review ?? false);

        $run = DocumentProcessingRun::query()->create([
            'exam_document_id' => $examDocument->id,
            'document_processing_config_id' => $examDocument->processingConfig?->id,
            'triggered_by' => $triggeredBy,
            'target_type' => 'PDF',
            'status' => ProcessingRunStatus::PROCESSING,
            'pdf_status' => 'PENDING',
            'input_checksum_sha256' => $outputDocx->checksum_sha256,
            'output_docx_checksum_sha256' => $outputDocx->checksum_sha256,
            'needs_manual_review' => $needsManualReview,
            'started_at' => now(),
        ]);

        $workDir = storage_path('app/private/tmp_runs/pdf_run_' . $run->id . '_' . Str::uuid());

        try {
            File::ensureDirectoryExists($workDir);
            $tempDocxPath = $workDir . DIRECTORY_SEPARATOR . 'standardized.docx';
            file_put_contents($tempDocxPath, (string) $docxDisk->get($outputDocx->storage_path));

            $baseName = $examDocument->sourceFile
                ? pathinfo($examDocument->sourceFile->original_filename, PATHINFO_FILENAME)
                : ('dokumen_' . $examDocument->id);
            $safeStem = Str::slug($baseName, '_') ?: ('dokumen_' . $examDocument->id);

            $this->convertAndPersistPdf(
                $run,
                $examDocument,
                $tempDocxPath,
                $workDir,
                $outputDocx->storage_disk,
                $safeStem,
                $needsManualReview,
                $startTime
            );
        } finally {
            if (File::isDirectory($workDir)) {
                File::deleteDirectory($workDir);
            }
        }

        return $run->fresh(['warnings', 'outputDocxFile', 'outputPdfFile']);
    }

    private function convertAndPersistPdf(
        DocumentProcessingRun $run,
        ExamDocument $examDocument,
        string $docxAbsPath,
        string $workDir,
        string $storageDisk,
        string $safeStem,
        bool $needsManualReview,
        float $startTime
    ): void {
        try {
            $conversion = $this->pdfConverter->convertDocxToPdf($docxAbsPath, $workDir);
            $pdfBinary = (string) file_get_contents($conversion['pdf_path']);
            $pdfChecksum = hash('sha256', $pdfBinary);
            $pdfSize = strlen($pdfBinary);

            $pdfStoragePath = sprintf(
                'exam-documents/%d/outputs/%s.pdf',
                $examDocument->exam_period_id,
                Str::uuid()->toString()
            );

            $storedPdf = Storage::disk($storageDisk)->put($pdfStoragePath, $pdfBinary);
            if (! $storedPdf) {
                throw new PdfConversionException(
                    'INSUFFICIENT_STORAGE',
                    'Gagal menyimpan hasil cetak PDF ke penyimpanan privat.'
                );
            }

            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            DB::transaction(function () use (
                $run,
                $examDocument,
                $storageDisk,
                $pdfStoragePath,
                $pdfSize,
                $pdfChecksum,
                $safeStem,
                $conversion,
                $needsManualReview,
                $durationMs
            ) {
                DocumentFile::query()
                    ->where('exam_document_id', $examDocument->id)
                    ->where('file_role', DocumentFileRole::OUTPUT_PDF)
                    ->update(['is_current' => false]);

                DocumentFile::query()->create([
                    'exam_document_id' => $examDocument->id,
                    'processing_run_id' => $run->id,
                    'file_role' => DocumentFileRole::OUTPUT_PDF,
                    'original_filename' => "STD_{$safeStem}.pdf",
                    'storage_disk' => $storageDisk,
                    'storage_path' => $pdfStoragePath,
                    'mime_type' => 'application/pdf',
                    'extension' => 'pdf',
                    'size_bytes' => $pdfSize,
                    'checksum_sha256' => $pdfChecksum,
                    'is_current' => true,
                    'created_by' => $run->triggered_by,
                ]);

                $nextRunStatus = $run->status === ProcessingRunStatus::PROCESSING
                    ? ProcessingRunStatus::COMPLETED
                    : $run->status;

                $run->update([
                    'status' => $nextRunStatus,
                    'pdf_status' => 'COMPLETED',
                    'libreoffice_version' => $conversion['libreoffice_version'],
                    'output_pdf_checksum_sha256' => $pdfChecksum,
                    'pdf_error_message' => null,
                    'finished_at' => now(),
                    'duration_ms' => $durationMs,
                ]);

                $examDocument->update([
                    'current_status' => $needsManualReview
                        ? DocumentStatus::NEEDS_REVIEW
                        : DocumentStatus::PROCESSED,
                ]);
            });
        } catch (Throwable $pdfException) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);
            $errorCode = $pdfException instanceof PdfConversionException
                ? $pdfException->errorCode
                : 'PDF_CONVERSION_FAILED';
            $userMessage = $pdfException instanceof PdfConversionException
                ? $pdfException->getMessage()
                : 'Konversi PDF gagal dijalankan. Hasil DOCX tetap tersedia untuk diunduh.';

            Log::warning('SIMSOAL PDF export failed while preserving DOCX output', [
                'run_id' => $run->id,
                'exam_document_id' => $examDocument->id,
                'error_code' => $errorCode,
                'message' => $pdfException->getMessage(),
            ]);

            DB::transaction(function () use ($run, $examDocument, $errorCode, $userMessage, $durationMs) {
                DocumentWarning::query()->create([
                    'exam_document_id' => $examDocument->id,
                    'document_processing_run_id' => $run->id,
                    'code' => $errorCode,
                    'severity' => WarningSeverity::WARNING,
                    'message' => $userMessage,
                    'location' => 'Ekspor PDF',
                    'is_resolved' => false,
                ]);

                $nextRunStatus = $run->target_type === 'PDF'
                    ? ProcessingRunStatus::FAILED
                    : ProcessingRunStatus::COMPLETED_WITH_WARNINGS;

                $run->update([
                    'status' => $nextRunStatus,
                    'pdf_status' => 'FAILED',
                    'pdf_error_message' => Str::limit($userMessage, 1000),
                    'error_message' => $run->target_type === 'PDF' ? Str::limit($userMessage, 1000) : $run->error_message,
                    'finished_at' => now(),
                    'duration_ms' => $durationMs,
                ]);

                // Tetap tandai PDF_FAILED namun OUTPUT_DOCX tetap valid dan dapat diunduh
                $examDocument->update([
                    'current_status' => DocumentStatus::PDF_FAILED,
                ]);
            });
        }
    }
}