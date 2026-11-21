<?php

namespace App\Services\Storage;

use App\Enums\DocumentFileRole;
use App\Enums\DocumentSourceFormat;
use App\Enums\DocumentStatus;
use App\Models\DocumentFile;
use App\Models\ExamDocument;
use App\Models\ExamPeriod;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PrivateDocumentStorageService
{
    /**
     * Simpan beberapa dokumen sumber DOCX beserta metadata secara atomik ke disk privat.
     *
     * @param  array<int, array{
     *     file: UploadedFile,
     *     validation: array{mime_type: string, size_bytes: int, checksum_sha256: string},
     *     subject_id: ?int,
     *     school_class_id: ?int,
     *     teacher_name: string,
     *     exam_date: ?string,
     *     duration_minutes: ?int,
     *     document_title: ?string,
     *     notes: ?string
     * }>  $items
     * @return array{documents: array<int, ExamDocument>, duplicate_count: int}
     *
     * @throws Throwable
     */
    public function storeBatchSources(ExamPeriod $period, array $items, User $uploader): array
    {
        $disk = 'local';
        $storedPaths = [];
        $createdDocuments = [];
        $duplicateCount = 0;

        try {
            DB::transaction(function () use (
                $period,
                $items,
                $uploader,
                $disk,
                &$storedPaths,
                &$createdDocuments,
                &$duplicateCount
            ): void {
                foreach ($items as $item) {
                    /** @var UploadedFile $file */
                    $file = $item['file'];
                    $validation = $item['validation'];

                    $alreadyExistsInPeriod = DocumentFile::query()
                        ->where('checksum_sha256', $validation['checksum_sha256'])
                        ->where('file_role', DocumentFileRole::SOURCE)
                        ->whereHas('examDocument', fn ($q) => $q->where('exam_period_id', $period->id))
                        ->exists();

                    if ($alreadyExistsInPeriod) {
                        $duplicateCount++;
                    }

                    $directory = 'sources/'.now()->format('Y/m');
                    $safeFilename = Str::uuid().'.docx';
                    $relativePath = $file->storeAs($directory, $safeFilename, $disk);

                    if ($relativePath === false) {
                        throw new \RuntimeException('Gagal menulis file sumber ke penyimpanan privat.');
                    }

                    $storedPaths[] = $relativePath;

                    $originalFilename = basename($file->getClientOriginalName());
                    $defaultTitle = pathinfo($originalFilename, PATHINFO_FILENAME);

                    $document = ExamDocument::query()->create([
                        'exam_period_id' => $period->id,
                        'subject_id' => $item['subject_id'] ?? null,
                        'school_class_id' => $item['school_class_id'] ?? null,
                        'teacher_name' => trim($item['teacher_name']),
                        'exam_date' => $item['exam_date'] ?? null,
                        'duration_minutes' => $item['duration_minutes'] ?? null,
                        'document_title' => filled($item['document_title'] ?? null)
                            ? trim((string) $item['document_title'])
                            : $defaultTitle,
                        'source_format' => DocumentSourceFormat::DOCX,
                        'current_status' => DocumentStatus::UPLOADED,
                        'notes' => filled($item['notes'] ?? null) ? trim((string) $item['notes']) : null,
                        'uploaded_by' => $uploader->id,
                    ]);

                    DocumentFile::query()->create([
                        'exam_document_id' => $document->id,
                        'processing_run_id' => null,
                        'file_role' => DocumentFileRole::SOURCE,
                        'original_filename' => $originalFilename,
                        'storage_disk' => $disk,
                        'storage_path' => $relativePath,
                        'mime_type' => $validation['mime_type'],
                        'extension' => 'docx',
                        'size_bytes' => $validation['size_bytes'],
                        'checksum_sha256' => $validation['checksum_sha256'],
                        'is_current' => true,
                        'created_by' => $uploader->id,
                    ]);

                    $createdDocuments[] = $document;
                }
            });
        } catch (Throwable $e) {
            foreach ($storedPaths as $path) {
                Storage::disk($disk)->delete($path);
            }

            throw $e;
        }

        return [
            'documents' => $createdDocuments,
            'duplicate_count' => $duplicateCount,
        ];
    }
}