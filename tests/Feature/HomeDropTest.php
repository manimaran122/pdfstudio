<?php

namespace Tests\Feature;

use App\Livewire\HomeDrop;
use App\Livewire\MergePdf;
use App\Livewire\PdfTool;
use App\Support\FileHandoff;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\PdfFactory;
use Tests\TestCase;

class HomeDropTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function pdf(string $name, int $pages = 1): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, PdfFactory::make($pages));
    }

    public function test_one_pdf_suggests_single_file_pdf_tools_most_used_first(): void
    {
        $component = Livewire::test(HomeDrop::class)
            ->set('uploads', [$this->pdf('report.pdf', 3)])
            ->assertSee('What do you want to do?')
            ->assertSee('report.pdf')
            ->assertSeeInOrder(['Split PDF', 'Compress PDF', 'PDF to Word'])
            ->assertDontSee('JPG to PDF');

        $this->assertNotContains('merge-pdf', FileHandoff::toolsFor($component->get('files')));
    }

    public function test_images_suggest_image_tools_and_mixed_files_suggest_nothing(): void
    {
        $jpg = UploadedFile::fake()->image('scan.jpg');

        Livewire::test(HomeDrop::class)
            ->set('uploads', [$jpg, UploadedFile::fake()->image('scan2.png')])
            ->assertSee('JPG to PDF')
            ->assertSee('Scan to PDF')
            ->assertDontSee('Compress PDF');

        Livewire::test(HomeDrop::class)
            ->set('uploads', [$this->pdf('a.pdf'), UploadedFile::fake()->image('b.jpg')])
            ->assertSee('These files can’t be used together.');
    }

    public function test_unreadable_pdfs_only_go_to_repair_and_unlock(): void
    {
        $component = Livewire::test(HomeDrop::class)
            ->set('uploads', [UploadedFile::fake()->createWithContent('broken.pdf', '%PDF-1.4 nonsense')]);

        $this->assertEqualsCanonicalizing(['repair-pdf', 'unlock-pdf'], FileHandoff::toolsFor($component->get('files')));
    }

    public function test_picking_a_tool_hands_the_files_over(): void
    {
        $drop = Livewire::test(HomeDrop::class)->set('uploads', [$this->pdf('report.pdf', 4)]);
        $workspace = $drop->get('workspace');

        $drop->call('pick', 'compress-pdf')->assertRedirectContains(route('tools.show', 'compress-pdf').'?from=');
        $token = session()->get('pdf.handoff') ? array_key_first(session()->get('pdf.handoff')) : null;

        $tool = Livewire::withQueryParams(['from' => $token])->test(PdfTool::class, ['tool' => 'compress-pdf'])
            ->assertSet('workspace', $workspace)
            ->assertSet('files.0.name', 'report.pdf')
            ->assertSet('files.0.pages', 4)
            ->call('process')
            ->assertHasNoErrors();

        // Tokens are single use.
        $this->assertNull(FileHandoff::take($token, 'compress-pdf'));
        $this->assertSame('report_compressed.pdf', $tool->get('result.name'));
    }

    public function test_several_pdfs_can_go_to_merge(): void
    {
        $drop = Livewire::test(HomeDrop::class)->set('uploads', [$this->pdf('a.pdf', 2), $this->pdf('b.pdf', 3)])
            ->assertSee('Merge PDF')
            ->assertSee('Compare PDF');

        $drop->call('pick', 'merge-pdf')->assertRedirectContains(route('tools.merge').'?from=');
        $token = array_key_first(session()->get('pdf.handoff'));

        Livewire::withQueryParams(['from' => $token])->test(MergePdf::class)
            ->assertCount('files', 2)
            ->call('merge')
            ->assertHasNoErrors()
            ->assertSet('result.pages', 5);
    }

    public function test_a_tool_that_does_not_fit_ignores_the_handoff(): void
    {
        $drop = Livewire::test(HomeDrop::class)->set('uploads', [$this->pdf('report.pdf')]);
        $drop->call('pick', 'jpg-to-pdf')->assertNoRedirect();

        $token = FileHandoff::put($drop->get('workspace'), $drop->get('files'));

        Livewire::withQueryParams(['from' => $token])->test(PdfTool::class, ['tool' => 'word-to-pdf'])
            ->assertSet('files', []);
    }
}
