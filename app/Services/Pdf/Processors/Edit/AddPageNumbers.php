<?php

namespace App\Services\Pdf\Processors\Edit;

use App\Services\Pdf\Processors\Processor;

class AddPageNumbers extends Processor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        $this->python('page_numbers', [
            'input' => $inputs[0],
            'output' => $output,
            'position' => $options['position'] ?? 'bottom-center',
            'format' => $options['format'] ?? '{n}',
            'start' => (int) ($options['start'] ?? 1),
            'size' => (float) ($options['size'] ?? 11),
            'color' => $options['color'] ?? '#000000',
            'skipFirst' => (bool) ($options['skipFirst'] ?? false),
            'margin' => (float) ($options['margin'] ?? 10),
        ]);
        $this->assertOutput($output);

        return null;
    }
}
