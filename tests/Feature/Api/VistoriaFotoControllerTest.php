<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class VistoriaFotoControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $otherUser;

    private User $admin;

    private Vistoria $vistoria;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');

        // Seed required lookup tables
        DB::table('tipo_abordagem')->insertGetId(['tipo' => 'Rotina']);
        DB::table('resultados_acoes')->insertGetId(['resultado' => 'Orientação']);

        $this->owner = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $this->vistoria = Vistoria::factory()->create([
            'user_id' => $this->owner->id,
        ]);

        // Set up Spatie permissions for admin
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = Role::create(['name' => 'admin']);
        Permission::create(['name' => 'editar qualquer vistoria']);
        Permission::create(['name' => 'excluir vistorias']);
        $adminRole->givePermissionTo(['editar qualquer vistoria', 'excluir vistorias']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    // ---------------------------------------------------------------
    // store: authentication
    // ---------------------------------------------------------------

    public function test_store_requires_authentication(): void
    {
        $file = UploadedFile::fake()->image('test-photo.jpg', 640, 480)->size(1024);

        $response = $this->postJson('/api/vistorias/fotos', [
            'vistoria_id' => $this->vistoria->id,
            'foto' => $file,
        ]);

        $response->assertUnauthorized();
    }

    // ---------------------------------------------------------------
    // store: validation
    // ---------------------------------------------------------------

    public function test_store_requires_valid_image_file(): void
    {
        $textFile = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->owner)->postJson('/api/vistorias/fotos', [
            'vistoria_id' => $this->vistoria->id,
            'foto' => $textFile,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['foto']);
    }

    public function test_store_requires_vistoria_id(): void
    {
        $file = UploadedFile::fake()->image('test-photo.jpg', 640, 480)->size(1024);

        $response = $this->actingAs($this->owner)->postJson('/api/vistorias/fotos', [
            'foto' => $file,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['vistoria_id']);
    }

    public function test_store_rejects_files_over_10mb(): void
    {
        // 10240 KB = 10 MB is the max; 10241 KB should be rejected
        $file = UploadedFile::fake()->image('large-photo.jpg', 640, 480)->size(10241);

        $response = $this->actingAs($this->owner)->postJson('/api/vistorias/fotos', [
            'vistoria_id' => $this->vistoria->id,
            'foto' => $file,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['foto']);
    }

    // ---------------------------------------------------------------
    // store: successful upload
    // ---------------------------------------------------------------

    public function test_store_succeeds_with_valid_jpeg_upload(): void
    {
        Queue::fake();

        $file = UploadedFile::fake()->image('test-photo.jpg', 640, 480)->size(1024);

        $response = $this->actingAs($this->owner)->postJson('/api/vistorias/fotos', [
            'vistoria_id' => $this->vistoria->id,
            'foto' => $file,
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'url', 'thumb']);

        // Verify media was attached to the vistoria
        $this->assertCount(1, $this->vistoria->getMedia('fotos'));
    }

    // ---------------------------------------------------------------
    // store: authorization
    // ---------------------------------------------------------------

    public function test_store_only_allows_owner_to_upload(): void
    {
        $file = UploadedFile::fake()->image('test-photo.jpg', 640, 480)->size(1024);

        $response = $this->actingAs($this->otherUser)->postJson('/api/vistorias/fotos', [
            'vistoria_id' => $this->vistoria->id,
            'foto' => $file,
        ]);

        $response->assertForbidden();
    }

    public function test_store_admin_can_upload_to_others_vistoria(): void
    {
        $file = UploadedFile::fake()->image('test-photo.jpg', 640, 480)->size(1024);

        $response = $this->actingAs($this->admin)->postJson('/api/vistorias/fotos', [
            'vistoria_id' => $this->vistoria->id,
            'foto' => $file,
        ]);

        $response->assertCreated();
    }

    // ---------------------------------------------------------------
    // store: filename sanitization
    // ---------------------------------------------------------------

    public function test_store_sanitizes_filename(): void
    {
        Queue::fake();

        $file = UploadedFile::fake()->image('foto com espaços & (caracteres).jpg', 640, 480)->size(512);

        $response = $this->actingAs($this->owner)->postJson('/api/vistorias/fotos', [
            'vistoria_id' => $this->vistoria->id,
            'foto' => $file,
        ]);

        $response->assertCreated();

        $media = $this->vistoria->getMedia('fotos')->first();
        // The sanitized name should not contain spaces, &, or parentheses
        $this->assertDoesNotMatchRegularExpression('/[^a-zA-Z0-9._-]/', $media->name);
    }

    // ---------------------------------------------------------------
    // status: authentication
    // ---------------------------------------------------------------

    public function test_status_requires_authentication(): void
    {
        $response = $this->getJson("/api/vistorias/{$this->vistoria->id}/fotos/status");

        $response->assertUnauthorized();
    }

    // ---------------------------------------------------------------
    // status: authorization
    // ---------------------------------------------------------------

    public function test_status_allows_any_authenticated_user(): void
    {
        // VistoriaPolicy::view() returns true for all authenticated users
        $response = $this->actingAs($this->otherUser)
            ->getJson("/api/vistorias/{$this->vistoria->id}/fotos/status");

        $response->assertOk();
    }

    // ---------------------------------------------------------------
    // status: response structure
    // ---------------------------------------------------------------

    public function test_status_returns_fotos_array_with_expected_structure(): void
    {
        Queue::fake();

        // Upload a photo first
        $file = UploadedFile::fake()->image('test-photo.jpg', 640, 480)->size(1024);
        $this->actingAs($this->owner)->postJson('/api/vistorias/fotos', [
            'vistoria_id' => $this->vistoria->id,
            'foto' => $file,
        ]);

        $response = $this->actingAs($this->owner)
            ->getJson("/api/vistorias/{$this->vistoria->id}/fotos/status");

        $response->assertOk();
        $response->assertJsonStructure([
            'fotos' => [
                '*' => ['id', 'url', 'thumb', 'name'],
            ],
        ]);
    }

    public function test_status_returns_empty_array_when_no_fotos(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson("/api/vistorias/{$this->vistoria->id}/fotos/status");

        $response->assertOk();
        $response->assertJsonPath('fotos', []);
    }
}
