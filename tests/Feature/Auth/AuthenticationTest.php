<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_root_and_dashboard(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    public function test_active_admin_can_authenticate_and_access_dashboard(): void
    {
        $admin = User::factory()->admin()->create([
            'password' => 'SecretPass123!',
        ]);

        $response = $this->post('/login', [
            'email' => $admin->email,
            'password' => 'SecretPass123!',
        ]);

        $this->assertAuthenticatedAs($admin);
        $response->assertRedirect('/dashboard');
    }

    public function test_active_operator_can_authenticate_and_access_dashboard(): void
    {
        $operator = User::factory()->operator()->create([
            'password' => 'SecretPass123!',
        ]);

        $response = $this->post('/login', [
            'email' => $operator->email,
            'password' => 'SecretPass123!',
        ]);

        $this->assertAuthenticatedAs($operator);
        $response->assertRedirect('/dashboard');
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $inactiveUser = User::factory()->operator()->inactive()->create([
            'password' => 'SecretPass123!',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => $inactiveUser->email,
            'password' => 'SecretPass123!',
        ]);

        $this->assertGuest();
        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
    }

    public function test_user_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->operator()->create([
            'password' => 'SecretPass123!',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'WrongPassword!',
        ]);

        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->operator()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/login');
    }
}