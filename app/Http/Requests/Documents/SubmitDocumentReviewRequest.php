<?php

namespace App\Http\Requests\Documents;

use App\Enums\DocumentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitDocumentReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => [
                'required',
                'string',
                Rule::in([
                    DocumentStatus::APPROVED->value,
                    DocumentStatus::REVISION_REQUIRED->value,
                ]),
            ],
            'review_notes' => [
                'nullable',
                'string',
                'max:2000',
                'required_if:decision,' . DocumentStatus::REVISION_REQUIRED->value,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'decision.required' => 'Pilih keputusan hasil pemeriksaan dokumen.',
            'decision.in' => 'Keputusan pemeriksaan tidak valid.',
            'review_notes.required_if' => 'Catatan revisi wajib diisi saat meminta revisi naskah soal.',
        ];
    }
}