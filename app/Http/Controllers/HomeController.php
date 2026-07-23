<?php

namespace App\Http\Controllers;

use App\Support\AppVersao;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home.index', [
            'brand' => config('app.brand', 'SIZEM'),
            'version' => AppVersao::telaInicial(),
            'versionDetalhe' => AppVersao::detalhe(),
        ]);
    }
}
