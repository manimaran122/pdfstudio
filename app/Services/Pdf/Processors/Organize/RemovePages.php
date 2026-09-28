<?php

namespace App\Services\Pdf\Processors\Organize;

use App\Services\Pdf\PdfToolException;

/**
 * Keep every page that wasn't selected for deletion.
 */
class RemovePages extends PageProcessor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        $count = $this->pageCount($inputs, $options);
        $remove = array_map('intval', $options['pages'] ?? []);
        $keep = array_values(array_diff(range(1, $count), $remove));

        if (! $keep) {
            throw PdfToolException::forUser('You can’t remove every page. Leave at least one.');
        }

        $this->copyPages($inputs[0], $keep, $output);
        $removed = $count - count($keep);

        return ['meta' => ['summary' => "Removed {$this->pages($removed)}; {$this->pages(count($keep))} left."]];
    }
}
