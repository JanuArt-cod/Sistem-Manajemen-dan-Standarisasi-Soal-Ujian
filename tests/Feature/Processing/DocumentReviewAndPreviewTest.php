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
use App\Models\DocumentWarning;
use App\Models\ExamDocument;
use App\Models\ExamPeriod;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentReviewAndPreviewTest extends TestCase
{
    use RefreshDatabase;

    private function seedProcessedDocumentWithArtifacts(
        User $actor,
        bool $withCriticalWarning = false,
        ExamPeriodStatus $periodStatus = ExamPeriodStatus::ACTIVE
    ): array {
        $period = ExamPeriod::query()->create([
            'name' => 'SAS Ganjil 2026/2027',
            'academic_year' => '2026/2027',
            'semester' => ExamSemester::cases()[0]->value,
            'status' => $periodStatus,
            'created_by' => $actor->id,
        ]);

        $subject = Subject::query()->firstOrCreate(
            ['name' => 'Matematika'],
            [
                'code' => 'MAT-12',
                'is_active' => true,
            ]
        );

        $schoolClass = SchoolClass::query()->firstOrCreate(
            ['name' => 'XII TKJ 1'],
            [
                'level' => '12',
                'is_active' => true,
            ]
        );

        $doc = ExamDocument::query()->create([
            'exam_period_id' => $period->id,
            'subject_id' => $subject->id,
            'school_class_id' => $schoolClass->id,
            'teacher_name' => 'Muhammad Januar Ramdhani, S.Kom.',
            'document_title' => 'Soal SAS Matematika XII',
            'source_format' => DocumentSourceFormat::DOCX,
            'current_status' => $withCriticalWarning ? DocumentStatus::NEEDS_REVIEW : DocumentStatus::PROCESSED,
            'uploaded_by' => $actor->id,
        ]);

        $docxBytes = 'PK-DUMMY-DOCX-OUTPUT';
        $docxPath = "exam-documents/{$period->id}/outputs/std_{$doc->id}.docx";
        Storage::disk('local')->put($docxPath, $docxBytes);

        $docxFile = DocumentFile::query()->create([
            'exam_document_id' => $doc->id,
            'file_role' => DocumentFileRole::OUTPUT_DOCX,
            'original_filename' => 'STD_soal_matematika_xii.docx',
            'storage_disk' => 'local',
            'storage_path' => $docxPath,
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'extension' => 'docx',
            'size_bytes' => strlen($docxBytes),
            'checksum_sha256' => hash('sha256', $docxBytes),
            'is_current' => true,
            'created_by' => $actor->id,
        ]);

        $pdfBytes = "%PDF-1.4\n%SIMSOAL TEST PDF\n%%EOF";
        $pdfPath = "exam-documents/{$period->id}/outputs/std_{$doc->id}.pdf";
        Storage::disk('local')->put($pdfPath, $pdfBytes);

        $pdfFile = DocumentFile::query()->create([
            'exam_document_id' => $doc->id,
            'file_role' => DocumentFileRole::OUTPUT_PDF,
            'original_filename' => 'STD_soal_matematika_xii.pdf',
            'storage_disk' => 'local',
            'storage_path' => $pdfPath,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => strlen($pdfBytes),
            'checksum_sha256' => hash('sha256', $pdfBytes),
            'is_current' => true,
            'created_by' => $actor->id,
        ]);

        $run = DocumentProcessingRun::query()->create([
            'exam_document_id' => $doc->id,
            'triggered_by' => $actor->id,
            'target_type' => 'DOCX',
            'status' => $withCriticalWarning ? ProcessingRunStatus::COMPLETED_WITH_WARNINGS : ProcessingRunStatus::COMPLETED,
            'pdf_status' => 'COMPLETED',
            'engine_name' => 'simsoal-docx-engine',
            'needs_manual_review' => $withCriticalWarning,
        ]);

        $warning = null;
        if ($withCriticalWarning) {
            $warning = DocumentWarning::query()->create([
                'exam_document_id' => $doc->id,
                'document_processing_run_id' => $run->id,
                'code' => 'TEXTBOX_OR_FLOATING_SHAPE',
                'severity' => WarningSeverity::REVIEW_REQUIRED,
                'message' => 'Dokumen mengandung textbox yang wajib diperiksa secara visual.',
                'is_resolved' => false,
            ]);
        }

        return [$doc, $docxFile, $pdfFile, $warning];
    }

    public function test_t16_authorized_user_can_preview_pdf_inline_while_guest_and_inactive_are_blocked(): void
    {
        Storage::fake('local');
        $operator = User::factory()->operator()->create();
        $inactive = User::factory()->inactive()->create();
        [$doc, $docxFile, $pdfFile] = $this->seedProcessedDocumentWithArtifacts($operator);

        // Tamu tidak terautentikasi ditolak
        $this->get("/document-files/{$pdfFile->id}/preview")
            ->assertRedirect('/login');

        // Pengguna nonaktif ditolak
        $this->actingAs($inactive)
            ->get("/document-files/{$pdfFile->id}/preview")
            ->assertRedirect('/login');

        // Berkas non-PDF tidak dapat dibuka lewat endpoint preview
        $this->actingAs($operator)
            ->get("/document-files/{$docxFile->id}/preview")
            ->assertStatus(422);

        // Operator aktif dapat melihat pratinjau PDF secara inline
        $response = $this->actingAs($operator)
            ->get("/document-files/{$pdfFile->id}/preview");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString(
            'inline',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_t15_critical_warning_blocks_approval_until_resolved_then_operator_can_approve_or_request_revision(): void
    {
        Storage::fake('local');
        $operator = User::factory()->operator()->create();
        [$doc, , , $warning] = $this->seedProcessedDocumentWithArtifacts($operator, true);

        // Mencoba approve saat masih ada peringatan REVIEW_REQUIRED harus ditolak
        $this->actingAs($operator)
            ->from("/exam-documents/{$doc->id}")
            ->post("/exam-documents/{$doc->id}/review", [
                'decision' => DocumentStatus::APPROVED->value,
                'review_notes' => 'Langsung disetujui',
            ])
            ->assertRedirect("/exam-documents/{$doc->id}")
            ->assertSessionHas('error');

        $doc->refresh();
        $this->assertEquals(DocumentStatus::NEEDS_REVIEW, $doc->current_status);
        $this->assertNull($doc->approved_at);

        // Selesaikan catatan peringatan terlebih dahulu
        $this->actingAs($operator)
            ->patch("/exam-documents/{$doc->id}/warnings/{$warning->id}/resolve", [
                'resolution_note' => 'Tata letak kotak catatan sudah diverifikasi pada pratinjau PDF.',
            ])
            ->assertRedirect("/exam-documents/{$doc->id}")
            ->assertSessionHas('success');

        $warning->refresh();
        $this->assertTrue($warning->is_resolved);
        $this->assertEquals($operator->id, $warning->resolved_by);
        $this->assertNotNull($warning->resolved_at);

        // Setelah peringatan selesai, dokumen dapat disetujui (APPROVED)
        $this->actingAs($operator)
            ->post("/exam-documents/{$doc->id}/review", [
                'decision' => DocumentStatus::APPROVED->value,
                'review_notes' => 'Siap cetak penggandaan.',
            ])
            ->assertRedirect("/exam-documents/{$doc->id}")
            ->assertSessionHas('success');

        $doc->refresh();
        $this->assertEquals(DocumentStatus::APPROVED, $doc->current_status);
        $this->assertEquals($operator->id, $doc->approved_by);
        $this->assertNotNull($doc->approved_at);

        // Operator juga dapat mengubah menjadi REVISION_REQUIRED jika diperlukan revisi
        $this->actingAs($operator)
            ->post("/exam-documents/{$doc->id}/review", [
                'decision' => DocumentStatus::REVISION_REQUIRED->value,
                'review_notes' => 'Nomor soal 14 terpotong di batas halaman, mohon perbaiki spasi pada sumber.',
            ])
            ->assertRedirect("/exam-documents/{$doc->id}")
            ->assertSessionHas('warning');

        $doc->refresh();
        $this->assertEquals(DocumentStatus::REVISION_REQUIRED, $doc->current_status);
        $this->assertNull($doc->approved_by);
        $this->assertNull($doc->approved_at);
        $this->assertEquals($operator->id, $doc->reviewed_by);
    }

    public function test_revision_decision_requires_notes_and_archived_period_cannot_be_reviewed(): void
    {
        Storage::fake('local');
        $operator = User::factory()->operator()->create();
        [$activeDoc] = $this->seedProcessedDocumentWithArtifacts($operator, false, ExamPeriodStatus::ACTIVE);
        [$archivedDoc] = $this->seedProcessedDocumentWithArtifacts($operator, false, ExamPeriodStatus::ARCHIVED);

        // Meminta revisi tanpa catatan harus gagal validasi
        $this->actingAs($operator)
            ->from("/exam-documents/{$activeDoc->id}")
            ->post("/exam-documents/{$activeDoc->id}/review", [
                'decision' => DocumentStatus::REVISION_REQUIRED->value,
                'review_notes' => '',
            ])
            ->assertRedirect("/exam-documents/{$activeDoc->id}")
            ->assertSessionHasErrors(['review_notes']);

        // Dokumen pada periode arsip tidak dapat direview
        $this->actingAs($operator)
            ->from("/exam-documents/{$archivedDoc->id}")
            ->post("/exam-documents/{$archivedDoc->id}/review", [
                'decision' => DocumentStatus::APPROVED->value,
            ])
            ->assertRedirect("/exam-documents/{$archivedDoc->id}")
            ->assertSessionHas('error');
    }
}