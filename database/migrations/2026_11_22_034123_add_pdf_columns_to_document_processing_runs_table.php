<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_processing_runs', function (Blueprint $table) {
            $table->string('pdf_status', 30)->default('PENDING')->after('status');
            $table->string('output_pdf_checksum_sha256', 64)->nullable()->after('output_docx_checksum_sha256');
            $table->text('pdf_error_message')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('document_processing_runs', function (Blueprint $table) {
            $table->dropColumn([
                'pdf_status',
                'output_pdf_checksum_sha256',
                'pdf_error_message',
            ]);
        });
    }
};