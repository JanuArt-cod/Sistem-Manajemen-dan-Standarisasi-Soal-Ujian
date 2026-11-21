<?php

namespace App\Models;

use App\Enums\ExamPeriodStatus;
use App\Enums\ExamSemester;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExamPeriod extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'academic_year',
        'semester',
        'start_date',
        'end_date',
        'status',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'semester' => ExamSemester::class,
            'status' => ExamPeriodStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isArchived(): bool
    {
        return $this->status === ExamPeriodStatus::ARCHIVED;
    }

    public function acceptsUploads(): bool
    {
        return ! $this->isArchived();
    }
}