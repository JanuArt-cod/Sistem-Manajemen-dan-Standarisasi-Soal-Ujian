<?php

namespace Tests\Feature\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_users_list(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->operator()->count(3)->create();

        $this->actingAs($admin)
            ->get('/users')
            ->assertStatus(200);
    }

    public function test_operator_cannot_access_users_management(): void
    {
        $operator = User::factory()->operator()->create();

        $this->actingAs($operator)->get('/users')->assertStatus(403);
        $this->actingAs($operator)->post('/users', [
            'name' => 'Baru',
            'email' => 'baru@simsoal.local',
            'role' => UserRole::OPERATOR->value,
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
        ])->assertStatus(403);
    }

    public function test_admin_can_create_operator_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'Operator Ujian 1',
            'email' => 'operator1@simsoal.local',
            'role' => UserRole::OPERATOR->value,
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
            'is_active' => true,
        ]);

        $response->assertRedirect('/users');
        $this->assertDatabaseHas('users', [
            'email' => 'operator1@simsoal.local',
            'role' => UserRole::OPERATOR->value,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_update_user_and_toggle_active_status(): void
    {
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->operator()->create(['is_active' => true]);

        $this->actingAs($admin)->put("/users/{$operator->id}", [
            'name' => 'Operator Diperbarui',
            'email' => $operator->email,
            'role' => UserRole::OPERATOR->value,
            'is_active' => true,
        ])->assertRedirect('/users');

        $this->assertDatabaseHas('users', [
            'id' => $operator->id,
            'name' => 'Operator Diperbarui',
        ]);

        $this->actingAs($admin)
            ->patch("/users/{$operator->id}/toggle-active")
            ->assertRedirect('/users');

        $this->assertDatabaseHas('users', [
            'id' => $operator->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_cannot_deactivate_own_account(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->patch("/users/{$admin->id}/toggle-active")
            ->assertStatus(403);

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_active' => true,
        ]);
    }
}