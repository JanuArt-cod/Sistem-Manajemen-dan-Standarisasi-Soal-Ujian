<?php

namespace App\Http\Requests\FormattingPresets;

use App\Enums\PaperCode;
use App\Enums\PaperOrientation;
use App\Models\FormattingPreset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFormattingPresetRequest extends FormRequest
{
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
            'paper_code' => ['required', 'string', Rule::in(PaperCode::values())],
            'paper_width_mm' => ['required', 'numeric', 'min:100', 'max:600'],
            'paper_height_mm' => ['required', 'numeric', 'min:100', 'max:600'],
            'orientation' => ['required', 'string', Rule::in(PaperOrientation::values())],
            'margin_top_mm' => ['required', 'numeric', 'min:0', 'max:100'],
            'margin_bottom_mm' => ['required', 'numeric', 'min:0', 'max:100'],
            'margin_left_mm' => ['required', 'numeric', 'min:0', 'max:100'],
            'margin_right_mm' => ['required', 'numeric', 'min:0', 'max:100'],
            'font_family' => ['required', 'string', 'max:100'],
            'font_size_pt' => ['required', 'numeric', 'min:8', 'max:24'],
            'line_spacing' => ['required', 'numeric', 'min:0.8', 'max:3.0'],
            'paragraph_spacing_before_pt' => ['nullable', 'numeric', 'min:0', 'max:72'],
            'paragraph_spacing_after_pt' => ['nullable', 'numeric', 'min:0', 'max:72'],
            'preserve_original_styles' => ['sometimes', 'boolean'],
            'apply_to_existing_tables' => ['sometimes', 'boolean'],
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
            'name.required' => 'Nama preset format wajib diisi.',
            'paper_width_mm.required' => 'Lebar kertas wajib diisi.',
            'paper_height_mm.required' => 'Tinggi kertas wajib diisi.',
            'font_family.required' => 'Jenis huruf wajib diisi.',
            'font_size_pt.required' => 'Ukuran huruf wajib diisi.',
            'line_spacing.required' => 'Spasi baris wajib diisi.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var ?FormattingPreset $preset */
            $preset = $this->route('formattingPreset');
            $isActive = $this->has('is_active') ? $this->boolean('is_active') : true;

            if ($preset && $preset->is_default && ! $isActive) {
                $validator->errors()->add(
                    'is_active',
                    'Preset yang sedang menjadi default sistem tidak dapat dinonaktifkan sebelum menetapkan preset default lainnya.'
                );
            }
        });
    }
}