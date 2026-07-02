<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Parametro;
use App\Services\ParametroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParametroServiceTest extends TestCase
{
    use RefreshDatabase;

    private ParametroService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ParametroService::class);
    }

    public function test_resolver_per_page_usa_parametro_do_banco(): void
    {
        Parametro::set('vistorias_por_pagina', '10');
        Parametro::set('paginacao_max', '50');

        $this->assertSame(10, $this->service->resolverPerPage(null));
        $this->assertSame(25, $this->service->resolverPerPage(25));
        $this->assertSame(50, $this->service->resolverPerPage(100));
    }

    public function test_foto_max_kb_e_regras_validacao(): void
    {
        Parametro::set('foto_max_tamanho_kb', '5120');

        $this->assertSame(5120, $this->service->fotoMaxKb());
        $this->assertSame(
            ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            $this->service->regrasValidacaoFoto()
        );
        $this->assertSame(
            ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            $this->service->regrasValidacaoFoto(true)
        );
    }

    public function test_limites_complexidade_e_config_mapa(): void
    {
        Parametro::set('complexidade_critico', '10');
        Parametro::set('complexidade_alto', '6');
        Parametro::set('complexidade_medio', '4');
        Parametro::set('mapa_centro_lat', '-19.92');
        Parametro::set('mapa_centro_lng', '-43.95');
        Parametro::set('mapa_zoom_padrao', '14');

        $limites = $this->service->limitesComplexidade();
        $this->assertSame(['critico' => 10, 'alto' => 6, 'medio' => 4], $limites);
        $this->assertSame('badge-warning', $this->service->badgeComplexidade(6));
        $this->assertSame('#f59e0b', $this->service->corComplexidade(6));

        $mapa = $this->service->configMapa();
        $this->assertSame([-19.92, -43.95], $mapa['center']);
        $this->assertSame(14, $mapa['zoom']);
        $this->assertSame($limites, $mapa['complexidade']);
    }

    public function test_sincronizar_config_app(): void
    {
        Parametro::set('app_nome', 'Teste SIZEM');
        Parametro::set('app_orgao', 'Órgão Teste');

        $this->service->sincronizarConfigApp();

        $this->assertSame('Teste SIZEM', config('app.brand'));
        $this->assertSame('Órgão Teste', config('app.orgao'));
    }
}
