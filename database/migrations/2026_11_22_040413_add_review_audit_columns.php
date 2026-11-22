<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_warnings', function (Blueprint $table) {
            if (! Schema::hasColumn('document_warnings', 'resolved_by')) {
                $table->foreignId('resolved_by')
                    ->nullable()
                    ->after('is_resolved')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('document_warnings', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('resolved_by');
            }

            if (! Schema::hasColumn('document_warnings', 'resolution_note')) {
                $table->string('resolution_note', 500)->nullable()->after('resolved_at');
            }
        });

        Schema::table('exam_documents', function (Blueprint $table) {
            if (! Schema::hasColumn('exam_documents', 'reviewed_by')) {
                $table->foreignId('reviewed_by')
                    ->nullable()
                    ->after('approved_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('exam_documents', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            }

            if (! Schema::hasColumn('exam_documents', 'review_notes')) {
                $table->text('review_notes')->nullable()->after('reviewed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_warnings', function (Blueprint $table) {
            if (Schema::hasColumn('document_warnings', 'resolved_by')) {
                $table->dropConstrainedForeignId('resolved_by');
            }
            if (Schema::hasColumn('document_warnings', 'resolved_at')) {
                $table->dropColumn('resolved_at');
            }
            if (Schema::hasColumn('document_warnings', 'resolution_note')) {
                $table->dropColumn('resolution_note');
            }
        });

        Schema::table('exam_documents', function (Blueprint $table) {
            if (Schema::hasColumn('exam_documents', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }
            if (Schema::hasColumn('exam_documents', 'reviewed_at')) {
                $table->dropColumn('reviewed_at');
            }
            if (Schema::hasColumn('exam_documents', 'review_notes')) {
                $table->dropColumn('review_notes');
            }
        });
    }
};