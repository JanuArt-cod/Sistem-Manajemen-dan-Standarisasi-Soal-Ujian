<?php

namespace App\Http\Requests\ExamPeriods;

use App\Enums\ExamPeriodStatus;
use App\Enums\ExamSemester;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExamPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_active ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'academic_year' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'string', Rule::in(ExamSemester::values())],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'string', Rule::in([
                ExamPeriodStatus::DRAFT->value,
                ExamPeriodStatus::ACTIVE->value,
            ])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama periode ujian wajib diisi.',
            'academic_year.required' => 'Tahun pelajaran wajib diisi.',
            'semester.required' => 'Semester wajib dipilih.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
            'status.in' => 'Periode baru hanya dapat disimpan sebagai Draf atau Aktif.',
        ];
    }
}