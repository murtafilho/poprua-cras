<?php

namespace App\Http\Controllers;

use App\Services\ParametroService;
use Illuminate\View\View;

class MapaController extends Controller
{
    public function __construct(private ParametroService $parametroService) {}

    public function index(): View
    {
        return view('mapa.index', [
            'mapaConfig' => $this->parametroService->configMapa(),
        ]);
    }
}
