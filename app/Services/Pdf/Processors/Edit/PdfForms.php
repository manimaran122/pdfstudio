<?php

namespace App\Services\Pdf\Processors\Edit;

use App\Services\Pdf\Processors\Processor;

class PdfForms extends Processor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        $flatten = (bool) ($options['flatten'] ?? false);
        $target = $flatten ? $output.'.fields.pdf' : $output;

        try {
            $result = $this->python('forms', [
                'input' => $inputs[0],
                'output' => $target,
                'fields' => array_values($options['_fields'] ?? []),
                'values' => array_values($options['values'] ?? []),
                'add' => array_values($options['add'] ?? []),
            ]);

            if ($flatten) {
                // PyMuPDF 1.23 can't bake widgets; pdftk draws their appearances into the page.
                $this->run([config('pdf.pdftk'), $target, 'output', $output, 'flatten']);
            }
        } finally {
            if ($flatten) {
                @unlink($target);
            }
        }

        $this->assertOutput($output);

        return ['meta' => ['filled' => $result['filled'] ?? 0, 'added' => $result['added'] ?? 0]];
    }
}
