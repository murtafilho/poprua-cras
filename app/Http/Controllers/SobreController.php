<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SobreController extends Controller
{
    public function __invoke(): View
    {
        return view('sobre.index');
    }
}
