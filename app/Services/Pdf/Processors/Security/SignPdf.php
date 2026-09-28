<?php

namespace App\Services\Pdf\Processors\Security;

use App\Services\Pdf\PdfToolException;
use App\Services\Pdf\Processors\Processor;

class SignPdf extends Processor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        $placements = array_values($options['placements'] ?? []);

        if (! collect($placements)->contains(fn ($item) => $item['kind'] === 'signature' && filled($item['asset'] ?? null))) {
            throw PdfToolException::forUser('Add your signature to the page first.');
        }

        $used = array_filter(array_column($placements, 'asset'));

        $result = $this->python('sign', [
            'input' => $inputs[0],
            'output' => $output,
            'placements' => $placements,
            'assets' => array_intersect_key($options['_assets'] ?? [], array_flip($used)),
        ]);

        $this->assertOutput($output);

        $count = (int) ($result['signatures'] ?? 0);

        return ['meta' => ['summary' => $count === 1 ? 'Signature added.' : "{$count} signatures added."]];
    }
}
