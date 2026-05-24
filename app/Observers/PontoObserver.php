<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Ponto;

class PontoObserver
{
    public function created(Ponto $ponto): void
    {
        $ponto->updateGeom();
    }

    public function updated(Ponto $ponto): void
    {
        if ($ponto->wasChanged(['lat', 'lng'])) {
            $ponto->updateGeom();
        }
    }
}
