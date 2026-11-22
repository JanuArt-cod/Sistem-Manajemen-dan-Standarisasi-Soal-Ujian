<?php

namespace App\Services\Formatting;

use App\Enums\TemplateHeaderMode;
use App\Models\DocumentProcessingConfig;
use App\Models\DocumentTemplate;
use App\Models\ExamDocument;
use App\Models\FormattingPreset;
use App\Models\SchoolProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DocumentConfigurationService
{
    /**
     * Menyimpan snapshot konfigurasi pemrosesan yang immutable untuk satu dokumen soal.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function saveImmutableConfig(
        ExamDocument $document,
        FormattingPreset $preset,
        DocumentTemplate $template,
        array $overrides,
        User $user
    ): DocumentProcessingConfig {
        return DB::transaction(function () use ($document, $preset, $template, $overrides, $user): DocumentProcessingConfig {
            $document->loadMissing([
                'examPeriod:id,name,academic_year,semester',
                'subject:id,name,code',
                'schoolClass:id,name,level',
            ]);

            $schoolProfile = SchoolProfile::current();
            $presetSnapshot = $preset->toSnapshotArray();
            $templateSnapshot = $template->toSnapshotArray();

            $insertHeader = array_key_exists('insert_first_page_header', $overrides)
                ? (bool) $overrides['insert_first_page_header']
                : ($template->header_mode !== TemplateHeaderMode::NONE);

            $showIdentityBox = array_key_exists('show_identity_box', $overrides)
                ? (bool) $overrides['show_identity_box']
                : (bool) $template->show_identity_box;

            $examHeading = filled($overrides['custom_exam_heading'] ?? null)
                ? trim((string) $overrides['custom_exam_heading'])
                : ($template->exam_title_label ?: $document->examPeriod?->name ?: 'UJIAN SEKOLAH');

            $resolvedPayload = [
                'page' => [
                    'paper_size' => $presetSnapshot['paper_size'],
                    'orientation' => $presetSnapshot['orientation'],
                    'width_mm' => $presetSnapshot['paper_width_mm'],
                    'height_mm' => $presetSnapshot['paper_height_mm'],
                    'margins_mm' => [
                        'top' => $presetSnapshot['margin_top_mm'],
                        'bottom' => $presetSnapshot['margin_bottom_mm'],
                        'left' => $presetSnapshot['margin_left_mm'],
                        'right' => $presetSnapshot['margin_right_mm'],
                    ],
                ],
                'typography' => [
                    'font_family' => $presetSnapshot['font_family'],
                    'font_size_pt' => $presetSnapshot['font_size_pt'],
                    'line_spacing' => $presetSnapshot['line_spacing'],
                    'paragraph_spacing_before_pt' => $presetSnapshot['paragraph_spacing_before_pt'],
                    'paragraph_spacing_after_pt' => $presetSnapshot['paragraph_spacing_after_pt'],
                ],
                'preservation' => [
                    'preserve_question_substance' => true,
                    'question_numbering_style' => $presetSnapshot['question_numbering_style'],
                    'option_numbering_style' => $presetSnapshot['option_numbering_style'],
                    'normalize_tables' => $presetSnapshot['normalize_tables'],
                    'normalize_images' => $presetSnapshot['normalize_images'],
                ],
                'first_page_header' => [
                    'enabled' => $insertHeader,
                    'first_page_only' => true,
                    'header_mode' => $insertHeader ? $templateSnapshot['header_mode'] : TemplateHeaderMode::NONE->value,
                    'show_logo' => $templateSnapshot['show_logo'],
                    'logo_position' => $templateSnapshot['logo_position'],
                    'show_border_line' => $templateSnapshot['show_border_line'],
                    'exam_heading' => $examHeading,
                    'show_identity_box' => $showIdentityBox,
                    'school_profile' => $schoolProfile ? [
                        'institution_name' => $schoolProfile->institution_name,
                        'foundation_name' => $schoolProfile->foundation_name,
                        'address' => $schoolProfile->address,
                        'phone' => $schoolProfile->phone,
                        'email' => $schoolProfile->email,
                        'header_lines' => $schoolProfile->header_lines ?? [],
                        'logo_path' => $schoolProfile->logo_path,
                    ] : null,
                    'custom_header_lines' => $templateSnapshot['custom_header_lines'],
                ],
                'document_metadata' => [
                    'document_title' => $document->document_title,
                    'period_name' => $document->examPeriod?->name,
                    'academic_year' => $document->examPeriod?->academic_year,
                    'subject_name' => $document->subject?->name ?? '-',
                    'class_name' => $document->schoolClass?->name ?? '-',
                    'teacher_name' => $document->teacher_name,
                    'exam_date' => $document->exam_date?->format('Y-m-d'),
                    'duration_minutes' => $document->duration_minutes,
                ],
            ];

            DocumentProcessingConfig::query()
                ->where('exam_document_id', $document->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            return DocumentProcessingConfig::query()->create([
                'exam_document_id' => $document->id,
                'formatting_preset_id' => $preset->id,
                'document_template_id' => $template->id,
                'preset_snapshot' => $presetSnapshot,
                'template_snapshot' => $templateSnapshot,
                'overrides' => [
                    'insert_first_page_header' => $insertHeader,
                    'show_identity_box' => $showIdentityBox,
                    'custom_exam_heading' => filled($overrides['custom_exam_heading'] ?? null)
                        ? trim((string) $overrides['custom_exam_heading'])
                        : null,
                ],
                'resolved_payload' => $resolvedPayload,
                'is_current' => true,
                'configured_by' => $user->id,
            ]);
        });
    }
}