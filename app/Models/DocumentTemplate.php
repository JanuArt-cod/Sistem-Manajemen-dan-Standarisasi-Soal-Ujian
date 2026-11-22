<?php

namespace App\Models;

use App\Enums\TemplateHeaderMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class DocumentTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'header_mode',
        'first_page_only',
        'show_school_logo',
        'show_exam_identity_table',
        'header_title',
        'header_subtitle',
        'instruction_text',
        'template_file_disk',
        'template_file_path',
        'template_original_name',
        'template_checksum',
        'is_default',
        'is_active',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'header_mode' => TemplateHeaderMode::class,
            'first_page_only' => 'boolean',
            'show_school_logo' => 'boolean',
            'show_exam_identity_table' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasCustomDocxFile(): bool
    {
        return $this->header_mode === TemplateHeaderMode::CUSTOM_DOCX
            && filled($this->template_file_disk)
            && filled($this->template_file_path);
    }

    public static function defaultTemplate(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    public function markAsDefault(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->where('id', '!=', $this->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);

            $this->update([
                'is_default' => true,
                'is_active' => true,
            ]);
        });
    }
}