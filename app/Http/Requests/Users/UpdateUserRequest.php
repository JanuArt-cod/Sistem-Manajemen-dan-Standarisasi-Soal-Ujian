<?php

namespace App\Http\Requests\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
        /** @var User $targetUser */
        $targetUser = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($targetUser->id),
            ],
            'role' => [
                'required',
                'string',
                Rule::in(UserRole::values()),
                function (string $attribute, mixed $value, \Closure $fail) use ($targetUser): void {
                    if ($this->user()?->id === $targetUser->id && $value !== UserRole::ADMIN->value) {
                        $fail('Anda tidak dapat menurunkan peran akun Anda sendiri dari Administrator.');
                    }
                },
            ],
            'password' => ['nullable', 'string', Password::min(8), 'confirmed'],
            'is_active' => [
                'sometimes',
                'boolean',
                function (string $attribute, mixed $value, \Closure $fail) use ($targetUser): void {
                    if ($this->user()?->id === $targetUser->id && ! (bool) $value) {
                        $fail('Anda tidak dapat menonaktifkan akun Anda sendiri.');
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
            'name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email sudah digunakan pengguna lain.',
            'role.required' => 'Peran pengguna wajib dipilih.',
            'role.in' => 'Peran pengguna tidak valid.',
            'password.min' => 'Kata sandi baru minimal terdiri dari 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ];
    }
}