<?php

namespace App\Http\Requests\DocumentTemplates;

use App\Enums\TemplateHeaderMode;
use App\Models\DocumentTemplate;
use App\Services\TemplateDocxValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDocumentTemplateRequest extends FormRequest
{
    public ?string $validatedDocxChecksum = null;

    public function authorize(): bool
    {
        return ($this->user()?->is_active ?? false) && ($this->user()?->isAdmin() ?? false);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'header_mode' => ['required', 'string', Rule::in(TemplateHeaderMode::values())],
            'first_page_only' => ['sometimes', 'boolean'],
            'show_school_logo' => ['sometimes', 'boolean'],
            'show_exam_identity_table' => ['sometimes', 'boolean'],
            'header_title' => ['nullable', 'string', 'max:150'],
            'header_subtitle' => ['nullable', 'string', 'max:200'],
            'instruction_text' => ['nullable', 'string', 'max:1000'],
            'template_file' => ['nullable', 'file', 'max:10240'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama template kop wajib diisi.',
            'header_mode.required' => 'Mode kop halaman pertama wajib dipilih.',
            'template_file.max' => 'Ukuran file template .docx maksimal 10 MB.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var ?DocumentTemplate $template */
            $template = $this->route('documentTemplate');

            // Keputusan D-016: Kop wajib hanya tampil pada halaman pertama
            if ($this->has('first_page_only') && ! $this->boolean('first_page_only')) {
                $validator->errors()->add(
                    'first_page_only',
                    'Sesuai keputusan proyek D-016, kop ujian hanya diperbolehkan tampil pada halaman pertama.'
                );
            }

            $isActive = $this->has('is_active') ? $this->boolean('is_active') : true;
            if ($template && $template->is_default && ! $isActive) {
                $validator->errors()->add(
                    'is_active',
                    'Template yang sedang menjadi default sistem tidak dapat dinonaktifkan sebelum menetapkan template default lainnya.'
                );
            }

            $mode = $this->input('header_mode');
            if ($mode === TemplateHeaderMode::CUSTOM_DOCX->value) {
                if ($this->hasFile('template_file')) {
                    $file = $this->file('template_file');
                    $result = app(TemplateDocxValidator::class)->validate($file);

                    if (! $result['valid']) {
                        $validator->errors()->add(
                            'template_file',
                            $result['error'] ?? 'File template .docx tidak valid.'
                        );
                    } else {
                        $this->validatedDocxChecksum = $result['checksum'];
                    }
                } elseif (! $template || ! $template->hasCustomDocxFile()) {
                    $validator->errors()->add(
                        'template_file',
                        'Unggah file template kop (.docx) asli untuk mode Kop Khusus.'
                    );
                }
            }
        });
    }
}