<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_authentication(): void
    {
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
    }

    public function test_profile_page_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();
    }

    public function test_user_can_update_name(): void
    {
        $user = User::factory()->create(['name' => 'Original']);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'Novo Nome', 'email' => $user->email]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Novo Nome']);
    }

    public function test_user_can_update_email(): void
    {
        $user = User::factory()->create(['email' => 'old@test.com']);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => $user->name, 'email' => 'new@test.com']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'new@test.com']);
    }

    public function test_user_can_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_delete_requires_correct_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->delete(route('profile.destroy'), ['password' => 'wrong'])
            ->assertSessionHasErrors('password', null, 'userDeletion');

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
}
