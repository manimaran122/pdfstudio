<?php

namespace Tests\Feature;

use App\Support\PdfWorkspace;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\PdfFactory;
use Tests\TestCase;

class PageImageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function workspaceWithPdf(): array
    {
        $workspace = PdfWorkspace::create();
        $file = Str::random(12);
        Storage::disk('local')->put(PdfWorkspace::path($workspace, "{$file}.pdf"), PdfFactory::withText(['One', 'Two']));

        return [$workspace, $file];
    }

    public function test_pages_of_your_own_workspace_render_as_jpg(): void
    {
        [$workspace, $file] = $this->workspaceWithPdf();

        $response = $this->get(route('workspace.page', [$workspace, $file, 2, 'thumb']))->assertOk();

        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertSame(240, getimagesize($response->getFile()->getPathname())[1]);
    }

    public function test_other_sessions_cannot_see_your_pages(): void
    {
        [$workspace, $file] = $this->workspaceWithPdf();
        $this->flushSession();

        $this->get(route('workspace.page', [$workspace, $file, 1, 'thumb']))->assertForbidden();
    }

    public function test_bad_page_size_or_file_is_not_found(): void
    {
        [$workspace, $file] = $this->workspaceWithPdf();

        $this->get(route('workspace.page', [$workspace, $file, 1, 'huge']))->assertNotFound();
        $this->get(route('workspace.page', [$workspace, 'AAAAAAAAAAAA', 1, 'thumb']))->assertNotFound();
        $this->get(route('workspace.page', [$workspace, $file, 9, 'thumb']))->assertNotFound();
    }
}
