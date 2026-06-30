<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

final class FormatoData
{
    public static function exibir(DateTimeInterface|string|null $valor, string $formatoData = 'd/m/Y'): string
    {
        if ($valor === null || $valor === '') {
            return '-';
        }

        $data = $valor instanceof DateTimeInterface
            ? Carbon::instance($valor)
            : Carbon::parse($valor);

        if ($data->format('H:i') === '00:00') {
            return $data->format($formatoData);
        }

        return $data->format($formatoData.' H:i');
    }

    public static function temHora(DateTimeInterface|string $valor): bool
    {
        $data = $valor instanceof DateTimeInterface
            ? Carbon::instance($valor)
            : Carbon::parse($valor);

        return $data->format('H:i') !== '00:00';
    }
}
