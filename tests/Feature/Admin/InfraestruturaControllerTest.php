<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class InfraestruturaControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::factory()->create();
        Role::create(['name' => 'admin']);
        $this->admin->assignRole('admin');

        $this->regularUser = User::factory()->create();
    }

    public function test_guest_is_redirected_from_infraestrutura(): void
    {
        $this->get(route('admin.infraestrutura'))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_access_infraestrutura(): void
    {
        $this->actingAs($this->regularUser)
            ->get(route('admin.infraestrutura'))
            ->assertForbidden();
    }

    public function test_admin_can_view_infraestrutura_page(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.infraestrutura'))
            ->assertOk()
            ->assertViewIs('admin.infraestrutura.index')
            ->assertSee('SIZEM — Infraestrutura')
            ->assertSee('php84-poprua-cras')
            ->assertSee('Containers Docker')
            ->assertSee('Stack tecnológica')
            ->assertSee('Versionamento')
            ->assertSee('v2.0')
            ->assertViewHas('versoes')
            ->assertViewHas('phpVersion');
    }
}
