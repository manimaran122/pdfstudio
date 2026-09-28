<?php

namespace App\Services\Pdf\Processors\Security;

use App\Services\Pdf\Processors\Processor;

class ComparePdf extends Processor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        $result = $this->python('compare', [
            'inputs' => array_slice($inputs, 0, 2),
            'output' => $output,
            'names' => array_slice($options['_names'] ?? [], 0, 2),
        ]);

        $this->assertOutput($output);

        return ['meta' => ['summary' => $result['summary'], 'changes' => $result['changes']]];
    }
}
