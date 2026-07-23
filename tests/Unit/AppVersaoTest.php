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

    public function test_tela_inicial_mostra_a_build_publicada_e_nao_o_numero_fixo(): void
    {
        config(['app.version' => '2.0']);

        $rotulo = AppVersao::telaInicial();
        $git = AppVersao::git();

        if ($git['commit'] === null) {
            // Sem .git (produção empacotada sem repositório) degrada para o rótulo fixo.
            $this->assertSame('v2.0', $rotulo);

            return;
        }

        $this->assertStringContainsString($git['commit'], $rotulo);
        $this->assertNotSame('v2.0', $rotulo);
        $this->assertMatchesRegularExpression('#^(\d{2}/\d{2}/\d{4} · )?[0-9a-f]{7,}$#', $rotulo);
    }

    public function test_detalhe_reune_branch_commit_e_cache_pwa(): void
    {
        config(['app.version' => '2.0']);

        $detalhe = AppVersao::detalhe();
        $git = AppVersao::git();

        $this->assertStringContainsString('v2.0', $detalhe);
        $this->assertStringContainsString('cache PWA v'.AppVersao::pwaCacheVersion(), $detalhe);

        if ($git['commit'] !== null) {
            $this->assertStringContainsString('commit '.$git['commit'], $detalhe);
        }
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
