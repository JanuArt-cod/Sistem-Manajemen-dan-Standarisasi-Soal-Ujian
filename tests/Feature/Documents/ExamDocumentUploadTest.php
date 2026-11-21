<?php

namespace Tests\Feature\Documents;

use App\Enums\DocumentFileRole;
use App\Enums\DocumentStatus;
use App\Enums\ExamPeriodStatus;
use App\Enums\ExamSemester;
use App\Models\DocumentFile;
use App\Models\ExamPeriod;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ExamDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    private function createValidDocxUpload(string $filename = 'soal-matematika.docx'): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'simsoal_test_');
        $zip = new ZipArchive();
        $zip->open($tempPath, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>1. Berapakah hasil 2 + 2?</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();

        return new UploadedFile(
            $tempPath,
            $filename,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );
    }

    public function test_operator_can_upload_valid_docx_to_private_storage_and_download_it(): void
    {
        Storage::fake('local');
        $operator = User::factory()->operator()->create();
        $period = ExamPeriod::query()->create([
            'name' => 'PAS Ganjil 2026/2027',
            'academic_year' => '2026/2027',
            'semester' => ExamSemester::ODD,
            'status' => ExamPeriodStatus::ACTIVE,
            'created_by' => $operator->id,
        ]);
        $subject = Subject::query()->create(['name' => 'Matematika', 'code' => 'MTK']);
        $schoolClass = SchoolClass::query()->create(['name' => 'X TKJ 1', 'level' => 'X']);

        $docx = $this->createValidDocxUpload('Soal Matematika Kelas X.docx');

        $response = $this->actingAs($operator)->post('/exam-documents', [
            'exam_period_id' => $period->id,
            'items' => [
                [
                    'file' => $docx,
                    'subject_id' => $subject->id,
                    'school_class_id' => $schoolClass->id,
                    'teacher_name' => 'Budi Santoso, S.Pd.',
                    'duration_minutes' => 90,
                    'document_title' => 'PAS Matematika Kelas X',
                ],
            ],
        ]);

        $response->assertRedirect('/exam-documents');

        $this->assertDatabaseHas('exam_documents', [
            'exam_period_id' => $period->id,
            'teacher_name' => 'Budi Santoso, S.Pd.',
            'current_status' => DocumentStatus::UPLOADED->value,
        ]);

        $docFile = DocumentFile::query()->firstOrFail();
        $this->assertEquals(DocumentFileRole::SOURCE, $docFile->file_role);
        $this->assertNotEquals('Soal Matematika Kelas X.docx', basename($docFile->storage_path));
        $this->assertEquals(64, strlen($docFile->checksum_sha256));
        Storage::disk('local')->assertExists($docFile->storage_path);

        // Unduhan terotorisasi berhasil
        $this->actingAs($operator)
            ->get("/document-files/{$docFile->id}/download")
            ->assertStatus(200);

        // Tamu tidak login ditolak
        auth()->logout();
        $this->get("/document-files/{$docFile->id}/download")
            ->assertRedirect('/login');
    }

    public function test_fake_docx_file_is_rejected(): void
    {
        Storage::fake('local');
        $operator = User::factory()->operator()->create();
        $period = ExamPeriod::query()->create([
            'name' => 'PTS Genap 2026/2027',
            'academic_year' => '2026/2027',
            'semester' => ExamSemester::EVEN,
            'status' => ExamPeriodStatus::ACTIVE,
            'created_by' => $operator->id,
        ]);

        $fakeDocx = UploadedFile::fake()->createWithContent('palsu.docx', 'Ini hanya teks biasa bukan paket OOXML.');

        $response = $this->actingAs($operator)->from('/exam-documents/create')->post('/exam-documents', [
            'exam_period_id' => $period->id,
            'items' => [
                [
                    'file' => $fakeDocx,
                    'teacher_name' => 'Guru Uji',
                ],
            ],
        ]);

        $response->assertRedirect('/exam-documents/create');
        $response->assertSessionHasErrors('items.0.file');
        $this->assertDatabaseCount('exam_documents', 0);
        $this->assertDatabaseCount('document_files', 0);
    }

    public function test_upload_to_archived_period_is_rejected(): void
    {
        Storage::fake('local');
        $operator = User::factory()->operator()->create();
        $archivedPeriod = ExamPeriod::query()->create([
            'name' => 'Arsip Lama',
            'academic_year' => '2025/2026',
            'semester' => ExamSemester::ODD,
            'status' => ExamPeriodStatus::ARCHIVED,
            'created_by' => $operator->id,
        ]);

        $docx = $this->createValidDocxUpload();

        $response = $this->actingAs($operator)->from('/exam-documents/create')->post('/exam-documents', [
            'exam_period_id' => $archivedPeriod->id,
            'items' => [
                [
                    'file' => $docx,
                    'teacher_name' => 'Guru Uji',
                ],
            ],
        ]);

        $response->assertSessionHasErrors('exam_period_id');
        $this->assertDatabaseCount('exam_documents', 0);
    }
}