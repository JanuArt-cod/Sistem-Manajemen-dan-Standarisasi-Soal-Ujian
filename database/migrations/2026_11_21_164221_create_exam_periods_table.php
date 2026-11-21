<?php

use App\Enums\ExamPeriodStatus;
use App\Enums\ExamSemester;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('academic_year', 20);
            $table->string('semester', 20)->default(ExamSemester::ODD->value);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default(ExamPeriodStatus::DRAFT->value);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_periods');
    }
};