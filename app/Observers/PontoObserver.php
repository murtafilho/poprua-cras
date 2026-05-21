<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Ponto;
use Illuminate\Support\Facades\DB;

class PontoObserver
{
    public function created(Ponto $ponto): void
    {
        $this->syncGeom($ponto);
    }

    public function updated(Ponto $ponto): void
    {
        if ($ponto->wasChanged(['lat', 'lng'])) {
            $this->syncGeom($ponto);
        }
    }

    private function syncGeom(Ponto $ponto): void
    {
        if ($ponto->lat && $ponto->lng) {
            DB::statement(
                'UPDATE pontos SET geom = ST_SetSRID(ST_MakePoint(?, ?), 4326) WHERE id = ?',
                [$ponto->lng, $ponto->lat, $ponto->id]
            );
        }
    }
}
