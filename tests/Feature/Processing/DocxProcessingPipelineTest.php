<?php

namespace Tests\Feature\Processing;

use App\Enums\DocumentFileRole;
use App\Enums\DocumentSourceFormat;
use App\Enums\DocumentStatus;
use App\Enums\ExamPeriodStatus;
use App\Enums\ExamSemester;
use App\Enums\ProcessingRunStatus;
use App\Enums\WarningSeverity;
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

class DocxProcessingPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function createFixtureDocxBytes(string $questionText = '1. Hitunglah hasil dari 12 + 8 = ...', bool $withTextbox = false): string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'simsoal_pipe_') . '.docx';

        $textboxXml = $withTextbox
            ? '<w:p><w:r><w:t><w:txbxContent><w:p><w:r><w:t>Catatan Kotak Melayang</w:t></w:r></w:p></w:txbxContent></w:t></w:r></w:p>'
            : '';

        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:body>'
            . '<w:p><w:r><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial"/><w:sz w:val="20"/></w:rPr>'
            . '<w:t>' . htmlspecialchars($questionText, ENT_XML1) . '</w:t></w:r></w:p>'
            . $textboxXml
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

    private function seedExamDocumentWithSource(
        User $actor,
        ExamPeriodStatus $periodStatus = ExamPeriodStatus::ACTIVE,
        bool $withTextbox = false
    ): ExamDocument {
        SchoolProfile::query()->updateOrCreate(
            ['id' => 1],
            [
                'institution_name' => 'SMK BINA INSAN MANDIRI',
                'foundation_name' => 'YAYASAN PENDIDIKAN ISLAM',
                'address' => 'Jl. Raya Bogor - Sukabumi',
            ]
        );

        $period = ExamPeriod::query()->create([
            'name' => 'SAS Ganjil 2026/2027',
            'academic_year' => '2026/2027',
            'semester' => ExamSemester::cases()[0]->value,
            'status' => $periodStatus,
            'created_by' => $actor->id,
        ]);

        $subject = Subject::query()->create([
            'code' => 'INF-10',
            'name' => 'Informatika',
            'is_active' => true,
        ]);

        $schoolClass = SchoolClass::query()->create([
            'name' => 'X TJKT 1',
            'level' => '10',
            'is_active' => true,
        ]);

        $doc = ExamDocument::query()->create([
            'exam_period_id' => $period->id,
            'subject_id' => $subject->id,
            'school_class_id' => $schoolClass->id,
            'teacher_name' => 'Muhammad Januar Ramdhani, S.Kom.',
            'document_title' => 'Soal SAS Informatika Kelas X',
            'source_format' => DocumentSourceFormat::DOCX,
            'current_status' => DocumentStatus::UPLOADED,
            'uploaded_by' => $actor->id,
        ]);

        $docxBytes = $this->createFixtureDocxBytes('1. Berapakah hasil konversi biner 1010 ke desimal?', $withTextbox);
        $checksum = hash('sha256', $docxBytes);
        $storagePath = "exam-documents/{$period->id}/sources/source_{$doc->id}.docx";

        Storage::disk('local')->put($storagePath, $docxBytes);

        DocumentFile::query()->create([
            'exam_document_id' => $doc->id,
            'file_role' => DocumentFileRole::SOURCE,
            'original_filename' => 'soal_informatika_x.docx',
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

    public function test_operator_can_process_docx_end_to_end_and_preserve_immutable_source(): void
    {
        Storage::fake('local');
        $operator = User::factory()->operator()->create();
        $doc = $this->seedExamDocumentWithSource($operator, ExamPeriodStatus::ACTIVE, false);

        $originalSourcePath = $doc->sourceFile->storage_path;
        $originalSourceHash = $doc->sourceFile->checksum_sha256;

        $this->actingAs($operator)
            ->post("/exam-documents/{$doc->id}/process")
            ->assertRedirect("/exam-documents/{$doc->id}");

        $doc->refresh();
        $this->assertEquals(DocumentStatus::PROCESSED, $doc->current_status);

        $run = DocumentProcessingRun::query()->where('exam_document_id', $doc->id)->firstOrFail();
        $this->assertEquals(ProcessingRunStatus::COMPLETED, $run->status);
        $this->assertEquals('simsoal-docx-engine', $run->engine_name);
        $this->assertFalse($run->needs_manual_review);
        $this->assertNotNull($run->output_docx_checksum_sha256);

        // Pastikan file sumber asli tetap utuh dan checksum tidak berubah
        $currentSourceHash = hash('sha256', (string) Storage::disk('local')->get($originalSourcePath));
        $this->assertEquals($originalSourceHash, $currentSourceHash);

        // Pastikan file output baru tersimpan di disk privat dengan peran OUTPUT_DOCX
        $outputFile = $doc->outputDocxFile()->firstOrFail();
        $this->assertEquals(DocumentFileRole::OUTPUT_DOCX, $outputFile->file_role);
        $this->assertTrue($outputFile->is_current);
        Storage::disk('local')->assertExists($outputFile->storage_path);

        // Verifikasi isi dokumen hasil berstandar F4 (215 x 330 mm -> 12189 x 18709 twips) & kop halaman pertama
        $tmpVerify = tempnam(sys_get_temp_dir(), 'simsoal_verify_') . '.docx';
        file_put_contents($tmpVerify, (string) Storage::disk('local')->get($outputFile->storage_path));

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tmpVerify) === true);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmpVerify);

        $this->assertStringContainsString('SMK BINA INSAN MANDIRI', $xml);
        $this->assertStringContainsString('1. Berapakah hasil konversi biner 1010 ke desimal?', $xml);
        $this->assertStringContainsString('Times New Roman', $xml);
        $this->assertStringContainsString('w:w="12189"', $xml);
        $this->assertStringContainsString('w:h="18709"', $xml);

        // Operator dapat mengunduh file output DOCX melalui endpoint terotorisasi
        $this->actingAs($operator)
            ->get("/document-files/{$outputFile->id}/download")
            ->assertStatus(200);
    }

    public function test_document_with_textbox_flags_needs_manual_review_and_saves_warning(): void
    {
        Storage::fake('local');
        $operator = User::factory()->operator()->create();
        $doc = $this->seedExamDocumentWithSource($operator, ExamPeriodStatus::ACTIVE, true);

        $this->actingAs($operator)
            ->post("/exam-documents/{$doc->id}/process")
            ->assertRedirect("/exam-documents/{$doc->id}");

        $doc->refresh();
        $this->assertEquals(DocumentStatus::NEEDS_REVIEW, $doc->current_status);

        $run = DocumentProcessingRun::query()->where('exam_document_id', $doc->id)->firstOrFail();
        $this->assertEquals(ProcessingRunStatus::COMPLETED_WITH_WARNINGS, $run->status);
        $this->assertTrue($run->needs_manual_review);

        $warning = $run->warnings()->firstOrFail();
        $this->assertEquals('TEXTBOX_OR_FLOATING_SHAPE', $warning->code);
        $this->assertEquals(WarningSeverity::REVIEW_REQUIRED, $warning->severity);
    }

    public function test_archived_period_document_cannot_be_processed_and_guest_is_blocked(): void
    {
        Storage::fake('local');
        $operator = User::factory()->operator()->create();
        $doc = $this->seedExamDocumentWithSource($operator, ExamPeriodStatus::ARCHIVED, false);

        $this->post("/exam-documents/{$doc->id}/process")
            ->assertRedirect('/login');

        $this->actingAs($operator)
            ->from("/exam-documents/{$doc->id}")
            ->post("/exam-documents/{$doc->id}/process")
            ->assertRedirect("/exam-documents/{$doc->id}")
            ->assertSessionHas('error');

        $this->assertEquals(0, DocumentProcessingRun::query()->count());
    }
}