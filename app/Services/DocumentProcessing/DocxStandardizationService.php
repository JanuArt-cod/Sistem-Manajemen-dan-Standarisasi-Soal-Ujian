<?php

namespace App\Services\DocumentProcessing;

use App\Enums\DocumentFileRole;
use App\Enums\DocumentStatus;
use App\Enums\ProcessingRunStatus;
use App\Enums\WarningSeverity;
use App\Models\DocumentFile;
use App\Models\DocumentProcessingRun;
use App\Models\DocumentWarning;
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
        private readonly FormattingSnapshotBuilder $snapshotBuilder
    ) {}

    /**
     * Menjalankan standarisasi DOCX untuk satu eksekusi DocumentProcessingRun di direktori kerja terisolasi.
     */
    public function executeRun(DocumentProcessingRun $run): DocumentProcessingRun
    {
        $startTime = microtime(true);
        $examDocument = $run->examDocument()->with(['sourceFile', 'processingConfig'])->firstOrFail();

        $run->update([
            'status' => ProcessingRunStatus::PROCESSING,
            'started_at' => now(),
            'error_message' => null,
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
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            DB::transaction(function () use (
                $run,
                $examDocument,
                $sourceFile,
                $outputStorageDisk,
                $outputStoragePath,
                $outputSize,
                $outputChecksum,
                $report,
                $engineStatus,
                $needsManualReview,
                $warningsData,
                $durationMs
            ) {
                DocumentFile::query()
                    ->where('exam_document_id', $examDocument->id)
                    ->where('file_role', DocumentFileRole::OUTPUT_DOCX)
                    ->update(['is_current' => false]);

                $stem = pathinfo($sourceFile->original_filename, PATHINFO_FILENAME);
                $safeStem = Str::slug($stem, '_') ?: ('dokumen_' . $examDocument->id);

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
                    'finished_at' => now(),
                    'duration_ms' => $durationMs,
                ]);

                $examDocument->update([
                    'current_status' => $needsManualReview
                        ? DocumentStatus::NEEDS_REVIEW
                        : DocumentStatus::PROCESSED,
                ]);
            });
        } catch (Throwable $e) {
            $durationMs = (int) round((microtime(true) - $startTime) * 1000);

            Log::error('SIMSOAL DOCX standardization failed', [
                'run_id' => $run->id,
                'exam_document_id' => $examDocument->id,
                'message' => $e->getMessage(),
            ]);

            $run->update([
                'status' => ProcessingRunStatus::FAILED,
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

        return $run->fresh(['warnings', 'outputDocxFile']);
    }
}