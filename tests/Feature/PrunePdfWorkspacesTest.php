<?php

namespace Tests\Feature;

use App\Support\PdfWorkspace;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrunePdfWorkspacesTest extends TestCase
{
    public function test_only_expired_workspaces_are_deleted(): void
    {
        Storage::fake('local');
        $disk = Storage::disk('local');

        $disk->put(PdfWorkspace::path('old', 'a.pdf'), 'x');
        $disk->put(PdfWorkspace::path('new', 'a.pdf'), 'x');
        touch($disk->path(PdfWorkspace::path('old', 'a.pdf')), now()->subHours(2)->getTimestamp());

        $this->artisan('pdf:prune')->assertSuccessful();

        $disk->assertMissing(PdfWorkspace::path('old', 'a.pdf'));
        $disk->assertExists(PdfWorkspace::path('new', 'a.pdf'));
    }
}
