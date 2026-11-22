<?php

namespace Database\Seeders;

use App\Enums\PageOrientation;
use App\Enums\PaperSize;
use App\Enums\TemplateHeaderMode;
use App\Models\DocumentTemplate;
use App\Models\FormattingPreset;
use App\Models\User;
use Illuminate\Database\Seeder;

class DefaultPresetAndTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $creatorId = User::query()->orderBy('id')->value('id');

        FormattingPreset::query()->firstOrCreate(
            ['name' => 'Standar F4 Resmi (215 × 330 mm)'],
            [
                'description' => 'Preset bawaan SIMSOAL: Kertas F4 potret, margin 20/20/25/20 mm, Times New Roman 12 pt, spasi baris 1.0.',
                'paper_size' => PaperSize::F4,
                'orientation' => PageOrientation::PORTRAIT,
                'paper_width_mm' => 215.00,
                'paper_height_mm' => 330.00,
                'margin_top_mm' => 20.00,
                'margin_bottom_mm' => 20.00,
                'margin_left_mm' => 25.00,
                'margin_right_mm' => 20.00,
                'font_family' => 'Times New Roman',
                'font_size_pt' => 12.0,
                'line_spacing' => 1.00,
                'paragraph_spacing_before_pt' => 0.0,
                'paragraph_spacing_after_pt' => 0.0,
                'question_numbering_style' => 'KEEP_ORIGINAL',
                'option_numbering_style' => 'KEEP_ORIGINAL',
                'normalize_tables' => true,
                'normalize_images' => true,
                'is_default' => true,
                'is_active' => true,
                'created_by' => $creatorId,
            ]
        );

        DocumentTemplate::query()->firstOrCreate(
            ['name' => 'Kop Resmi Halaman Pertama + Identitas Ujian'],
            [
                'description' => 'Menyisipkan kop identitas sekolah dan kotak keterangan ujian hanya pada halaman pertama dokumen.',
                'header_mode' => TemplateHeaderMode::SCHOOL_PROFILE,
                'first_page_only' => true,
                'show_logo' => true,
                'logo_position' => 'LEFT',
                'show_border_line' => true,
                'exam_title_label' => null,
                'show_identity_box' => true,
                'custom_header_lines' => [],
                'footer_text' => null,
                'show_page_numbers' => true,
                'is_default' => true,
                'is_active' => true,
                'created_by' => $creatorId,
            ]
        );
    }
}