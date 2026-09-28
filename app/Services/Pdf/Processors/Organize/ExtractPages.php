<?php

namespace App\Services\Pdf\Processors\Organize;

/**
 * Copy the selected pages, in page order, into one PDF or a ZIP with one
 * PDF per page.
 */
class ExtractPages extends PageProcessor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        $pages = array_values(array_unique(array_map('intval', $options['pages'] ?? [])));
        sort($pages);

        if (($options['as'] ?? 'combined') === 'combined' || count($pages) === 1) {
            $this->copyPages($inputs[0], $pages, $output);

            return ['meta' => ['summary' => "Extracted {$this->pages(count($pages))} into a new PDF."]];
        }

        $base = $this->baseName($options);
        $groups = [];

        foreach ($pages as $page) {
            $groups["{$base}_{$page}.pdf"] = [$page];
        }

        return [
            'file' => $this->zipGroups($inputs[0], $groups, $output),
            'meta' => ['summary' => 'Extracted '.count($pages).' pages as separate PDFs.'],
        ];
    }
}
