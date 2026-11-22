<?php

use App\Enums\PaperCode;
use App\Enums\PaperOrientation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formatting_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('paper_code', 20)->default(PaperCode::F4->value);
            $table->decimal('paper_width_mm', 6, 2)->default(215.00);
            $table->decimal('paper_height_mm', 6, 2)->default(330.00);
            $table->string('orientation', 20)->default(PaperOrientation::PORTRAIT->value);
            $table->decimal('margin_top_mm', 5, 2)->default(20.00);
            $table->decimal('margin_bottom_mm', 5, 2)->default(20.00);
            $table->decimal('margin_left_mm', 5, 2)->default(25.00);
            $table->decimal('margin_right_mm', 5, 2)->default(20.00);
            $table->string('font_family', 100)->default('Times New Roman');
            $table->decimal('font_size_pt', 4, 1)->default(12.0);
            $table->decimal('line_spacing', 3, 2)->default(1.00);
            $table->decimal('paragraph_spacing_before_pt', 4, 1)->default(0.0);
            $table->decimal('paragraph_spacing_after_pt', 4, 1)->default(4.0);
            $table->boolean('preserve_original_styles')->default(true);
            $table->boolean('apply_to_existing_tables')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'is_default']);
        });

        $now = now();

        DB::table('formatting_presets')->insert([
            'name' => 'Standar Ujian F4 (215 × 330 mm) — TNR 12 pt',
            'description' => 'Preset baku naskah ujian sekolah: kertas F4 Portrait, margin Atas 20 / Bawah 20 / Kiri 25 / Kanan 20 mm, font Times New Roman 12 pt, spasi baris 1.0.',
            'paper_code' => PaperCode::F4->value,
            'paper_width_mm' => 215.00,
            'paper_height_mm' => 330.00,
            'orientation' => PaperOrientation::PORTRAIT->value,
            'margin_top_mm' => 20.00,
            'margin_bottom_mm' => 20.00,
            'margin_left_mm' => 25.00,
            'margin_right_mm' => 20.00,
            'font_family' => 'Times New Roman',
            'font_size_pt' => 12.0,
            'line_spacing' => 1.00,
            'paragraph_spacing_before_pt' => 0.0,
            'paragraph_spacing_after_pt' => 4.0,
            'preserve_original_styles' => true,
            'apply_to_existing_tables' => false,
            'is_default' => true,
            'is_active' => true,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('formatting_presets');
    }
};