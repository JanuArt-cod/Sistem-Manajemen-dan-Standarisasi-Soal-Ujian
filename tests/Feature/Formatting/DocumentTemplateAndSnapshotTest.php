<?php

namespace Tests\Feature\Formatting;

use App\Enums\DocumentStatus;
use App\Enums\ExamPeriodStatus;
use App\Enums\ExamSemester;
use App\Enums\TemplateHeaderMode;
use App\Models\DocumentProcessingConfig;
use App\Models\DocumentTemplate;
use App\Models\ExamDocument;
use App\Models\ExamPeriod;
use App\Models\FormattingPreset;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class DocumentTemplateAndSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function createValidDocxUploadedFile(string $filename = 'kop-resmi.docx'): UploadedFile
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'simsoal_tpl_') . '.docx';

        $zip = new ZipArchive();
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>KOP SEKOLAH</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        return new UploadedFile(
            $tmpPath,
            $filename,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
    }

    public function test_default_first_page_only_template_exists_after_migration(): void
    {
        $template = DocumentTemplate::defaultTemplate();

        $this->assertNotNull($template);
        $this->assertEquals(TemplateHeaderMode::STANDARD_SCHOOL, $template->header_mode);
        $this->assertTrue($template->first_page_only);
        $this->assertTrue($template->is_default);
        $this->assertTrue($template->is_active);
    }

    public function test_admin_can_upload_valid_custom_docx_template_and_rejects_fake_docx(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

        $fakeFile = UploadedFile::fake()->createWithContent('kop-palsu.docx', 'Bukan file ZIP atau DOCX');

        $this->actingAs($admin)
            ->from('/document-templates')
            ->post('/document-templates', [
                'name' => 'Kop Palsu',
                'header_mode' => TemplateHeaderMode::CUSTOM_DOCX->value,
                'first_page_only' => true,
                'template_file' => $fakeFile,
            ])
            ->assertSessionHasErrors('template_file');

        $validDocx = $this->createValidDocxUploadedFile('kop-yaspisa.docx');

        $this->actingAs($admin)
            ->post('/document-templates', [
                'name' => 'Kop Khusus Yaspisa',
                'description' => 'Template kop resmi Yayasan',
                'header_mode' => TemplateHeaderMode::CUSTOM_DOCX->value,
                'first_page_only' => true,
                'template_file' => $validDocx,
                'is_default' => true,
                'is_active' => true,
            ])
            ->assertRedirect('/document-templates');

        $created = DocumentTemplate::query()->where('name', 'Kop Khusus Yaspisa')->firstOrFail();

        $this->assertTrue($created->first_page_only);
        $this->assertTrue($created->is_default);
        $this->assertEquals('local', $created->template_file_disk);
        $this->assertNotNull($created->template_checksum);
        Storage::disk('local')->assertExists($created->template_file_path);

        $this->actingAs($admin)
            ->get("/document-templates/{$created->id}/download")
            ->assertStatus(200);
    }

    public function test_decision_d016_blocks_multi_page_header_template(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from('/document-templates')
            ->post('/document-templates', [
                'name' => 'Kop Semua Halaman',
                'header_mode' => TemplateHeaderMode::STANDARD_SCHOOL->value,
                'first_page_only' => false,
            ])
            ->assertSessionHasErrors('first_page_only');
    }

    public function test_operator_can_lock_document_processing_snapshot_but_cannot_manage_templates(): void
    {
        $operator = User::factory()->operator()->create();
        $preset = FormattingPreset::defaultPreset();
        $template = DocumentTemplate::defaultTemplate();

        $this->actingAs($operator)->get('/document-templates')->assertStatus(200);

        $this->actingAs($operator)
            ->post('/document-templates', [
                'name' => 'Template Tidak Sah',
                'header_mode' => TemplateHeaderMode::NONE->value,
            ])
            ->assertStatus(403);

        $periodAttributes = [
            'name' => 'SAS Ganjil 2026/2027',
            'academic_year' => '2026/2027',
            'semester' => ExamSemester::cases()[0]->value,
            'status' => enum_exists(ExamPeriodStatus::class) ? ExamPeriodStatus::cases()[0]->value : 'ACTIVE',
        ];
        if (Schema::hasColumn('exam_periods', 'created_by')) {
            $periodAttributes['created_by'] = $operator->id;
        }
        $period = ExamPeriod::query()->create($periodAttributes);

        $subject = Subject::query()->create([
            'code' => 'TJKT-01',
            'name' => 'Administrasi Infrastruktur Jaringan',
            'is_active' => true,
        ]);

        $schoolClass = SchoolClass::query()->create([
            'name' => 'XI TJKT 1',
            'grade_level' => 11,
            'is_active' => true,
        ]);

        $docAttributes = [
            'exam_period_id' => $period->id,
            'subject_id' => $subject->id,
            'school_class_id' => $schoolClass->id,
            'teacher_name' => 'Ahmad Fauzi, S.Kom.',
            'title' => 'Naskah Ujian AIJ Kelas XI',
            'status' => enum_exists(DocumentStatus::class) ? DocumentStatus::cases()[0]->value : 'UPLOADED',
        ];
        if (Schema::hasColumn('exam_documents', 'uploaded_by')) {
            $docAttributes['uploaded_by'] = $operator->id;
        }
        if (Schema::hasColumn('exam_documents', 'created_by')) {
            $docAttributes['created_by'] = $operator->id;
        }
        $examDocument = ExamDocument::query()->create($docAttributes);

        $this->actingAs($operator)
            ->put("/exam-documents/{$examDocument->id}/processing-config", [
                'formatting_preset_id' => $preset->id,
                'document_template_id' => $template->id,
            ])
            ->assertRedirect("/exam-documents/{$examDocument->id}");

        $config = DocumentProcessingConfig::query()
            ->where('exam_document_id', $examDocument->id)
            ->firstOrFail();

        $this->assertEquals($preset->id, $config->formatting_preset_id);
        $this->assertEquals($template->id, $config->document_template_id);
        $this->assertEquals(64, strlen($config->snapshot_checksum));
        $this->assertEquals('F4', $config->snapshot_payload['preset']['paper_code']);
        $this->assertEquals(215.0, $config->snapshot_payload['preset']['paper_width_mm']);
        $this->assertEquals(330.0, $config->snapshot_payload['preset']['paper_height_mm']);
        $this->assertTrue($config->snapshot_payload['template']['first_page_only']);
        $this->assertEquals('Ahmad Fauzi, S.Kom.', $config->snapshot_payload['document']['teacher_name']);
    }

    public function test_active_default_template_cannot_be_deactivated(): void
    {
        $admin = User::factory()->admin()->create();
        $template = DocumentTemplate::defaultTemplate();

        $this->actingAs($admin)
            ->from('/document-templates')
            ->put("/document-templates/{$template->id}", [
                'name' => $template->name,
                'header_mode' => $template->header_mode->value,
                'first_page_only' => true,
                'is_active' => false,
            ])
            ->assertSessionHasErrors('is_active');
    }
}