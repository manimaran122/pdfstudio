<?php

namespace App\Services\Pdf\Processors;

use App\Services\Pdf\PdfToolException;

class RepairPdf extends Processor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        // qpdf rebuilds the xref table and object streams; exit code 3 means
        // "succeeded with warnings", which is exactly what a repair looks like.
        try {
            $this->run([$this->binary('qpdf'), $inputs[0], $output], [0, 3]);
            $this->assertOutput($output);

            return null;
        } catch (PdfToolException) {
            @unlink($output);
        }

        // Fall back to re-rendering the document through Ghostscript.
        $this->run([
            $this->binary('gs'), '-q', '-dNOPAUSE', '-dBATCH', '-dSAFER',
            '-sDEVICE=pdfwrite', '-sOutputFile='.$output, $inputs[0],
        ]);

        $this->assertOutput($output);

        return null;
    }
}
