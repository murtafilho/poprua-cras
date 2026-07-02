<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\StackVersoes;
use Illuminate\View\View;

class InfraestruturaController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.infraestrutura.index', [
            'versoes' => StackVersoes::all(),
            'phpVersion' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            'geradoEm' => now()->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            'prodUrl' => 'https://sufis.pbh.gov.br/ginfi/poprua-cras/public',
        ]);
    }
}
