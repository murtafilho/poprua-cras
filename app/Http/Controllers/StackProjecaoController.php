<?php

namespace App\Http\Controllers;

use App\Services\StackProjecaoService;
use Illuminate\View\View;

class StackProjecaoController extends Controller
{
    public function __construct(private StackProjecaoService $stackProjecaoService) {}

    public function __invoke(): View
    {
        return view('stack-projecao.index', [
            'dados' => $this->stackProjecaoService->dados(),
        ]);
    }
}
