<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentProcessingConfig extends Model
{
    protected $fillable = [
        'exam_document_id',
        'formatting_preset_id',
        'document_template_id',
        'snapshot_payload',
        'snapshot_checksum',
        'configured_by',
        'configured_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_payload' => 'array',
            'configured_at' => 'datetime',
        ];
    }

    public function examDocument(): BelongsTo
    {
        return $this->belongsTo(ExamDocument::class);
    }

    public function formattingPreset(): BelongsTo
    {
        return $this->belongsTo(FormattingPreset::class);
    }

    public function documentTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class);
    }

    public function configurator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'configured_by');
    }
}