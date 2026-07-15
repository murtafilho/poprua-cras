<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home.index', [
            'brand' => config('app.brand', 'SIZEM'),
            'version' => config('app.version', '2.0'),
        ]);
    }
}
