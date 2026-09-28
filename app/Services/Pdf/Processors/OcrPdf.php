<?php

namespace App\Services\Pdf\Processors;

class OcrPdf extends Processor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        $command = [
            $this->binary('ocrmypdf'), '--quiet',
            '--language', config('pdf.ocr_language'),
            ($options['mode'] ?? 'skip') === 'force' ? '--force-ocr' : '--skip-text',
        ];

        if (! empty($options['deskew'])) {
            $command[] = '--deskew';
        }

        $this->run([...$command, $inputs[0], $output]);
        $this->assertOutput($output);

        return null;
    }
}
