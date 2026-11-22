<?php

use App\Enums\TemplateHeaderMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('header_mode', 30)->default(TemplateHeaderMode::STANDARD_SCHOOL->value);
            $table->boolean('first_page_only')->default(true);
            $table->boolean('show_school_logo')->default(true);
            $table->boolean('show_exam_identity_table')->default(true);
            $table->string('header_title', 150)->nullable();
            $table->string('header_subtitle', 200)->nullable();
            $table->text('instruction_text')->nullable();
            $table->string('template_file_disk', 30)->nullable();
            $table->string('template_file_path', 255)->nullable();
            $table->string('template_original_name', 255)->nullable();
            $table->string('template_checksum', 64)->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'is_default']);
        });

        $now = now();

        DB::table('document_templates')->insert([
            [
                'name' => 'Kop Standar Ujian Sekolah (Halaman Pertama)',
                'description' => 'Menyusun kop resmi dari Profil Sekolah beserta tabel identitas ujian khusus pada halaman pertama dokumen (Keputusan D-016).',
                'header_mode' => TemplateHeaderMode::STANDARD_SCHOOL->value,
                'first_page_only' => true,
                'show_school_logo' => true,
                'show_exam_identity_table' => true,
                'header_title' => null,
                'header_subtitle' => null,
                'instruction_text' => 'Berdoalah sebelum mengerjakan soal. Periksa kembali identitas dan jawaban Anda sebelum dikumpulkan.',
                'template_file_disk' => null,
                'template_file_path' => null,
                'template_original_name' => null,
                'template_checksum' => null,
                'is_default' => true,
                'is_active' => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Tanpa Kop Tambahan (Pertahankan Kepala Naskah Asli)',
                'description' => 'Tidak menyisipkan blok kop baru; hanya menerapkan standarisasi ukuran kertas, margin, dan tipografi naskah.',
                'header_mode' => TemplateHeaderMode::NONE->value,
                'first_page_only' => true,
                'show_school_logo' => false,
                'show_exam_identity_table' => false,
                'header_title' => null,
                'header_subtitle' => null,
                'instruction_text' => null,
                'template_file_disk' => null,
                'template_file_path' => null,
                'template_original_name' => null,
                'template_checksum' => null,
                'is_default' => false,
                'is_active' => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        Schema::create('document_processing_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_document_id')->unique()->constrained('exam_documents')->cascadeOnDelete();
            $table->foreignId('formatting_preset_id')->nullable()->constrained('formatting_presets')->nullOnDelete();
            $table->foreignId('document_template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->json('snapshot_payload');
            $table->string('snapshot_checksum', 64);
            $table->foreignId('configured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('configured_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_processing_configs');
        Schema::dropIfExists('document_templates');
    }
};