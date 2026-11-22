<?php

namespace Tests\Feature\Processing;

use App\Enums\DocumentFileRole;
use App\Enums\DocumentSourceFormat;
use App\Enums\DocumentStatus;
use App\Enums\ExamPeriodStatus;
use App\Enums\ExamSemester;
use App\Enums\ProcessingRunStatus;
use App\Models\DocumentFile;
use App\Models\DocumentProcessingRun;
use App\Models\ExamDocument;
use App\Models\ExamPeriod;
use App\Models\SchoolClass;
use App\Models\SchoolProfile;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class PdfExportPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function createFixtureDocxBytes(string $questionText = '1. Jelaskan fungsi router pada jaringan komputer!'): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'simsoal_pdf_') . '.docx';

        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>'
            . '<w:p><w:r><w:t>' . htmlspecialchars($questionText, ENT_XML1) . '</w:t></w:r></w:p>'
            . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/></w:sectPr>'
            . '</w:body></w:document>';

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>';

        $zip = new ZipArchive();
        $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();

        $bytes = (string) file_get_contents($tmpPath);
        @unlink($tmpPath);

        return $bytes;
    }

    private function seedDocument(User $actor): ExamDocument
    {
        SchoolProfile::query()->updateOrCreate(
            ['id' => 1],
            [
                'institution_name' => 'SMK BINA INSAN MANDIRI',
                'foundation_name' => 'YAYASAN PENDIDIKAN ISLAM',
                'address' => 'Jl. Raya Bogor - Sukabumi',
            ]
        );

        $period = ExamPeriod::query()->create([
            'name' => 'SAS Genap 2026/2027',
            'academic_year' => '2026/2027',
            'semester' => ExamSemester::cases()[0]->value,
            'status' => ExamPeriodStatus::ACTIVE,
            'created_by' => $actor->id,
        ]);

        $subject = Subject::query()->create([
            'code' => 'TJKT-11',
            'name' => 'Administrasi Infrastruktur Jaringan',
            'is_active' => true,
        ]);

        $schoolClass = SchoolClass::query()->create([
            'name' => 'XI TJKT 1',
            'level' => '11',
            'is_active' => true,
        ]);

        $doc = ExamDocument::query()->create([
            'exam_period_id' => $period->id,
            'subject_id' => $subject->id,
            'school_class_id' => $schoolClass->id,
            'teacher_name' => 'Muhammad Januar Ramdhani, S.Kom.',
            'document_title' => 'Soal SAS AIJ Kelas XI',
            'source_format' => DocumentSourceFormat::DOCX,
            'current_status' => DocumentStatus::UPLOADED,
            'uploaded_by' => $actor->id,
        ]);

        $docxBytes = $this->createFixtureDocxBytes();
        $checksum = hash('sha256', $docxBytes);
        $storagePath = "exam-documents/{$period->id}/sources/source_{$doc->id}.docx";

        Storage::disk('local')->put($storagePath, $docxBytes);

        DocumentFile::query()->create([
            'exam_document_id' => $doc->id,
            'file_role' => DocumentFileRole::SOURCE,
            'original_filename' => 'soal_aij_xi.docx',
            'storage_disk' => 'local',
            'storage_path' => $storagePath,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'extension' => 'docx',
            'size_bytes' => strlen($docxBytes),
            'checksum_sha256' => $checksum,
            'is_current' => true,
            'created_by' => $actor->id,
        ]);

        return $doc->fresh(['sourceFile']);
    }

    public function test_t13_standardization_exports_valid_pdf_via_libreoffice(): void
    {
        $soffice = (string) config('simsoal.libreoffice_binary');
        if ($soffice === '' || ! is_file($soffice)) {
            $this->markTestSkipped('LibreOffice binary tidak tersedia pada lingkungan pengujian ini.');
        }

        Storage::fake('local');
        $operator = User::factory()->operator()->create();
        $doc = $this->seedDocument($operator);

        $this->actingAs($operator)
            ->post("/exam-documents/{$doc->id}/process")
            ->assertRedirect("/exam-documents/{$doc->id}");

        $doc->refresh();
        $this->assertEquals(DocumentStatus::PROCESSED, $doc->current_status);

        $run = DocumentProcessingRun::query()->where('exam_document_id', $doc->id)->firstOrFail();
        $this->assertEquals(ProcessingRunStatus::COMPLETED, $run->status);
        $this->assertEquals('COMPLETED', $run->pdf_status);
        $this->assertNotNull($run->libreoffice_version);
        $this->assertNotNull($run->output_pdf_checksum_sha256);

        $pdfFile = $doc->outputPdfFile()->firstOrFail();
        $this->assertEquals(DocumentFileRole::OUTPUT_PDF, $pdfFile->file_role);
        $this->assertEquals('application/pdf', $pdfFile->mime_type);
        $this->assertTrue($pdfFile->is_current);
        Storage::disk('local')->assertExists($pdfFile->storage_path);

        $pdfBytes = (string) Storage::disk('local')->get($pdfFile->storage_path);
        $this->assertTrue(str_starts_with($pdfBytes, '%PDF-'));

        $this->actingAs($operator)
            ->get("/document-files/{$pdfFile->id}/download")
            ->assertStatus(200);
    }

    public function test_t14_and_t17_libreoffice_failure_preserves_docx_with_pdf_failed_and_allows_standalone_pdf_retry(): void
    {
        $realSoffice = (string) config('simsoal.libreoffice_binary');
        Storage::fake('local');
        $operator = User::factory()->operator()->create();
        $doc = $this->seedDocument($operator);

        // Simulasikan LibreOffice gagal / tidak ditemukan
        config(['simsoal.libreoffice_binary' => 'C:\\invalid\\nonexistent\\soffice.exe']);

        $this->actingAs($operator)
            ->post("/exam-documents/{$doc->id}/process")
            ->assertRedirect("/exam-documents/{$doc->id}");

        $doc->refresh();
        $this->assertEquals(DocumentStatus::PDF_FAILED, $doc->current_status);

        $firstRun = DocumentProcessingRun::query()->where('exam_document_id', $doc->id)->firstOrFail();
        $this->assertEquals(ProcessingRunStatus::COMPLETED_WITH_WARNINGS, $firstRun->status);
        $this->assertEquals('FAILED', $firstRun->pdf_status);
        $this->assertNotNull($firstRun->pdf_error_message);
        $this->assertStringNotContainsString('C:\\invalid', $firstRun->pdf_error_message);

        // Pastikan hasil DOCX tetap tersedia dan dapat diunduh
        $docxFile = $doc->outputDocxFile()->firstOrFail();
        Storage::disk('local')->assertExists($docxFile->storage_path);
        $this->assertNull($doc->outputPdfFile()->first());

        $this->actingAs($operator)
            ->get("/document-files/{$docxFile->id}/download")
            ->assertStatus(200);

        // Jika LibreOffice tersedia di sistem, uji ekspor ulang PDF mandiri (T-17)
        if ($realSoffice !== '' && is_file($realSoffice)) {
            config(['simsoal.libreoffice_binary' => $realSoffice]);

            $this->actingAs($operator)
                ->post("/exam-documents/{$doc->id}/export-pdf")
                ->assertRedirect("/exam-documents/{$doc->id}");

            $doc->refresh();
            $this->assertEquals(DocumentStatus::PROCESSED, $doc->current_status);
            $this->assertNotNull($doc->outputPdfFile()->first());
            $this->assertEquals(2, DocumentProcessingRun::query()->where('exam_document_id', $doc->id)->count());
        }
    }
}