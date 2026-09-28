<?php

namespace Tests\Feature;

use App\Livewire\MergePdf;
use App\Services\Pdf\Pdftk;
use App\Support\PdfWorkspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Support\PdfFactory;
use Tests\TestCase;

class MergePdfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (Process::run(['which', config('pdf.pdftk')])->failed()) {
            $this->markTestSkipped('pdftk is not installed.');
        }

        Storage::fake('local');
    }

    private function pdf(string $name, int $pages): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, PdfFactory::make($pages));
    }

    public function test_uploaded_files_are_counted_and_listed(): void
    {
        Livewire::test(MergePdf::class)
            ->set('uploads', [$this->pdf('b.pdf', 2), $this->pdf('a.pdf', 3)])
            ->assertHasNoErrors()
            ->assertSet('files.0.name', 'b.pdf')
            ->assertSet('files.0.pages', 2)
            ->assertSet('files.1.pages', 3)
            ->assertSet('uploads', [])
            ->assertSee('2 files · drag to reorder')
            ->assertSee('Merge 2 files');
    }

    public function test_unreadable_pdf_is_rejected(): void
    {
        Livewire::test(MergePdf::class)
            ->set('uploads', [UploadedFile::fake()->createWithContent('broken.pdf', '%PDF-1.4 garbage')])
            ->assertHasErrors('uploads')
            ->assertSet('files', []);
    }

    public function test_non_pdf_is_rejected(): void
    {
        Livewire::test(MergePdf::class)
            ->set('uploads', [UploadedFile::fake()->create('notes.txt', 1, 'text/plain')])
            ->assertHasErrors('uploads.0')
            ->assertSet('files', []);
    }

    public function test_files_can_be_reordered_rotated_and_removed(): void
    {
        $component = Livewire::test(MergePdf::class)
            ->set('uploads', [$this->pdf('one.pdf', 1), $this->pdf('two.pdf', 1), $this->pdf('three.pdf', 1)]);

        [$one, $two, $three] = array_column($component->get('files'), 'id');

        $component->call('move', $three, $one)
            ->assertSet('files.0.name', 'three.pdf')
            ->call('nudge', $three, 1)
            ->assertSet('files.1.name', 'three.pdf')
            ->call('sortByName')
            ->assertSet('files.0.name', 'one.pdf')
            ->call('rotate', $two)
            ->assertSet('files.2.rotation', 90)
            ->call('remove', $one)
            ->assertCount('files', 2);

        Storage::disk('local')->assertMissing(PdfWorkspace::path($component->get('workspace'), "{$one}.pdf"));
    }

    public function test_files_are_merged_with_bookmarks_and_downloaded(): void
    {
        $component = Livewire::test(MergePdf::class)
            ->set('uploads', [$this->pdf('cover.pdf', 2), $this->pdf('contract.pdf', 3)])
            ->set('outputName', '../Final report')
            ->call('merge')
            ->assertHasNoErrors()
            ->assertSet('result.name', 'Final report.pdf')
            ->assertSet('result.pages', 5)
            ->assertSee('Your file is ready');

        $output = Storage::disk('local')->path($component->get('result.path'));
        $data = Process::run([config('pdf.pdftk'), $output, 'dump_data_utf8'])->output();

        $this->assertStringContainsString('NumberOfPages: 5', $data);
        $this->assertStringContainsString("BookmarkTitle: contract.pdf\nBookmarkLevel: 1\nBookmarkPageNumber: 3", $data);

        $component->call('download')->assertFileDownloaded('Final report.pdf');
    }

    public function test_merge_requires_two_files(): void
    {
        Livewire::test(MergePdf::class)
            ->set('uploads', [$this->pdf('only.pdf', 1)])
            ->call('merge')
            ->assertHasErrors('merge')
            ->assertSet('result', null);
    }

    public function test_workspace_is_locked_from_the_client(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(MergePdf::class)->set('workspace', '../../');
    }

    public function test_handles_are_generated_past_z(): void
    {
        $method = new \ReflectionMethod(Pdftk::class, 'handle');

        $this->assertSame('A', $method->invoke(new Pdftk, 0));
        $this->assertSame('Z', $method->invoke(new Pdftk, 25));
        $this->assertSame('AA', $method->invoke(new Pdftk, 26));
    }
}
