<?php

namespace App\Console\Commands;

use App\Support\StackVersoes;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateStackPdfCommand extends Command
{
    protected $signature = 'docs:stack-pdf {--output=docs/STACK_TECNOLOGICA.pdf}';

    protected $description = 'Gera PDF da stack tecnológica do SIZEM BH';

    public function handle(): int
    {
        $output = base_path($this->option('output'));
        File::ensureDirectoryExists(dirname($output));

        $versoes = StackVersoes::all();

        Pdf::loadView('docs.stack-tecnologica', [
            'geradoEm' => now()->timezone('America/Sao_Paulo')->format('d/m/Y H:i'),
            'versoes' => $versoes,
        ])
            ->setPaper('a4', 'portrait')
            ->save($output);

        $this->info("PDF gerado: {$output}");

        return self::SUCCESS;
    }
}
