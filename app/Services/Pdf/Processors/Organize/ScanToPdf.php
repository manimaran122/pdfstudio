<?php

namespace App\Services\Pdf\Processors\Organize;

use App\Services\Pdf\Processors\ImagesToPdf;
use App\Services\Pdf\Processors\Processor;

/**
 * Photos or scans → one PDF (via ImagesToPdf), then optionally OCR so the
 * text can be searched and copied. OCR also straightens crooked photos
 * and turns sideways pages upright.
 */
class ScanToPdf extends Processor
{
    public function __construct(private ImagesToPdf $images) {}

    public function process(array $inputs, string $output, array $options): ?array
    {
        $flattened = array_filter(array_map($this->flatten(...), $inputs));

        try {
            $sources = array_replace($inputs, $flattened);

            if (empty($options['ocr'])) {
                $this->images->process($sources, $output, $options);

                return null;
            }

            $plain = dirname($output).'/scan-'.bin2hex(random_bytes(4)).'.pdf';

            try {
                $this->images->process($sources, $plain, $options);
                $this->run([
                    $this->binary('ocrmypdf'), '--quiet',
                    '--language', config('pdf.ocr_language'),
                    '--deskew', '--rotate-pages',
                    $plain, $output,
                ]);
            } finally {
                @unlink($plain);
            }
        } finally {
            array_map('unlink', $flattened);
        }

        $this->assertOutput($output);

        return ['meta' => ['summary' => 'The text in your scan is now searchable.']];
    }

    /**
     * img2pdf refuses PNGs with transparency (common from screenshots and
     * some scanner apps), so put those on white first. Returns the path of
     * the flattened copy, or null when the image can be used as is.
     */
    private function flatten(string $input): ?string
    {
        $info = @getimagesize($input);

        if (($info[2] ?? null) !== IMAGETYPE_PNG || ! in_array(ord(file_get_contents($input, false, null, 25, 1)), [4, 6], true)) {
            return null;
        }

        $image = imagecreatefrompng($input);
        $canvas = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        $path = $input.'.flat.png';
        imagepng($canvas, $path);

        return $path;
    }
}
