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
        Parametro::query()->create([
            'chave' => 'paginacao_max',
            'valor' => '50',
            'tipo' => 'integer',
            'grupo' => 'listagem',
        ]);

        $this->assertSame(10, $this->service->resolverPerPage(null));
        $this->assertSame(25, $this->service->resolverPerPage(25));
        $this->assertSame(50, $this->service->resolverPerPage(100));
    }
}
