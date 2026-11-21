<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('SIMSOAL_ADMIN_EMAIL', 'admin@simsoal.local');
        $name = env('SIMSOAL_ADMIN_NAME', 'Administrator SIMSOAL');
        $password = env('SIMSOAL_ADMIN_PASSWORD');

        if (blank($password)) {
            if (! app()->environment(['local', 'testing'])) {
                throw new RuntimeException(
                    'SIMSOAL_ADMIN_PASSWORD wajib diisi di file .env pada lingkungan non-lokal.'
                );
            }

            $password = 'Admin2026';
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => $password,
                'role' => UserRole::ADMIN,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}