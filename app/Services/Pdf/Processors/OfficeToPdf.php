<?php

namespace App\Services\Pdf\Processors;

use App\Services\Pdf\PdfToolException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Converts Word, PowerPoint, Excel and HTML files with headless LibreOffice.
 */
class OfficeToPdf extends Processor
{
    public function __construct(private string $filter = 'pdf') {}

    public static function html(): self
    {
        return new self('pdf:writer_web_pdf_Export');
    }

    public function process(array $inputs, string $output, array $options): ?array
    {
        // A private profile per run lets conversions run concurrently;
        // LibreOffice refuses to share one profile between processes.
        $work = storage_path('app/libreoffice/'.Str::random(16));
        $outDir = $work.'/out';
        File::ensureDirectoryExists($outDir);

        try {
            $this->run([
                $this->binary('soffice'),
                '-env:UserInstallation=file://'.$work.'/profile',
                '--headless', '--norestore', '--nolockcheck',
                '--convert-to', $this->filter,
                '--outdir', $outDir,
                $inputs[0],
            ], env: ['HOME' => $work]);

            $converted = $outDir.'/'.pathinfo($inputs[0], PATHINFO_FILENAME).'.pdf';

            if (! is_file($converted)) {
                throw new PdfToolException('LibreOffice could not open the file.');
            }

            rename($converted, $output);
            $this->assertOutput($output);
        } finally {
            File::deleteDirectory($work);
        }

        return null;
    }
}
