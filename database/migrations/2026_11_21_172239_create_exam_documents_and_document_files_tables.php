<?php

use App\Enums\DocumentFileRole;
use App\Enums\DocumentSourceFormat;
use App\Enums\DocumentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_period_id')->constrained('exam_periods')->restrictOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->string('teacher_name', 150);
            $table->date('exam_date')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('document_title', 200)->nullable();
            $table->string('source_format', 20)->default(DocumentSourceFormat::DOCX->value);
            $table->string('current_status', 30)->default(DocumentStatus::UPLOADED->value);
            $table->text('notes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['exam_period_id', 'current_status']);
            $table->index('subject_id');
            $table->index('school_class_id');
            $table->index('current_status');
        });

        Schema::create('document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_document_id')->constrained('exam_documents')->restrictOnDelete();
            $table->unsignedBigInteger('processing_run_id')->nullable();
            $table->string('file_role', 30)->default(DocumentFileRole::SOURCE->value);
            $table->string('original_filename', 255);
            $table->string('storage_disk', 50)->default('local');
            $table->string('storage_path', 500);
            $table->string('mime_type', 150);
            $table->string('extension', 20);
            $table->unsignedBigInteger('size_bytes');
            $table->string('checksum_sha256', 64);
            $table->boolean('is_current')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['exam_document_id', 'file_role', 'is_current']);
            $table->index('processing_run_id');
            $table->index('checksum_sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_files');
        Schema::dropIfExists('exam_documents');
    }
};