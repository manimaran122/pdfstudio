<?php

namespace App\Http\Controllers;

use App\Support\PdfWorkspace;
use App\Support\RecentFiles;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecentFilesController extends Controller
{
    public function index(): View
    {
        RecentFiles::markSeen();

        return view('tools.recent', ['files' => RecentFiles::all()]);
    }

    public function download(string $id): StreamedResponse
    {
        $entry = RecentFiles::find($id);
        abort_unless($entry, 404);

        return PdfWorkspace::disk()->download($entry['path'], $entry['name']);
    }
}
