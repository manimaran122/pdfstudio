<?php

namespace App\Services\Pdf\Processors;

class ImagesToPdf extends Processor
{
    private const PAGE_SIZES = ['a4' => 'A4', 'letter' => 'Letter'];

    public function process(array $inputs, string $output, array $options): ?array
    {
        $command = [$this->binary('img2pdf')];
        $size = self::PAGE_SIZES[$options['pageSize'] ?? 'fit'] ?? null;

        if ($size) {
            array_push($command, '--pagesize', $size, '--fit', 'into', '--auto-orient');

            if (! empty($options['margin'])) {
                array_push($command, '--border', '1cm');
            }
        }

        $this->run([...$command, '-o', $output, ...$inputs]);
        $this->assertOutput($output);

        return null;
    }
}
