<?php

namespace App\Http\Requests\ExamDocuments;

use App\Models\ExamDocument;
use Illuminate\Foundation\Http\FormRequest;

class UpdateExamDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ExamDocument $document */
        $document = $this->route('examDocument');

        return $this->user()?->can('update', $document) ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'school_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'teacher_name' => ['required', 'string', 'max:150'],
            'exam_date' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'document_title' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'teacher_name.required' => 'Nama guru penyusun soal wajib diisi.',
            'document_title.required' => 'Judul naskah soal wajib diisi.',
        ];
    }
}