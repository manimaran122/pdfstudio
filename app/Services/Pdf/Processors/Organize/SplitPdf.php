<?php

namespace App\Services\Pdf\Processors\Organize;

use App\Services\Pdf\PdfToolException;

/**
 * Split by custom ranges, every N pages, or into single pages. Several parts
 * come back as a ZIP; a single part is returned as a plain PDF.
 */
class SplitPdf extends PageProcessor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        $count = $this->pageCount($inputs, $options);

        $ranges = match ($options['mode'] ?? 'ranges') {
            'every' => $this->chunks($count, max(1, (int) ($options['every'] ?? 1))),
            'all' => $this->chunks($count, 1),
            default => $this->parse((string) ($options['ranges'] ?? ''), $count),
        };

        if (count($ranges) === 1) {
            $this->copyPages($inputs[0], range(...$ranges[0]), $output);

            return ['meta' => ['summary' => 'That’s a single part, so you get one PDF instead of a ZIP.']];
        }

        $base = $this->baseName($options);
        $groups = [];

        foreach ($ranges as [$start, $end]) {
            $label = $start === $end ? (string) $start : "{$start}-{$end}";
            $groups["{$base}_{$label}.pdf"] = range($start, $end);
        }

        return [
            'file' => $this->zipGroups($inputs[0], $groups, $output),
            'meta' => ['summary' => 'Split into '.count($groups).' PDFs.'],
        ];
    }

    /**
     * @return array<int, array{int, int}>
     */
    private function chunks(int $count, int $size): array
    {
        return array_map(fn (int $start) => [$start, min($count, $start + $size - 1)], range(1, $count, $size));
    }

    /**
     * Parse "1-3, 4-6, 9, 10-" into [start, end] pairs, in the order given.
     * Repeated ranges are dropped, since they would produce the same file.
     *
     * @return array<int, array{int, int}>
     */
    private function parse(string $text, int $count): array
    {
        $ranges = [];

        foreach (preg_split('/[,;]+/', $text) as $part) {
            $part = preg_replace('/\s+/', '', $part);

            if ($part === '') {
                continue;
            }

            if (! preg_match('/^(\d+)(?:(-)(\d*))?$/', $part, $match)) {
                throw PdfToolException::forUser("“{$part}” isn’t a page range. Use page numbers and ranges like 1-3, 5.");
            }

            $start = (int) $match[1];
            $end = isset($match[2]) ? ((($match[3] ?? '') === '') ? $count : (int) $match[3]) : $start;

            foreach ([$start, $end] as $page) {
                if ($page < 1 || $page > $count) {
                    throw PdfToolException::forUser("Page {$page} doesn’t exist; this PDF has {$this->pages($count)}.");
                }
            }

            if ($start > $end) {
                throw PdfToolException::forUser("“{$part}” runs backwards. Write the lower page first, like {$end}-{$start}.");
            }

            $ranges["{$start}-{$end}"] = [$start, $end];
        }

        if (! $ranges) {
            throw PdfToolException::forUser('Enter the page ranges to split out, like 1-3, 4-6.');
        }

        return array_values($ranges);
    }
}
