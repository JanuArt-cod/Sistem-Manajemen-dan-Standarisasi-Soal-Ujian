<?php

namespace App\Services\Formatting;

use App\Enums\PageOrientation;
use App\Enums\PaperSize;
use App\Enums\TemplateHeaderMode;
use App\Models\DocumentTemplate;
use App\Models\FormattingPreset;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class FormattingPresetService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createPreset(array $data, User $creator): FormattingPreset
    {
        return DB::transaction(function () use ($data, $creator): FormattingPreset {
            $paperSize = PaperSize::from($data['paper_size']);
            $dimensions = $paperSize === PaperSize::CUSTOM
                ? [
                    'width_mm' => (float) $data['paper_width_mm'],
                    'height_mm' => (float) $data['paper_height_mm'],
                ]
                : $paperSize->defaultDimensionsMm();

            $isDefault = (bool) ($data['is_default'] ?? false);
            $isActive = (bool) ($data['is_active'] ?? true);

            if ($isDefault) {
                $isActive = true;
                FormattingPreset::query()->where('is_default', true)->update(['is_default' => false]);
            } elseif (! FormattingPreset::query()->where('is_default', true)->exists() && $isActive) {
                $isDefault = true;
            }

            return FormattingPreset::query()->create([
                'name' => trim($data['name']),
                'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
                'paper_size' => $paperSize,
                'orientation' => PageOrientation::from($data['orientation'] ?? PageOrientation::PORTRAIT->value),
                'paper_width_mm' => $dimensions['width_mm'],
                'paper_height_mm' => $dimensions['height_mm'],
                'margin_top_mm' => (float) $data['margin_top_mm'],
                'margin_bottom_mm' => (float) $data['margin_bottom_mm'],
                'margin_left_mm' => (float) $data['margin_left_mm'],
                'margin_right_mm' => (float) $data['margin_right_mm'],
                'font_family' => trim($data['font_family']),
                'font_size_pt' => (float) $data['font_size_pt'],
                'line_spacing' => (float) $data['line_spacing'],
                'paragraph_spacing_before_pt' => (float) ($data['paragraph_spacing_before_pt'] ?? 0),
                'paragraph_spacing_after_pt' => (float) ($data['paragraph_spacing_after_pt'] ?? 0),
                'question_numbering_style' => $data['question_numbering_style'] ?? 'KEEP_ORIGINAL',
                'option_numbering_style' => $data['option_numbering_style'] ?? 'KEEP_ORIGINAL',
                'normalize_tables' => (bool) ($data['normalize_tables'] ?? true),
                'normalize_images' => (bool) ($data['normalize_images'] ?? true),
                'is_default' => $isDefault,
                'is_active' => $isActive,
                'created_by' => $creator->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePreset(FormattingPreset $preset, array $data): FormattingPreset
    {
        return DB::transaction(function () use ($preset, $data): FormattingPreset {
            $paperSize = PaperSize::from($data['paper_size']);
            $dimensions = $paperSize === PaperSize::CUSTOM
                ? [
                    'width_mm' => (float) $data['paper_width_mm'],
                    'height_mm' => (float) $data['paper_height_mm'],
                ]
                : $paperSize->defaultDimensionsMm();

            $isDefault = (bool) ($data['is_default'] ?? $preset->is_default);
            $isActive = (bool) ($data['is_active'] ?? $preset->is_active);

            if ($isDefault) {
                $isActive = true;
                FormattingPreset::query()
                    ->where('id', '!=', $preset->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $preset->update([
                'name' => trim($data['name']),
                'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
                'paper_size' => $paperSize,
                'orientation' => PageOrientation::from($data['orientation'] ?? PageOrientation::PORTRAIT->value),
                'paper_width_mm' => $dimensions['width_mm'],
                'paper_height_mm' => $dimensions['height_mm'],
                'margin_top_mm' => (float) $data['margin_top_mm'],
                'margin_bottom_mm' => (float) $data['margin_bottom_mm'],
                'margin_left_mm' => (float) $data['margin_left_mm'],
                'margin_right_mm' => (float) $data['margin_right_mm'],
                'font_family' => trim($data['font_family']),
                'font_size_pt' => (float) $data['font_size_pt'],
                'line_spacing' => (float) $data['line_spacing'],
                'paragraph_spacing_before_pt' => (float) ($data['paragraph_spacing_before_pt'] ?? 0),
                'paragraph_spacing_after_pt' => (float) ($data['paragraph_spacing_after_pt'] ?? 0),
                'question_numbering_style' => $data['question_numbering_style'] ?? 'KEEP_ORIGINAL',
                'option_numbering_style' => $data['option_numbering_style'] ?? 'KEEP_ORIGINAL',
                'normalize_tables' => (bool) ($data['normalize_tables'] ?? true),
                'normalize_images' => (bool) ($data['normalize_images'] ?? true),
                'is_default' => $isDefault,
                'is_active' => $isActive,
            ]);

            return $preset->refresh();
        });
    }

    public function setDefaultPreset(FormattingPreset $preset): FormattingPreset
    {
        return DB::transaction(function () use ($preset): FormattingPreset {
            FormattingPreset::query()
                ->where('id', '!=', $preset->id)
                ->update(['is_default' => false]);

            $preset->update([
                'is_default' => true,
                'is_active' => true,
            ]);

            return $preset->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createTemplate(array $data, User $creator): DocumentTemplate
    {
        return DB::transaction(function () use ($data, $creator): DocumentTemplate {
            $isDefault = (bool) ($data['is_default'] ?? false);
            $isActive = (bool) ($data['is_active'] ?? true);

            if ($isDefault) {
                $isActive = true;
                DocumentTemplate::query()->where('is_default', true)->update(['is_default' => false]);
            } elseif (! DocumentTemplate::query()->where('is_default', true)->exists() && $isActive) {
                $isDefault = true;
            }

            $customLines = collect($data['custom_header_lines'] ?? [])
                ->map(fn ($line) => trim((string) $line))
                ->filter(fn ($line) => $line !== '')
                ->values()
                ->all();

            return DocumentTemplate::query()->create([
                'name' => trim($data['name']),
                'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
                'header_mode' => TemplateHeaderMode::from($data['header_mode']),
                'first_page_only' => true,
                'show_logo' => (bool) ($data['show_logo'] ?? true),
                'logo_position' => $data['logo_position'] ?? 'LEFT',
                'show_border_line' => (bool) ($data['show_border_line'] ?? true),
                'exam_title_label' => filled($data['exam_title_label'] ?? null) ? trim((string) $data['exam_title_label']) : null,
                'show_identity_box' => (bool) ($data['show_identity_box'] ?? true),
                'custom_header_lines' => $customLines,
                'footer_text' => filled($data['footer_text'] ?? null) ? trim((string) $data['footer_text']) : null,
                'show_page_numbers' => (bool) ($data['show_page_numbers'] ?? true),
                'is_default' => $isDefault,
                'is_active' => $isActive,
                'created_by' => $creator->id,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateTemplate(DocumentTemplate $template, array $data): DocumentTemplate
    {
        return DB::transaction(function () use ($template, $data): DocumentTemplate {
            $isDefault = (bool) ($data['is_default'] ?? $template->is_default);
            $isActive = (bool) ($data['is_active'] ?? $template->is_active);

            if ($isDefault) {
                $isActive = true;
                DocumentTemplate::query()
                    ->where('id', '!=', $template->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $customLines = collect($data['custom_header_lines'] ?? [])
                ->map(fn ($line) => trim((string) $line))
                ->filter(fn ($line) => $line !== '')
                ->values()
                ->all();

            $template->update([
                'name' => trim($data['name']),
                'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
                'header_mode' => TemplateHeaderMode::from($data['header_mode']),
                'first_page_only' => true,
                'show_logo' => (bool) ($data['show_logo'] ?? true),
                'logo_position' => $data['logo_position'] ?? 'LEFT',
                'show_border_line' => (bool) ($data['show_border_line'] ?? true),
                'exam_title_label' => filled($data['exam_title_label'] ?? null) ? trim((string) $data['exam_title_label']) : null,
                'show_identity_box' => (bool) ($data['show_identity_box'] ?? true),
                'custom_header_lines' => $customLines,
                'footer_text' => filled($data['footer_text'] ?? null) ? trim((string) $data['footer_text']) : null,
                'show_page_numbers' => (bool) ($data['show_page_numbers'] ?? true),
                'is_default' => $isDefault,
                'is_active' => $isActive,
            ]);

            return $template->refresh();
        });
    }

    public function setDefaultTemplate(DocumentTemplate $template): DocumentTemplate
    {
        return DB::transaction(function () use ($template): DocumentTemplate {
            DocumentTemplate::query()
                ->where('id', '!=', $template->id)
                ->update(['is_default' => false]);

            $template->update([
                'is_default' => true,
                'is_active' => true,
            ]);

            return $template->refresh();
        });
    }
}