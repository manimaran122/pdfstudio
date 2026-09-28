<?php

namespace App\Http\Controllers;

use App\Support\PdfWorkspace;
use Illuminate\Support\Facades\Process;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Renders one page of an uploaded PDF to JPG for thumbnails and the page
 * editor, caching the result inside the workspace.
 */
class PageImageController extends Controller
{
    private const SIZES = ['thumb' => 240, 'large' => 900];

    public function __invoke(string $workspace, string $file, int $page, string $size): BinaryFileResponse
    {
        abort_unless(PdfWorkspace::ownedBySession($workspace), 403);
        abort_unless(isset(self::SIZES[$size]) && preg_match('/^[A-Za-z0-9]{12}$/', $file) && $page >= 1, 404);

        $disk = PdfWorkspace::disk();
        $pdf = $disk->path(PdfWorkspace::path($workspace, "{$file}.pdf"));
        abort_unless(is_file($pdf), 404);

        $image = $disk->path(PdfWorkspace::path($workspace, "pages/{$file}-{$page}-{$size}.jpg"));

        if (! is_file($image)) {
            @mkdir(dirname($image), 0775, true);

            Process::timeout(60)->run([
                config('pdf.binaries.pdftoppm'), '-jpeg', '-jpegopt', 'quality=80',
                '-f', (string) $page, '-l', (string) $page,
                '-scale-to', (string) self::SIZES[$size], '-singlefile',
                $pdf, substr($image, 0, -4),
            ]);

            abort_unless(is_file($image), 404);
        }

        return response()->file($image, ['Cache-Control' => 'private, max-age=3600']);
    }
}
