<?php

namespace App\Services\Pdf\Processors\Organize;

use App\Services\Pdf\Pdftk;
use App\Services\Pdf\PdfToolException;
use App\Services\Pdf\Processors\Processor;
use ZipArchive;

/**
 * Shared page plumbing for the Organize tools. Pages are copied with qpdf,
 * which keeps links, annotations and form fields on the pages it copies.
 */
abstract class PageProcessor extends Processor
{
    /**
     * Pages in the first input (known from upload, counted again if not).
     */
    protected function pageCount(array $inputs, array $options): int
    {
        return (int) ($options['_pages'][0] ?? app(Pdftk::class)->pageCount($inputs[0]));
    }

    /**
     * Write the given pages of $input to $output, in order. Each page is a
     * page number or ['page' => n, 'rotation' => degrees clockwise], added
     * to the page's own rotation. Repeated pages become separate copies.
     *
     * @param  array<int, int|array{page: int, rotation?: int}>  $pages
     */
    protected function copyPages(string $input, array $pages, string $output): void
    {
        $numbers = [];
        $turns = [];

        foreach (array_values($pages) as $i => $page) {
            $numbers[] = (int) (is_array($page) ? $page['page'] : $page);
            $rotation = is_array($page) ? ((int) ($page['rotation'] ?? 0)) % 360 : 0;

            if ($rotation) {
                $turns[$rotation][] = $i + 1;
            }
        }

        $command = [$this->binary('qpdf'), '--empty', '--pages', $input, implode(',', $numbers), '--'];

        foreach ($turns as $rotation => $positions) {
            $command[] = "--rotate=+{$rotation}:".implode(',', $positions);
        }

        // Exit code 3 means "succeeded with warnings", common with scanned PDFs.
        $this->run([...$command, $output], [0, 3]);
        $this->assertOutput($output);
    }

    /**
     * Write each [page numbers] group to its own PDF inside a ZIP next to
     * $output, and return the ZIP's path.
     *
     * @param  array<string, array<int, int>>  $groups  File name => pages.
     */
    protected function zipGroups(string $input, array $groups, string $output): string
    {
        $dir = dirname($output).'/parts-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $zipPath = preg_replace('/\.[^.]+$/', '.zip', $output);
        $zip = new ZipArchive;

        try {
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new PdfToolException('Unable to create the ZIP file.');
            }

            foreach (array_keys($groups) as $i => $name) {
                $part = "{$dir}/{$i}.pdf";
                $this->copyPages($input, $groups[$name], $part);
                $zip->addFile($part, $name);
            }

            $zip->close();
        } finally {
            array_map('unlink', glob("{$dir}/*.pdf"));
            @rmdir($dir);
        }

        $this->assertOutput($zipPath);

        return $zipPath;
    }

    /**
     * The upload's name without extension, for naming files inside a ZIP.
     */
    protected function baseName(array $options): string
    {
        $base = pathinfo($options['_names'][0] ?? 'document', PATHINFO_FILENAME);

        return trim((string) preg_replace('/[^\w\-. ()]+/u', '_', $base), ' ._') ?: 'document';
    }

    /**
     * "3 pages" / "1 page".
     */
    protected function pages(int $count): string
    {
        return $count.' '.($count === 1 ? 'page' : 'pages');
    }
}
