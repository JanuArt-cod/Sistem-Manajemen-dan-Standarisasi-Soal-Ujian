<?php

namespace App\Models;

use App\Enums\DocumentFileRole;
use App\Enums\DocumentSourceFormat;
use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExamDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'exam_period_id',
        'subject_id',
        'school_class_id',
        'teacher_name',
        'exam_date',
        'duration_minutes',
        'document_title',
        'source_format',
        'current_status',
        'notes',
        'uploaded_by',
        'approved_by',
        'approved_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'duration_minutes' => 'integer',
            'source_format' => DocumentSourceFormat::class,
            'current_status' => DocumentStatus::class,
            'approved_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function examPeriod(): BelongsTo
    {
        return $this->belongsTo(ExamPeriod::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(DocumentFile::class);
    }

    public function sourceFile(): HasOne
    {
        return $this->hasOne(DocumentFile::class)
            ->where('file_role', DocumentFileRole::SOURCE)
            ->where('is_current', true)
            ->latest('id');
    }

    public function outputDocxFile(): HasOne
    {
        return $this->hasOne(DocumentFile::class)
            ->where('file_role', DocumentFileRole::OUTPUT_DOCX)
            ->where('is_current', true)
            ->latest('id');
    }

    public function outputPdfFile(): HasOne
    {
        return $this->hasOne(DocumentFile::class)
            ->where('file_role', DocumentFileRole::OUTPUT_PDF)
            ->where('is_current', true)
            ->latest('id');
    }

    public function processingConfigs(): HasMany
    {
        return $this->hasMany(DocumentProcessingConfig::class);
    }

    public function processingConfig(): HasOne
    {
        return $this->hasOne(DocumentProcessingConfig::class)->latest('id');
    }

    public function processingRuns(): HasMany
    {
        return $this->hasMany(DocumentProcessingRun::class)->orderByDesc('id');
    }

    public function latestProcessingRun(): HasOne
    {
        return $this->hasOne(DocumentProcessingRun::class)->latestOfMany();
    }

    public function warnings(): HasMany
    {
        return $this->hasMany(DocumentWarning::class)->orderBy('id');
    }
}