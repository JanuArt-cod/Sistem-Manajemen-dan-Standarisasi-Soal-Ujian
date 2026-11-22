<?php

namespace App\Http\Requests\ExamDocuments;

use App\Models\DocumentTemplate;
use App\Models\ExamDocument;
use App\Models\FormattingPreset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveDocumentProcessingConfigRequest extends FormRequest
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
            'formatting_preset_id' => ['required', 'integer', 'exists:formatting_presets,id'],
            'document_template_id' => ['required', 'integer', 'exists:document_templates,id'],
            'insert_first_page_header' => ['required', 'boolean'],
            'show_identity_box' => ['required', 'boolean'],
            'custom_exam_heading' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'formatting_preset_id.required' => 'Preset tata letak wajib dipilih.',
            'formatting_preset_id.exists' => 'Preset tata letak tidak valid.',
            'document_template_id.required' => 'Template kop wajib dipilih.',
            'document_template_id.exists' => 'Template kop tidak valid.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $preset = FormattingPreset::query()->find($this->integer('formatting_preset_id'));
            if ($preset && ! $preset->is_active) {
                $validator->errors()->add('formatting_preset_id', 'Preset yang dipilih sedang nonaktif.');
            }

            $template = DocumentTemplate::query()->find($this->integer('document_template_id'));
            if ($template && ! $template->is_active) {
                $validator->errors()->add('document_template_id', 'Template kop yang dipilih sedang nonaktif.');
            }
        });
    }
}