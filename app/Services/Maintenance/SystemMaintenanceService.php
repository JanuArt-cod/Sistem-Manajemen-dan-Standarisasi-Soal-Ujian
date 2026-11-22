<?php

namespace App\Services\Maintenance;

use App\Enums\PaperCode;
use App\Enums\PaperOrientation;
use App\Enums\TemplateHeaderMode;
use App\Models\DocumentFile;
use App\Models\DocumentProcessingConfig;
use App\Models\DocumentProcessingRun;
use App\Models\DocumentTemplate;
use App\Models\DocumentWarning;
use App\Models\ExamDocument;
use App\Models\ExamPeriod;
use App\Models\FormattingPreset;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SystemMaintenanceService
{
    public const DEFAULT_PRESET_NAME = 'Standar F4 Resmi (215 × 330 mm)';

    public const DEFAULT_TEMPLATE_NAME = 'Kop Resmi Halaman Pertama + Identitas Ujian';

    /**
     * Ringkasan jumlah data & pemakaian penyimpanan privat saat ini.
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        $totalBytes = (int) DocumentFile::query()->sum('size_bytes');

        return [
            'documents_count' => ExamDocument::query()->count(),
            'files_count' => DocumentFile::query()->count(),
            'runs_count' => DocumentProcessingRun::query()->count(),
            'storage_human_size' => $this->formatBytes($totalBytes),
            'periods_count' => ExamPeriod::query()->count(),
            'subjects_count' => Subject::query()->count(),
            'classes_count' => SchoolClass::query()->count(),
            'presets_count' => FormattingPreset::query()->count(),
            'templates_count' => DocumentTemplate::query()->count(),
            'users_count' => User::query()->count(),
        ];
    }

    /**
     * Pulihkan Preset Format & Template Kop standar baku SIMSOAL ke nilai default.
     *
     * @return array{removed_presets: int, removed_templates: int}
     */
    public function restoreDefaultPresetsAndTemplates(?int $actorId = null, bool $removeCustom = false): array
    {
        $creatorId = $actorId ?: User::query()->orderBy('id')->value('id');
        $removedPresets = 0;
        $removedTemplates = 0;
        $filesToDelete = [];

        DB::transaction(function () use (
            $creatorId,
            $removeCustom,
            &$removedPresets,
            &$removedTemplates,
            &$filesToDelete
        ): void {
            $defaultPreset = FormattingPreset::withTrashed()
                ->where('name', self::DEFAULT_PRESET_NAME)
                ->first();

            $presetAttributes = [
                'name' => self::DEFAULT_PRESET_NAME,
                'description' => 'Preset standar baku SIMSOAL: Kertas F4 potret (215 × 330 mm), margin 20/20/25/20 mm, Times New Roman 12 pt, spasi baris 1.0.',
                'paper_code' => PaperCode::F4,
                'paper_width_mm' => 215.0,
                'paper_height_mm' => 330.0,
                'orientation' => PaperOrientation::PORTRAIT,
                'margin_top_mm' => 20.0,
                'margin_bottom_mm' => 20.0,
                'margin_left_mm' => 25.0,
                'margin_right_mm' => 20.0,
                'font_family' => 'Times New Roman',
                'font_size_pt' => 12.0,
                'line_spacing' => 1.0,
                'paragraph_spacing_before_pt' => 0.0,
                'paragraph_spacing_after_pt' => 4.0,
                'preserve_original_styles' => true,
                'apply_to_existing_tables' => false,
                'is_active' => true,
                'created_by' => $creatorId,
            ];

            if ($defaultPreset) {
                if ($defaultPreset->trashed()) {
                    $defaultPreset->restore();
                }
                $defaultPreset->update($presetAttributes);
            } else {
                $defaultPreset = FormattingPreset::query()->create([
                    ...$presetAttributes,
                    'is_default' => false,
                ]);
            }

            $defaultPreset->markAsDefault();

            $defaultTemplate = DocumentTemplate::withTrashed()
                ->where('name', self::DEFAULT_TEMPLATE_NAME)
                ->first();

            $templateAttributes = [
                'name' => self::DEFAULT_TEMPLATE_NAME,
                'description' => 'Menyisipkan kop identitas resmi sekolah dan tabel identitas ujian hanya pada halaman pertama dokumen (Keputusan D-016).',
                'header_mode' => TemplateHeaderMode::STANDARD_SCHOOL,
                'first_page_only' => true,
                'show_school_logo' => true,
                'show_exam_identity_table' => true,
                'header_title' => null,
                'header_subtitle' => null,
                'instruction_text' => null,
                'template_file_disk' => null,
                'template_file_path' => null,
                'template_original_name' => null,
                'template_checksum' => null,
                'is_active' => true,
                'created_by' => $creatorId,
            ];

            if ($defaultTemplate) {
                if ($defaultTemplate->trashed()) {
                    $defaultTemplate->restore();
                }
                $defaultTemplate->update($templateAttributes);
            } else {
                $defaultTemplate = DocumentTemplate::query()->create([
                    ...$templateAttributes,
                    'is_default' => false,
                ]);
            }

            $defaultTemplate->markAsDefault();

            if ($removeCustom) {
                $customPresets = FormattingPreset::withTrashed()
                    ->where('id', '!=', $defaultPreset->id)
                    ->get();

                foreach ($customPresets as $preset) {
                    $isReferenced = DocumentProcessingConfig::query()
                        ->where('formatting_preset_id', $preset->id)
                        ->exists();

                    if (! $isReferenced) {
                        $preset->forceDelete();
                        $removedPresets++;
                    }
                }

                $customTemplates = DocumentTemplate::withTrashed()
                    ->where('id', '!=', $defaultTemplate->id)
                    ->get();

                foreach ($customTemplates as $template) {
                    $isReferenced = DocumentProcessingConfig::query()
                        ->where('document_template_id', $template->id)
                        ->exists();

                    if (! $isReferenced) {
                        if (filled($template->template_file_disk) && filled($template->template_file_path)) {
                            $filesToDelete[] = [
                                'disk' => $template->template_file_disk,
                                'path' => $template->template_file_path,
                            ];
                        }
                        $template->forceDelete();
                        $removedTemplates++;
                    }
                }
            }
        });

        foreach ($filesToDelete as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }

        return [
            'removed_presets' => $removedPresets,
            'removed_templates' => $removedTemplates,
        ];
    }

    /**
     * Hapus seluruh dokumen soal uji coba beserta file fisik sumber, hasil DOCX, dan PDF di storage privat.
     *
     * @return array{deleted_documents: int, deleted_files: int}
     */
    public function purgeTestDocumentsAndFiles(): array
    {
        $deletedDocuments = ExamDocument::query()->count();

        $trackedFiles = DocumentFile::query()
            ->get(['storage_disk', 'storage_path'])
            ->map(fn (DocumentFile $file): array => [
                'disk' => $file->storage_disk ?: 'local',
                'path' => $file->storage_path,
            ])
            ->filter(fn (array $item): bool => filled($item['path']))
            ->unique(fn (array $item): string => $item['disk'].'::'.$item['path'])
            ->values();

        DB::transaction(function (): void {
            DocumentWarning::query()->delete();
            DocumentFile::query()->delete();
            DocumentProcessingRun::query()->delete();
            DocumentProcessingConfig::query()->delete();
            ExamDocument::query()->delete();
        });

        $deletedFiles = 0;
        foreach ($trackedFiles as $item) {
            $disk = Storage::disk($item['disk']);
            if ($disk->exists($item['path'])) {
                $disk->delete($item['path']);
                $deletedFiles++;
            }
        }

        // Bersihkan sisa berkas yatim pada folder sources dan outputs di disk privat
        $localDisk = Storage::disk('local');
        foreach (['sources', 'outputs'] as $folder) {
            if ($localDisk->exists($folder)) {
                foreach ($localDisk->allFiles($folder) as $orphanPath) {
                    $localDisk->delete($orphanPath);
                    $deletedFiles++;
                }
            }
        }

        return [
            'deleted_documents' => $deletedDocuments,
            'deleted_files' => $deletedFiles,
        ];
    }

    /**
     * Hapus data master uji coba (periode ujian, mata pelajaran, rombongan kelas).
     *
     * @return array{deleted_periods: int, deleted_subjects: int, deleted_classes: int}
     */
    public function purgeTestMasterData(): array
    {
        if (ExamDocument::query()->exists()) {
            throw new RuntimeException('Kosongkan seluruh Dokumen Soal terlebih dahulu sebelum menghapus data Periode, Mapel, dan Kelas.');
        }

        $deletedPeriods = ExamPeriod::query()->count();
        $deletedSubjects = Subject::query()->count();
        $deletedClasses = SchoolClass::query()->count();

        DB::transaction(function (): void {
            ExamPeriod::query()->delete();
            Subject::query()->delete();
            SchoolClass::query()->delete();
        });

        return [
            'deleted_periods' => $deletedPeriods,
            'deleted_subjects' => $deletedSubjects,
            'deleted_classes' => $deletedClasses,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 2).' '.$units[$power];
    }
}