<?php

namespace App\Services\Pdf\Processors\Edit;

use App\Services\Pdf\PdfToolException;
use App\Services\Pdf\Processors\Processor;

class CropPdf extends Processor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        $mode = $options['mode'] ?? 'margins';
        $area = collect($options['area'] ?? [])->firstWhere('kind', 'rect');

        if ($mode === 'area' && ! $area) {
            throw PdfToolException::forUser('Draw the area to keep on a page first.');
        }

        $this->python('crop', [
            'input' => $inputs[0],
            'output' => $output,
            'mode' => $mode,
            'area' => $area,
            'allPages' => (bool) ($options['allPages'] ?? true),
            'margins' => collect(['top', 'right', 'bottom', 'left'])
                ->mapWithKeys(fn ($side) => [$side => (float) ($options[$side] ?? 0)])
                ->all(),
        ]);
        $this->assertOutput($output);

        return null;
    }
}
