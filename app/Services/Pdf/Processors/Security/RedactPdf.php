<?php

namespace App\Services\Pdf\Processors\Security;

use App\Services\Pdf\Processors\Processor;

class RedactPdf extends Processor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        $presets = array_keys(array_filter([
            'emails' => $options['emails'] ?? false,
            'phones' => $options['phones'] ?? false,
            'cards' => $options['cards'] ?? false,
        ]));

        $result = $this->python('redact', [
            'input' => $inputs[0],
            'output' => $output,
            'terms' => (string) ($options['terms'] ?? ''),
            'presets' => $presets,
            'match_case' => (bool) ($options['matchCase'] ?? false),
            'areas' => array_values($options['areas'] ?? []),
            'strip_metadata' => (bool) ($options['metadata'] ?? true),
        ]);

        $this->assertOutput($output);

        return ['meta' => ['summary' => $result['summary']]];
    }
}
