<?php

namespace App\Services\Pdf\Processors\Edit;

use App\Services\Pdf\PdfToolException;
use App\Services\Pdf\Processors\Processor;

class EditPdf extends Processor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        $items = array_values($options['items'] ?? []);

        if (! $items) {
            throw PdfToolException::forUser('Add something to a page first.');
        }

        $this->python('edit', [
            'input' => $inputs[0],
            'output' => $output,
            'items' => $items,
            'assets' => (object) ($options['_assets'] ?? []),
        ]);
        $this->assertOutput($output);

        return null;
    }
}
