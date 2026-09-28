<?php

namespace App\Services\Pdf;

use Illuminate\Support\Facades\Process;

class Pdftk
{
    /**
     * pdftk page-rotation suffixes, relative to each page's current rotation.
     */
    private const ROTATIONS = [0 => '', 90 => 'right', 180 => 'down', 270 => 'left'];

    public function __construct(private string $binary = 'pdftk') {}

    /**
     * Count the pages in a PDF, failing if pdftk cannot open it
     * (corrupt, not a PDF, or password protected).
     */
    public function pageCount(string $path): int
    {
        $result = Process::run([$this->binary, $path, 'dump_data_utf8']);

        if ($result->failed() || ! preg_match('/^NumberOfPages: (\d+)$/m', $result->output(), $match)) {
            throw new PdfToolException(trim($result->errorOutput()) ?: 'Unable to read PDF.');
        }

        return (int) $match[1];
    }

    /**
     * Merge PDFs into $output in the given order.
     *
     * @param  array<int, array{path: string, rotation?: int}>  $parts
     * @param  array<int, array{title: string, page: int}>  $bookmarks
     */
    public function merge(array $parts, string $output, array $bookmarks = []): void
    {
        $handles = [];
        $ranges = [];

        foreach (array_values($parts) as $i => $part) {
            $handle = $this->handle($i);
            $handles[] = "{$handle}={$part['path']}";
            $ranges[] = $handle.'1-end'.(self::ROTATIONS[$part['rotation'] ?? 0] ?? '');
        }

        $target = $bookmarks ? $output.'.tmp' : $output;

        $this->run([$this->binary, ...$handles, 'cat', ...$ranges, 'output', $target]);

        if (! $bookmarks) {
            return;
        }

        $info = $output.'.info';
        file_put_contents($info, $this->bookmarkData($bookmarks));

        try {
            $this->run([$this->binary, $target, 'update_info_utf8', $info, 'output', $output]);
        } finally {
            @unlink($info);
            @unlink($target);
        }
    }

    /**
     * pdftk input handles must be uppercase letters: A..Z, AA..AZ, ...
     */
    private function handle(int $index): string
    {
        $handle = '';

        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $handle = chr(65 + ($n - 1) % 26).$handle;
        }

        return $handle;
    }

    /**
     * @param  array<int, array{title: string, page: int}>  $bookmarks
     */
    private function bookmarkData(array $bookmarks): string
    {
        $data = '';

        foreach ($bookmarks as $bookmark) {
            $title = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $bookmark['title']));
            $data .= "BookmarkBegin\nBookmarkTitle: {$title}\nBookmarkLevel: 1\nBookmarkPageNumber: {$bookmark['page']}\n";
        }

        return $data;
    }

    /**
     * @param  array<int, string>  $command
     */
    private function run(array $command): void
    {
        $result = Process::timeout(120)->run($command);

        if ($result->failed()) {
            throw new PdfToolException(trim($result->errorOutput()) ?: 'pdftk failed.');
        }
    }
}
