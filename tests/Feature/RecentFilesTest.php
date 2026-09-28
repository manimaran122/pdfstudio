<?php

namespace Tests\Feature;

use App\Livewire\PdfTool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\PdfFactory;
use Tests\TestCase;

class RecentFilesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function compress(string $name): Testable
    {
        return Livewire::test(PdfTool::class, ['tool' => 'compress-pdf'])
            ->set('uploads', [UploadedFile::fake()->createWithContent($name, PdfFactory::make(2))])
            ->call('process')
            ->assertHasNoErrors();
    }

    public function test_results_are_listed_and_downloadable(): void
    {
        $this->compress('first.pdf');
        $this->compress('second.pdf');

        $entries = session('pdf.recent');
        $this->assertSame(['second_compressed.pdf', 'first_compressed.pdf'], array_column($entries, 'name'));

        $this->get(route('home'))->assertSeeInOrder(['Recent files', 'second_compressed.pdf', 'first_compressed.pdf']);
        $this->get(route('recent'))->assertOk()->assertSee('Compress PDF')->assertSee('Files from this session');

        $this->get(route('recent.download', $entries[1]['id']))
            ->assertOk()
            ->assertDownload('first_compressed.pdf');
    }

    public function test_running_a_tool_twice_keeps_both_results(): void
    {
        $component = $this->compress('report.pdf');
        $first = $component->get('result.path');

        $component->call('process')->assertHasNoErrors();

        $this->assertNotSame($first, $component->get('result.path'));
        Storage::disk('local')->assertExists($first);
        $this->assertCount(2, session('pdf.recent'));
    }

    public function test_new_files_show_a_dot_until_the_page_is_opened(): void
    {
        $this->get(route('home'))->assertDontSee('(new)');

        $this->compress('report.pdf');
        $this->get(route('home'))->assertSee('(new)');

        $this->get(route('recent'));
        $this->get(route('home'))->assertDontSee('(new)');
    }

    public function test_other_sessions_and_pruned_files_cannot_be_downloaded(): void
    {
        $this->compress('report.pdf');
        $entry = session('pdf.recent')[0];

        Storage::disk('local')->delete($entry['path']);
        $this->get(route('recent.download', $entry['id']))->assertNotFound();
        $this->get(route('recent'))->assertSee('Files you create will appear here');

        $this->flushSession();
        $this->get(route('recent.download', $entry['id']))->assertNotFound();
    }
}
