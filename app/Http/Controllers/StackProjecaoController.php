<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StackProjecaoController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $path = base_path('docs/TERMO_DE_REFERENCIA_HOSPEDAGEM_SIZEM.html');

        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
