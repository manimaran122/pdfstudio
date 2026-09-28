<?php

namespace App\Services\Pdf\Processors\ConvertFrom;

use App\Services\Pdf\PdfToolException;
use App\Services\Pdf\Processors\Processor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * One slide per page with LibreOffice's PDF import (text boxes, shapes and
 * images stay separate, editable objects).
 */
class PdfToPowerPoint extends Processor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        // A private profile per run, as in OfficeToPdf: LibreOffice refuses
        // to share one profile between concurrent processes.
        $work = storage_path('app/libreoffice/'.Str::random(16));
        $outDir = $work.'/out';
        File::ensureDirectoryExists($outDir);

        try {
            $this->run([
                $this->binary('soffice'),
                '-env:UserInstallation=file://'.$work.'/profile',
                '--headless', '--norestore', '--nolockcheck',
                '--infilter=impress_pdf_import',
                '--convert-to', 'pptx',
                '--outdir', $outDir,
                $inputs[0],
            ], env: ['HOME' => $work]);

            $converted = $outDir.'/'.pathinfo($inputs[0], PATHINFO_FILENAME).'.pptx';
            $slides = is_file($converted) ? $this->slideCount($converted) : 0;

            if ($slides === 0) {
                throw new PdfToolException('LibreOffice produced no slides.');
            }

            rename($converted, $output);
        } finally {
            File::deleteDirectory($work);
        }

        return ['meta' => ['summary' => "Created {$slides} ".Str::plural('slide', $slides).', one per page.']];
    }

    /**
     * Slides in the deck; 0 when the file isn't a readable PPTX.
     */
    private function slideCount(string $path): int
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return 0;
        }

        $count = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (preg_match('#^ppt/slides/slide\d+\.xml$#', $zip->getNameIndex($i))) {
                $count++;
            }
        }

        $zip->close();

        return $count;
    }
}
