<?php

namespace App\Services;

use App\Enums\ExamSemester;
use App\Models\DocumentProcessingConfig;
use App\Models\DocumentTemplate;
use App\Models\ExamDocument;
use App\Models\FormattingPreset;
use App\Models\SchoolProfile;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use RuntimeException;

class FormattingSnapshotBuilder
{
    /**
     * Membangun dan mengunci snapshot konfigurasi format & template kop untuk satu dokumen soal.
     */
    public function buildAndSave(
        ExamDocument $examDocument,
        FormattingPreset $preset,
        DocumentTemplate $template,
        ?User $actor = null
    ): DocumentProcessingConfig {
        $examDocument->loadMissing(['examPeriod', 'subject', 'schoolClass']);
        $schoolProfile = SchoolProfile::current() ?? SchoolProfile::query()->first();

        $logoPath = $schoolProfile?->getAttribute('logo_path');
        $logoDisk = $schoolProfile?->getAttribute('logo_disk')
            ?: (! empty($logoPath) ? 'local' : null);

        $rawSemester = $examDocument->examPeriod?->getAttribute('semester');
        $semesterEnum = $rawSemester instanceof ExamSemester
            ? $rawSemester
            : (is_string($rawSemester) ? ExamSemester::tryFrom($rawSemester) : null);
        $semesterLabel = $semesterEnum?->label()
            ?? (is_string($rawSemester) && $rawSemester !== '' ? $rawSemester : null);

        $examDateFormatted = $this->formatIndonesianDate($examDocument->getAttribute('exam_date'));
        $periodName = $examDocument->examPeriod?->getAttribute('name');

        $canonicalPayload = [
            'schema_version' => '1.0',
            'decisions' => [
                'first_page_only_header' => true, // D-016
                'preserve_original_source' => true,
                'preserve_question_substance' => true,
            ],
            'preset' => [
                'id' => $preset->id,
                'name' => $preset->name,
                'paper_code' => $preset->paper_code->value,
                'paper_width_mm' => (float) $preset->paper_width_mm,
                'paper_height_mm' => (float) $preset->paper_height_mm,
                'orientation' => $preset->orientation->value,
                'margin_top_mm' => (float) $preset->margin_top_mm,
                'margin_bottom_mm' => (float) $preset->margin_bottom_mm,
                'margin_left_mm' => (float) $preset->margin_left_mm,
                'margin_right_mm' => (float) $preset->margin_right_mm,
                'font_family' => $preset->font_family,
                'font_size_pt' => (float) $preset->font_size_pt,
                'line_spacing' => (float) $preset->line_spacing,
                'paragraph_spacing_before_pt' => (float) $preset->paragraph_spacing_before_pt,
                'paragraph_spacing_after_pt' => (float) $preset->paragraph_spacing_after_pt,
                'preserve_original_styles' => (bool) $preset->preserve_original_styles,
                'apply_to_existing_tables' => (bool) $preset->apply_to_existing_tables,
            ],
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'header_mode' => $template->header_mode->value,
                'first_page_only' => true,
                'show_school_logo' => (bool) $template->show_school_logo,
                'show_exam_identity_table' => (bool) $template->show_exam_identity_table,
                'header_title' => $template->header_title,
                'header_subtitle' => $template->header_subtitle,
                'instruction_text' => $template->instruction_text,
                'template_file_disk' => $template->template_file_disk,
                'template_file_path' => $template->template_file_path,
                'template_checksum' => $template->template_checksum,
            ],
            'school' => [
                'name' => $schoolProfile?->getAttribute('institution_name')
                    ?? $schoolProfile?->getAttribute('school_name')
                    ?? $schoolProfile?->getAttribute('name'),
                'foundation_name' => $schoolProfile?->getAttribute('foundation_name'),
                'npsn' => $schoolProfile?->getAttribute('npsn'),
                'address' => $schoolProfile?->getAttribute('address'),
                'phone' => $schoolProfile?->getAttribute('phone'),
                'email' => $schoolProfile?->getAttribute('email'),
                'website' => $schoolProfile?->getAttribute('website'),
                'headmaster_name' => $schoolProfile?->getAttribute('headmaster_name'),
                'headmaster_nip' => $schoolProfile?->getAttribute('headmaster_nip'),
                'header_lines' => $schoolProfile?->getAttribute('header_lines') ?? [],
                'logo_disk' => $logoDisk,
                'logo_path' => $logoPath,
            ],
            'document' => [
                'id' => $examDocument->id,
                'exam_period_id' => $examDocument->exam_period_id,
                'period_name' => $periodName,
                'exam_short_name' => $this->buildShortExamName($periodName, $template->header_title, $semesterLabel),
                'academic_year' => $examDocument->examPeriod?->getAttribute('academic_year'),
                'semester' => $semesterEnum?->value ?? $rawSemester,
                'semester_label' => $semesterLabel,
                'subject_id' => $examDocument->subject_id,
                'subject_name' => $examDocument->subject?->getAttribute('name'),
                'subject_code' => $examDocument->subject?->getAttribute('code'),
                'school_class_id' => $examDocument->school_class_id,
                'class_name' => $examDocument->schoolClass?->getAttribute('name'),
                'teacher_name' => $examDocument->getAttribute('teacher_name'),
                'title' => $examDocument->getAttribute('document_title')
                    ?? $examDocument->getAttribute('title')
                    ?? "Dokumen Soal #{$examDocument->id}",
                'exam_date' => $examDateFormatted,
                'duration_minutes' => $examDocument->getAttribute('duration_minutes'),
            ],
        ];

        $checksum = hash(
            'sha256',
            (string) json_encode($canonicalPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $snapshotPayload = array_merge($canonicalPayload, [
            'captured_at' => now()->toIso8601String(),
        ]);

        return DocumentProcessingConfig::query()->updateOrCreate(
            ['exam_document_id' => $examDocument->id],
            [
                'formatting_preset_id' => $preset->id,
                'document_template_id' => $template->id,
                'snapshot_payload' => $snapshotPayload,
                'snapshot_checksum' => $checksum,
                'configured_by' => $actor?->id,
                'configured_at' => now(),
            ]
        );
    }

    /**
     * Mengembalikan konfigurasi dokumen yang sudah tersimpan atau membuatnya otomatis dari preset & template default.
     */
    public function ensureForDocument(ExamDocument $examDocument, ?User $actor = null): DocumentProcessingConfig
    {
        $existing = DocumentProcessingConfig::query()
            ->where('exam_document_id', $examDocument->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $defaultPreset = FormattingPreset::defaultPreset();
        $defaultTemplate = DocumentTemplate::defaultTemplate();

        if ($defaultPreset === null || $defaultTemplate === null) {
            throw new RuntimeException('Preset format default atau template kop default belum tersedia.');
        }

        return $this->buildAndSave($examDocument, $defaultPreset, $defaultTemplate, $actor);
    }

    private function formatIndonesianDate(mixed $rawDate): ?string
    {
        if ($rawDate === null || $rawDate === '') {
            return null;
        }

        try {
            $date = $rawDate instanceof CarbonInterface
                ? $rawDate
                : Carbon::parse((string) $rawDate);

            $days = [
                0 => 'Minggu',
                1 => 'Senin',
                2 => 'Selasa',
                3 => 'Rabu',
                4 => 'Kamis',
                5 => 'Jumat',
                6 => 'Sabtu',
            ];

            $months = [
                1 => 'Januari',
                2 => 'Februari',
                3 => 'Maret',
                4 => 'April',
                5 => 'Mei',
                6 => 'Juni',
                7 => 'Juli',
                8 => 'Agustus',
                9 => 'September',
                10 => 'Oktober',
                11 => 'November',
                12 => 'Desember',
            ];

            $dayName = $days[$date->dayOfWeek] ?? '';
            $monthName = $months[$date->month] ?? '';

            return sprintf('%s, %02d %s %04d', $dayName, $date->day, $monthName, $date->year);
        } catch (\Throwable) {
            return is_string($rawDate) ? trim($rawDate) : null;
        }
    }

    private function buildShortExamName(?string $periodName, ?string $headerTitle, ?string $semesterLabel): string
    {
        $source = trim((string) ($periodName ?: $headerTitle ?: 'UJIAN'));

        if (preg_match('/\(([^)]+)\)/', $source, $matches)) {
            $abbr = trim($matches[1]);
            if ($semesterLabel && stripos($abbr, $semesterLabel) === false && stripos($source, $semesterLabel) !== false) {
                return "{$abbr} {$semesterLabel}";
            }

            return $abbr;
        }

        return $source;
    }
}