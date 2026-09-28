<?php

namespace App\Services\Pdf\Processors\ConvertFrom;

use App\Services\Pdf\PdfToolException;
use App\Services\Pdf\Processors\Processor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Renders every page (pdftoppm) or extracts the embedded images
 * (pdfimages) as JPGs. One image is returned as-is; more go in a ZIP.
 */
class PdfToJpg extends Processor
{
    private const JPEG_QUALITY = 90;

    public function process(array $inputs, string $output, array $options): ?array
    {
        $work = dirname($output).'/jpg-'.Str::random(8);
        File::ensureDirectoryExists($work);

        try {
            $images = ($options['mode'] ?? 'pages') === 'images'
                ? $this->extract($inputs[0], $work)
                : $this->render($inputs[0], $work, (int) ($options['quality'] ?? 150));

            if (! $images) {
                throw PdfToolException::forUser('This PDF has no embedded images. Choose “Convert pages” to save each page as a JPG instead.');
            }

            $file = $this->package($images, $output, $this->baseName($options));
        } finally {
            File::deleteDirectory($work);
        }

        $count = count($images);
        $summary = ($options['mode'] ?? 'pages') === 'images'
            ? "Extracted {$count} ".Str::plural('image', $count).'.'
            : "Converted {$count} ".Str::plural('page', $count).' to JPG.';

        return ['file' => $file, 'meta' => ['summary' => $summary]];
    }

    /**
     * @return array<int, string>
     */
    private function render(string $input, string $work, int $dpi): array
    {
        $dpi = in_array($dpi, [72, 150, 300], true) ? $dpi : 150;

        $this->run([
            $this->binary('pdftoppm'), '-jpeg', '-jpegopt', 'quality='.self::JPEG_QUALITY,
            '-r', (string) $dpi, $input, $work.'/page',
        ]);

        return $this->sorted(glob($work.'/page-*.jpg'));
    }

    /**
     * Embedded images, skipping soft masks and tiny decorations. JPEG data
     * is copied untouched; everything else is re-encoded as JPG.
     *
     * @return array<int, string>
     */
    private function extract(string $input, string $work): array
    {
        $list = $this->run([$this->binary('pdfimages'), '-list', $input])->output();
        $this->run([$this->binary('pdfimages'), '-j', '-png', $input, $work.'/img']);

        $images = [];

        foreach (array_slice(explode("\n", trim($list)), 2) as $row) {
            // page num type width height ...
            $cols = preg_split('/\s+/', trim($row));

            if (count($cols) < 5 || $cols[2] !== 'image' || (int) $cols[3] < 16 || (int) $cols[4] < 16) {
                continue;
            }

            $file = glob($work.'/img-'.sprintf('%03d', (int) $cols[1]).'.*')[0] ?? null;

            if ($file && ($jpg = $this->toJpeg($file))) {
                $images[] = $jpg;
            }
        }

        return $images;
    }

    private function toJpeg(string $file): ?string
    {
        if (str_ends_with($file, '.jpg')) {
            return $file;
        }

        $source = @imagecreatefrompng($file);

        if (! $source) {
            return null;
        }

        // Flatten transparency onto white; JPG has no alpha channel.
        $canvas = imagecreatetruecolor(imagesx($source), imagesy($source));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));
        $jpg = preg_replace('/\.png$/', '.jpg', $file);
        imagejpeg($canvas, $jpg, self::JPEG_QUALITY);

        return $jpg;
    }

    /**
     * @param  array<int, string>  $images
     */
    private function package(array $images, string $output, string $base): string
    {
        if (count($images) === 1) {
            $single = preg_replace('/\.[^.]+$/', '.jpg', $output);
            rename($images[0], $single);

            return $single;
        }

        $zip = new ZipArchive;

        if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new PdfToolException('Could not create the ZIP file.');
        }

        $digits = max(2, strlen((string) count($images)));

        foreach (array_values($images) as $i => $image) {
            $zip->addFile($image, $base.'-'.str_pad((string) ($i + 1), $digits, '0', STR_PAD_LEFT).'.jpg');
            // Already compressed; deflating JPGs only costs time.
            $zip->setCompressionIndex($i, ZipArchive::CM_STORE);
        }

        $zip->close();
        $this->assertOutput($output);

        return $output;
    }

    /**
     * @param  array<int, string>  $files
     * @return array<int, string>
     */
    private function sorted(array $files): array
    {
        natsort($files);

        return array_values($files);
    }

    private function baseName(array $options): string
    {
        $name = pathinfo($options['_names'][0] ?? 'page', PATHINFO_FILENAME);

        return trim((string) preg_replace('/[^\w\-. ()]+/u', '_', $name), ' ._') ?: 'page';
    }
}
