<?php

namespace Tests\Unit;

use App\Support\AppVersao;
use Tests\TestCase;

class AppVersaoTest extends TestCase
{
    public function test_release_usa_config_app_version(): void
    {
        config(['app.version' => '2.1']);

        $this->assertSame('2.1', AppVersao::release());
        $this->assertSame('v2.1', AppVersao::label());
    }

    public function test_pwa_cache_version_le_de_sw_js(): void
    {
        $this->assertNotNull(AppVersao::pwaCacheVersion());
        $this->assertMatchesRegularExpression('/^\d+$/', AppVersao::pwaCacheVersion());
    }

    public function test_git_retorna_estrutura_esperada(): void
    {
        $git = AppVersao::git();

        $this->assertArrayHasKey('branch', $git);
        $this->assertArrayHasKey('commit', $git);
        $this->assertArrayHasKey('commit_full', $git);
        $this->assertArrayHasKey('message', $git);
        $this->assertArrayHasKey('date', $git);

        if ($git['commit_full'] !== null) {
            $this->assertStringContainsString($git['commit'], AppVersao::commitUrl($git['commit_full']));
        }
    }
}
