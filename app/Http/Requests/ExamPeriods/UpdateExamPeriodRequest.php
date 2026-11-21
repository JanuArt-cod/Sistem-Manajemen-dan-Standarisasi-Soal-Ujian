<?php

namespace App\Http\Requests\ExamPeriods;

use App\Enums\ExamPeriodStatus;
use App\Enums\ExamSemester;
use App\Models\ExamPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExamPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ExamPeriod $period */
        $period = $this->route('examPeriod');

        return $this->user()?->can('update', $period) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var ExamPeriod $period */
        $period = $this->route('examPeriod');

        return [
            'name' => ['required', 'string', 'max:150'],
            'academic_year' => ['required', 'string', 'max:20'],
            'semester' => ['required', 'string', Rule::in(ExamSemester::values())],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => [
                'required',
                'string',
                Rule::in(ExamPeriodStatus::values()),
                function (string $attribute, mixed $value, \Closure $fail) use ($period): void {
                    if ($period->isArchived() && $value !== ExamPeriodStatus::ARCHIVED->value && ! $this->user()?->isAdmin()) {
                        $fail('Periode yang sudah diarsipkan hanya dapat dibuka kembali oleh Administrator.');
                    }
                },
            ],
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
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
        ];
    }
}