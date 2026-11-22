<?php

namespace App\Models;

use App\Enums\DocumentFileRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentFile extends Model
{
    protected $fillable = [
        'exam_document_id',
        'processing_run_id',
        'file_role',
        'original_filename',
        'storage_disk',
        'storage_path',
        'mime_type',
        'extension',
        'size_bytes',
        'checksum_sha256',
        'is_current',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_role' => DocumentFileRole::class,
            'size_bytes' => 'integer',
            'is_current' => 'boolean',
        ];
    }

    public function examDocument(): BelongsTo
    {
        return $this->belongsTo(ExamDocument::class);
    }

    public function processingRun(): BelongsTo
    {
        return $this->belongsTo(DocumentProcessingRun::class, 'processing_run_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function humanSize(): string
    {
        $bytes = $this->size_bytes;

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}