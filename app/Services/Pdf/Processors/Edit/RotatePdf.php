<?php

namespace App\Services\Pdf\Processors\Edit;

use App\Services\Pdf\Processors\Processor;

class RotatePdf extends Processor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        // Page number => clockwise degrees, relative to the current rotation.
        $rotations = collect($options['rotation'] ?? [])
            ->map(fn ($degrees, $page) => [(int) $page, (int) $degrees])
            ->filter(fn ($pair) => $pair[1] % 360 !== 0)
            ->values()
            ->all();

        $this->python('rotate', ['input' => $inputs[0], 'output' => $output, 'rotations' => $rotations]);
        $this->assertOutput($output);

        return null;
    }
}
