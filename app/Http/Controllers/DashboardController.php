<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboardService) {}

    public function __invoke(Request $request): View
    {
        return view('dashboard', [
            'dadosMensais' => $this->dashboardService->dadosMensais(),
            'totais' => $this->dashboardService->totaisVistorias(),
            'totalPontos' => $this->dashboardService->totalPontos(),
            'resultados' => $this->dashboardService->resultadosAcoes(),
        ]);
    }
}
