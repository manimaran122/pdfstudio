<?php

namespace App\Services\Pdf\Processors;

class CompressPdf extends Processor
{
    private const SETTINGS = [
        'extreme' => '/screen',
        'recommended' => '/ebook',
        'low' => '/printer',
    ];

    public function process(array $inputs, string $output, array $options): ?array
    {
        $this->run([
            $this->binary('gs'), '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER',
            '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.5',
            '-dPDFSETTINGS='.self::SETTINGS[$options['level'] ?? 'recommended'],
            '-dDetectDuplicateImages=true', '-dCompressFonts=true',
            '-sOutputFile='.$output, $inputs[0],
        ]);

        $this->assertOutput($output);

        // Ghostscript can grow an already-optimized file; never hand back a bigger one.
        if (filesize($output) >= filesize($inputs[0])) {
            copy($inputs[0], $output);
        }

        return null;
    }
}
