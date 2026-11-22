<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_processing_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_document_id')->constrained('exam_documents')->cascadeOnDelete();
            $table->foreignId('document_processing_config_id')->nullable()->constrained('document_processing_configs')->nullOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_type', 30)->default('DOCX');
            $table->string('status', 40)->index();
            $table->string('engine_name', 100)->nullable();
            $table->string('engine_version', 40)->nullable();
            $table->string('python_version', 40)->nullable();
            $table->string('docx_library_version', 40)->nullable();
            $table->string('libreoffice_version', 80)->nullable();
            $table->string('input_checksum_sha256', 64)->nullable();
            $table->string('output_docx_checksum_sha256', 64)->nullable();
            $table->boolean('needs_manual_review')->default(false);
            $table->json('metrics_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['exam_document_id', 'created_at']);
        });

        Schema::create('document_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_document_id')->constrained('exam_documents')->cascadeOnDelete();
            $table->foreignId('document_processing_run_id')->constrained('document_processing_runs')->cascadeOnDelete();
            $table->string('code', 80)->index();
            $table->string('severity', 30)->index();
            $table->text('message');
            $table->string('location', 120)->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['exam_document_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_warnings');
        Schema::dropIfExists('document_processing_runs');
    }
};