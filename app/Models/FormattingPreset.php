<?php

namespace App\Models;

use App\Enums\PaperCode;
use App\Enums\PaperOrientation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class FormattingPreset extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'paper_code',
        'paper_width_mm',
        'paper_height_mm',
        'orientation',
        'margin_top_mm',
        'margin_bottom_mm',
        'margin_left_mm',
        'margin_right_mm',
        'font_family',
        'font_size_pt',
        'line_spacing',
        'paragraph_spacing_before_pt',
        'paragraph_spacing_after_pt',
        'preserve_original_styles',
        'apply_to_existing_tables',
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
            'paper_code' => PaperCode::class,
            'orientation' => PaperOrientation::class,
            'paper_width_mm' => 'float',
            'paper_height_mm' => 'float',
            'margin_top_mm' => 'float',
            'margin_bottom_mm' => 'float',
            'margin_left_mm' => 'float',
            'margin_right_mm' => 'float',
            'font_size_pt' => 'float',
            'line_spacing' => 'float',
            'paragraph_spacing_before_pt' => 'float',
            'paragraph_spacing_after_pt' => 'float',
            'preserve_original_styles' => 'boolean',
            'apply_to_existing_tables' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function defaultPreset(): ?self
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