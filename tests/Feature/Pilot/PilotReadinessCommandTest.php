<?php

namespace Tests\Feature\Pilot;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PilotReadinessCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_check_command_succeeds_when_environment_and_seed_defaults_are_ready(): void
    {
        Storage::fake('local');
        User::factory()->admin()->create();

        $this->artisan('simsoal:pilot-check')
            ->expectsOutputToContain('Pemeriksaan Kesiapan Sistem & Uji Pilot SIMSOAL')
            ->expectsOutputToContain('Seluruh prasyarat sistem siap untuk menjalankan uji pilot dokumen soal.')
            ->assertExitCode(0);
    }

    public function test_pilot_check_command_fails_when_libreoffice_binary_is_missing(): void
    {
        Storage::fake('local');
        User::factory()->admin()->create();

        config()->set('simsoal.libreoffice_binary', 'C:\\path\\tidak\\ada\\soffice.exe');

        $this->artisan('simsoal:pilot-check')
            ->expectsOutputToContain('Binary LibreOffice tidak ditemukan')
            ->assertExitCode(1);
    }
}