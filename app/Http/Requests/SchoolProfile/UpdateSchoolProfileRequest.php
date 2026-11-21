<?php

namespace App\Http\Requests\SchoolProfile;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'institution_name' => ['required', 'string', 'max:180'],
            'foundation_name' => ['nullable', 'string', 'max:180'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'header_lines' => ['nullable', 'array', 'max:6'],
            'header_lines.*' => ['nullable', 'string', 'max:180'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'institution_name.required' => 'Nama satuan pendidikan / sekolah wajib diisi.',
            'email.email' => 'Format email sekolah tidak valid.',
            'header_lines.max' => 'Maksimal 6 baris teks kop surat.',
            'logo.mimes' => 'Logo sekolah harus berformat PNG, JPG, atau JPEG.',
            'logo.max' => 'Ukuran file logo maksimal 2 MB.',
        ];
    }
}