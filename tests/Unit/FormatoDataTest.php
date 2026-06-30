<?php

namespace Tests\Unit;

use App\Support\FormatoData;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FormatoDataTest extends TestCase
{
    #[Test]
    public function oculta_hora_meia_noite(): void
    {
        $data = Carbon::parse('2026-05-20 00:00:00');

        $this->assertSame('20/05/2026', FormatoData::exibir($data));
        $this->assertFalse(FormatoData::temHora($data));
    }

    #[Test]
    public function exibe_hora_quando_preenchida(): void
    {
        $data = Carbon::parse('2026-05-20 14:30:00');

        $this->assertSame('20/05/2026 14:30', FormatoData::exibir($data));
        $this->assertTrue(FormatoData::temHora($data));
    }

    #[Test]
    public function valor_nulo_retorna_traco(): void
    {
        $this->assertSame('-', FormatoData::exibir(null));
    }
}
