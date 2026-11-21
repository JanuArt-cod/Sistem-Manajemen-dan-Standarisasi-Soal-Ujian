<?php

namespace Tests\Feature\Administration;

use App\Enums\ExamPeriodStatus;
use App\Enums\ExamSemester;
use App\Models\ExamPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdministrationDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_school_profile_with_logo_stored_privately(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();

        $logo = UploadedFile::fake()->image('logo.png', 200, 200);

        $response = $this->actingAs($admin)->put('/school-profile', [
            'institution_name' => 'SMK Bina Insan Mandiri',
            'foundation_name' => 'Yayasan Pendidikan Mandiri',
            'address' => 'Jl. Pendidikan No. 10',
            'phone' => '0251-123456',
            'email' => 'info@smkbim.sch.id',
            'header_lines' => ['DINAS PENDIDIKAN', 'KABUPATEN BOGOR'],
            'logo' => $logo,
        ]);

        $response->assertRedirect('/school-profile');
        $this->assertDatabaseHas('school_profiles', [
            'institution_name' => 'SMK Bina Insan Mandiri',
        ]);

        $this->actingAs($admin)
            ->get('/school-profile/logo')
            ->assertStatus(200);
    }

    public function test_operator_cannot_edit_school_profile(): void
    {
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator)->get('/school-profile')->assertStatus(403);
        $this->actingAs($operator)->put('/school-profile', [
            'institution_name' => 'Perubahan Ilegal',
        ])->assertStatus(403);
    }

    public function test_operator_can_create_and_archive_exam_period_but_only_admin_can_reopen(): void
    {
        $operator = User::factory()->operator()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($operator)->post('/exam-periods', [
            'name' => 'PAS Ganjil 2026/2027',
            'academic_year' => '2026/2027',
            'semester' => ExamSemester::ODD->value,
            'status' => ExamPeriodStatus::ACTIVE->value,
        ])->assertRedirect('/exam-periods');

        $period = ExamPeriod::query()->firstOrFail();

        // Operator mengarsipkan periode
        $this->actingAs($operator)
            ->patch("/exam-periods/{$period->id}/archive")
            ->assertRedirect('/exam-periods');

        $this->assertDatabaseHas('exam_periods', [
            'id' => $period->id,
            'status' => ExamPeriodStatus::ARCHIVED->value,
        ]);

        // Operator ditolak saat mencoba membuka kembali periode yang diarsipkan
        $this->actingAs($operator)
            ->patch("/exam-periods/{$period->id}/reopen")
            ->assertStatus(403);

        // Admin dapat membuka kembali periode yang diarsipkan
        $this->actingAs($admin)
            ->patch("/exam-periods/{$period->id}/reopen")
            ->assertRedirect('/exam-periods');

        $this->assertDatabaseHas('exam_periods', [
            'id' => $period->id,
            'status' => ExamPeriodStatus::ACTIVE->value,
        ]);
    }

    public function test_operator_can_manage_subjects_and_school_classes(): void
    {
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator)->post('/master-data/subjects', [
            'name' => 'Teknik Jaringan Komputer',
            'code' => 'TJKT',
        ])->assertRedirect('/master-data');

        $this->actingAs($operator)->post('/master-data/classes', [
            'name' => 'XI TJKT 1',
            'level' => 'XI',
        ])->assertRedirect('/master-data');

        $this->assertDatabaseHas('subjects', ['name' => 'Teknik Jaringan Komputer', 'code' => 'TJKT']);
        $this->assertDatabaseHas('school_classes', ['name' => 'XI TJKT 1', 'level' => 'XI']);
    }
}