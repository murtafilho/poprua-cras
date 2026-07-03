<?php

namespace App\Http\Controllers;

use App\Services\StackProjecaoService;
use App\Support\StackVersoes;
use Illuminate\View\View;

class StackProjecaoController extends Controller
{
    public function __construct(private StackProjecaoService $stackProjecaoService) {}

    public function __invoke(): View
    {
        return view('stack-projecao.index', [
            'dados' => $this->stackProjecaoService->dados(),
            'versoes' => StackVersoes::all(),
            'phpVersion' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
        ]);
    }
}
