<?php

namespace App\Services\Pdf\Processors\ConvertFrom;

use App\Services\Pdf\PdfToolException;
use App\Services\Pdf\Processors\Processor;

/**
 * PDF/A via ocrmypdf (Ghostscript under the hood). --skip-text leaves
 * pages that already have text alone, so this is a format conversion,
 * not a re-OCR.
 */
class PdfToPdfa extends Processor
{
    /** ocrmypdf exit codes worth explaining to the user. */
    private const ENCRYPTED = 8;

    private const PDFA_FAILED = 10;

    public function process(array $inputs, string $output, array $options): ?array
    {
        $level = in_array($options['level'] ?? null, ['pdfa-1', 'pdfa-2', 'pdfa-3'], true) ? $options['level'] : 'pdfa-2';

        $result = $this->run([
            $this->binary('ocrmypdf'), '--quiet', '--skip-text',
            '--language', config('pdf.ocr_language'),
            '--output-type', $level,
            $inputs[0], $output,
        ], [0, self::ENCRYPTED, self::PDFA_FAILED]);

        match ($result->exitCode()) {
            self::ENCRYPTED => throw PdfToolException::forUser('This PDF is password protected. Unlock it first.'),
            self::PDFA_FAILED => throw PdfToolException::forUser('This PDF couldn’t be made PDF/A compliant. Try Repair PDF first.'),
            default => null,
        };

        $this->assertOutput($output);

        return ['meta' => ['summary' => 'Saved as PDF/A-'.substr($level, -1).'b, ready for long-term archiving.']];
    }
}
