<?php

namespace App\Models;

use App\Enums\DocumentFileRole;
use App\Enums\ProcessingRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentProcessingRun extends Model
{
    protected $fillable = [
        'exam_document_id',
        'document_processing_config_id',
        'triggered_by',
        'target_type',
        'status',
        'pdf_status',
        'engine_name',
        'engine_version',
        'python_version',
        'docx_library_version',
        'libreoffice_version',
        'input_checksum_sha256',
        'output_docx_checksum_sha256',
        'output_pdf_checksum_sha256',
        'needs_manual_review',
        'metrics_json',
        'error_message',
        'pdf_error_message',
        'started_at',
        'finished_at',
        'duration_ms',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProcessingRunStatus::class,
            'needs_manual_review' => 'boolean',
            'metrics_json' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
        ];
    }

    public function examDocument(): BelongsTo
    {
        return $this->belongsTo(ExamDocument::class);
    }

    public function processingConfig(): BelongsTo
    {
        return $this->belongsTo(DocumentProcessingConfig::class, 'document_processing_config_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(DocumentWarning::class)->orderBy('id');
    }

    public function outputDocxFile(): HasOne
    {
        return $this->hasOne(DocumentFile::class, 'processing_run_id')
            ->where('file_role', DocumentFileRole::OUTPUT_DOCX);
    }

    public function outputPdfFile(): HasOne
    {
        return $this->hasOne(DocumentFile::class, 'processing_run_id')
            ->where('file_role', DocumentFileRole::OUTPUT_PDF);
    }
}