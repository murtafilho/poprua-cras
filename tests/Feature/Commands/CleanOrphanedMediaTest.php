<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Models\Vistoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CleanOrphanedMediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('tipo_abordagem')->insert(['tipo' => 'Rotina']);
        DB::table('resultados_acoes')->insert(['resultado' => 'Orientação']);
    }

    public function test_reports_no_orphaned_media(): void
    {
        $this->artisan('media:clean-orphaned')
            ->expectsOutput('No orphaned media found.')
            ->assertSuccessful();
    }

    public function test_dry_run_lists_without_deleting(): void
    {
        $vistoria = Vistoria::factory()->create();
        $media = $vistoria->addMediaFromString('test-content')
            ->usingName('test-photo')
            ->usingFileName('test.jpg')
            ->toMediaCollection('test');

        DB::table('vistorias')->where('id', $vistoria->id)->delete();

        $this->artisan('media:clean-orphaned', ['--dry-run' => true])
            ->expectsOutput('Found 1 orphaned media record(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('media', ['id' => $media->id]);
    }

    public function test_deletes_orphaned_media(): void
    {
        $vistoria = Vistoria::factory()->create();
        $media = $vistoria->addMediaFromString('test-content')
            ->usingName('test-photo')
            ->usingFileName('test.jpg')
            ->toMediaCollection('test');

        DB::table('vistorias')->where('id', $vistoria->id)->delete();

        $this->artisan('media:clean-orphaned')
            ->expectsOutputToContain('Deleted 1 orphaned media')
            ->assertSuccessful();

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
    }
}
