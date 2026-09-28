<?php

namespace App\Services\Pdf\Processors\Organize;

/**
 * Rebuild the PDF from the page grid's list: pages in the new order, with
 * repeats as copies, and each rotation added to the page's own.
 */
class OrganizePdf extends PageProcessor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        $pages = array_values($options['pages'] ?? []);
        $this->copyPages($inputs[0], $pages, $output);

        return ['meta' => ['summary' => "Your new PDF has {$this->pages(count($pages))}."]];
    }
}
