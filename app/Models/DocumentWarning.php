<?php

namespace App\Models;

use App\Enums\WarningSeverity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentWarning extends Model
{
    protected $fillable = [
        'exam_document_id',
        'document_processing_run_id',
        'code',
        'severity',
        'message',
        'location',
        'is_resolved',
        'resolved_by',
        'resolved_at',
        'resolution_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => WarningSeverity::class,
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function examDocument(): BelongsTo
    {
        return $this->belongsTo(ExamDocument::class);
    }

    public function processingRun(): BelongsTo
    {
        return $this->belongsTo(DocumentProcessingRun::class, 'document_processing_run_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}