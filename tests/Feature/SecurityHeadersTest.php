<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_responses_include_security_headers(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('mapa.index'));

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_permissions_policy_allows_camera_and_geolocation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('mapa.index'));

        $this->assertStringContainsString('camera=(self)', $response->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('geolocation=(self)', $response->headers->get('Permissions-Policy'));
    }

    public function test_login_page_includes_security_headers(): void
    {
        $response = $this->get('/login');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_html_documents_are_not_cached_by_clients(): void
    {
        $response = $this->get('/login');

        $cacheControl = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $response->assertHeader('Pragma', 'no-cache');
    }
}
