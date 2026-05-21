<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_requires_admin(): void
    {
        $response = $this->get('/register');

        $response->assertRedirect('/login');
    }

    public function test_admin_can_access_registration(): void
    {
        $admin = User::factory()->create();
        Role::create(['name' => 'admin']);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get('/register');

        $response->assertStatus(200);
    }

    public function test_admin_can_register_new_user(): void
    {
        $admin = User::factory()->create();
        Role::create(['name' => 'admin']);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
