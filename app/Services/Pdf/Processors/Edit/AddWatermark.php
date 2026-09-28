<?php

namespace App\Services\Pdf\Processors\Edit;

use App\Services\Pdf\PdfToolException;
use App\Services\Pdf\Processors\Processor;
use GdImage;

class AddWatermark extends Processor
{
    /** Longest side of the prepared watermark image, in pixels. */
    private const MAX_IMAGE_SIDE = 2000;

    public function process(array $inputs, string $output, array $options): ?array
    {
        $type = $options['type'] ?? 'text';
        $payload = [
            'input' => $inputs[0],
            'output' => $output,
            'type' => $type,
            'opacity' => (float) ($options['opacity'] ?? 50),
            'rotation' => $options['rotation'] ?? 'diagonal',
            'position' => $options['position'] ?? 'center',
            'behind' => (bool) ($options['behind'] ?? false),
        ];
        $image = null;

        if ($type === 'image') {
            $path = $options['_assets'][$options['image'] ?? ''] ?? null;

            if (! $path || ! is_file($path)) {
                throw PdfToolException::forUser('Choose an image for the watermark.');
            }

            [$image, $ratio] = $this->prepareImage($path, $payload['opacity'] / 100, $payload['rotation'] === 'diagonal');
            $payload += ['image' => $image, 'ratio' => $ratio, 'scale' => (float) ($options['scale'] ?? 50)];
        } else {
            $text = trim((string) ($options['text'] ?? ''));

            if ($text === '') {
                throw PdfToolException::forUser('Enter the watermark text.');
            }

            $payload += ['text' => $text, 'size' => (float) ($options['size'] ?? 48), 'color' => $options['color'] ?? '#808080'];
        }

        try {
            $this->python('watermark', $payload);
        } finally {
            if ($image) {
                @unlink($image);
            }
        }

        $this->assertOutput($output);

        return null;
    }

    /**
     * PyMuPDF 1.23 can't set an image's opacity, so bake it (and the
     * diagonal turn) into a temporary PNG.
     *
     * @return array{0: string, 1: float} PNG path and height / width ratio.
     */
    private function prepareImage(string $path, float $opacity, bool $diagonal): array
    {
        $source = @imagecreatefromstring((string) file_get_contents($path));

        if (! $source instanceof GdImage) {
            throw PdfToolException::forUser('The watermark image couldn’t be read.');
        }

        $image = $this->canvas(imagesx($source), imagesy($source), self::MAX_IMAGE_SIDE);
        imagecopyresampled($image, $source, 0, 0, 0, 0, imagesx($image), imagesy($image), imagesx($source), imagesy($source));

        if ($diagonal) {
            // Counter-clockwise, so it reads bottom-left to top-right like the text watermark.
            $rotated = imagerotate($image, 45, imagecolorallocatealpha($image, 0, 0, 0, 127));
            imagealphablending($rotated, false);
            imagesavealpha($rotated, true);
            $image = $rotated;
        }

        // Adds to every pixel's transparency (0 = opaque, 127 = clear).
        imagefilter($image, IMG_FILTER_COLORIZE, 0, 0, 0, (int) round(127 * (1 - $opacity)));

        $base = tempnam(sys_get_temp_dir(), 'watermark');
        $file = $base.'.png';
        @unlink($base);
        imagepng($image, $file);

        return [$file, imagesy($image) / max(1, imagesx($image))];
    }

    private function canvas(int $width, int $height, int $max): GdImage
    {
        $scale = min(1, $max / max($width, $height, 1));
        $canvas = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        return $canvas;
    }
}
