<?php

namespace Tests\Feature\Tools;

use App\Livewire\PdfTool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\PdfFactory;
use Tests\TestCase;
use ZipArchive;

class OrganizeToolsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    private function requireBinary(string $binary): void
    {
        if (Process::run(['which', $binary])->failed()) {
            $this->markTestSkipped("{$binary} is not installed.");
        }
    }

    /**
     * A PDF whose pages read "Page 1", "Page 2", ...
     */
    private function pdf(int $pages, string $name = 'report.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            PdfFactory::withText(array_map(fn ($n) => "Page {$n}", range(1, $pages))),
        );
    }

    private function tool(string $slug, UploadedFile ...$files): Testable
    {
        $this->requireBinary('qpdf');

        return Livewire::test(PdfTool::class, ['tool' => $slug])
            ->set('uploads', $files)
            ->assertHasNoErrors();
    }

    private function resultPath(Testable $component): string
    {
        return Storage::disk('local')->path($component->get('result.path'));
    }

    /**
     * Text and rotation of each page, e.g. [['Page 3', 90], ...].
     *
     * @return array<int, array{string, int}>
     */
    private function pages(string $path): array
    {
        $script = 'import fitz, json, sys; print(json.dumps([[p.get_text().strip(), p.rotation] for p in fitz.open(sys.argv[1])]))';
        $result = Process::run([config('pdf.binaries.python'), '-c', $script, $path]);
        $this->assertTrue($result->successful(), $result->errorOutput());

        return json_decode($result->output(), true);
    }

    /**
     * @return array<int, string>
     */
    private function texts(string $path): array
    {
        return array_column($this->pages($path), 0);
    }

    /**
     * Page texts of each PDF in a ZIP, keyed by entry name, in ZIP order.
     *
     * @return array<string, array<int, string>>
     */
    private function zipTexts(string $path): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            $file = tempnam(sys_get_temp_dir(), 'zip');
            file_put_contents($file, $zip->getFromIndex($i));
            $entries[$name] = $this->texts($file);
            unlink($file);
        }

        $zip->close();

        return $entries;
    }

    public function test_tool_pages_render(): void
    {
        foreach (['split-pdf', 'remove-pages', 'extract-pages', 'organize-pdf', 'scan-to-pdf'] as $slug) {
            $this->get(route('tools.show', $slug))->assertOk()->assertSee('Drop');
        }
    }

    public function test_split_by_ranges_returns_a_zip_of_pdfs(): void
    {
        $component = $this->tool('split-pdf', $this->pdf(9))
            ->set('options.ranges', '1-3, 4-6, 9')
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.name', 'report_split.zip')
            ->assertSet('result.meta.summary', 'Split into 3 PDFs.');

        $this->assertSame([
            'report_1-3.pdf' => ['Page 1', 'Page 2', 'Page 3'],
            'report_4-6.pdf' => ['Page 4', 'Page 5', 'Page 6'],
            'report_9.pdf' => ['Page 9'],
        ], $this->zipTexts($this->resultPath($component)));

        $component->call('download')->assertFileDownloaded('report_split.zip');
    }

    public function test_split_open_range_runs_to_the_end(): void
    {
        $component = $this->tool('split-pdf', $this->pdf(5))
            ->set('options.ranges', '1, 3-')
            ->call('process')
            ->assertHasNoErrors();

        $this->assertSame([
            'report_1.pdf' => ['Page 1'],
            'report_3-5.pdf' => ['Page 3', 'Page 4', 'Page 5'],
        ], $this->zipTexts($this->resultPath($component)));
    }

    public function test_split_every_n_pages(): void
    {
        $component = $this->tool('split-pdf', $this->pdf(5))
            ->set('options.mode', 'every')
            ->set('options.every', 2)
            ->call('process')
            ->assertHasNoErrors();

        $this->assertSame([
            'report_1-2.pdf' => ['Page 1', 'Page 2'],
            'report_3-4.pdf' => ['Page 3', 'Page 4'],
            'report_5.pdf' => ['Page 5'],
        ], $this->zipTexts($this->resultPath($component)));
    }

    public function test_split_every_page(): void
    {
        $component = $this->tool('split-pdf', $this->pdf(3))
            ->set('options.mode', 'all')
            ->call('process')
            ->assertHasNoErrors();

        $this->assertSame([
            'report_1.pdf' => ['Page 1'],
            'report_2.pdf' => ['Page 2'],
            'report_3.pdf' => ['Page 3'],
        ], $this->zipTexts($this->resultPath($component)));
    }

    public function test_split_into_one_part_returns_a_pdf(): void
    {
        $component = $this->tool('split-pdf', $this->pdf(4))
            ->set('options.ranges', '2-3')
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.name', 'report_split.pdf')
            ->assertSet('result.pages', 2);

        $this->assertSame(['Page 2', 'Page 3'], $this->texts($this->resultPath($component)));
    }

    public function test_split_rejects_bad_ranges(): void
    {
        $component = $this->tool('split-pdf', $this->pdf(4));

        $component->set('options.ranges', '1-3, 7')->call('process')
            ->assertHasErrors('process')
            ->assertSee('Page 7 doesn’t exist; this PDF has 4 pages.')
            ->assertSet('result', null);

        $component->set('options.ranges', 'first two')->call('process')
            ->assertSee('isn’t a page range');

        $component->set('options.ranges', '')->call('process')
            ->assertHasErrors('options.ranges');
    }

    public function test_remove_pages_keeps_the_rest_in_order(): void
    {
        $component = $this->tool('remove-pages', $this->pdf(5))
            ->set('options.pages', [2, 4])
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.name', 'report_removed.pdf')
            ->assertSet('result.pages', 3)
            ->assertSet('result.meta.summary', 'Removed 2 pages; 3 pages left.');

        $this->assertSame(['Page 1', 'Page 3', 'Page 5'], $this->texts($this->resultPath($component)));
    }

    public function test_remove_pages_refuses_to_remove_every_page(): void
    {
        $this->tool('remove-pages', $this->pdf(2))
            ->set('options.pages', [1, 2])
            ->call('process')
            ->assertHasErrors('process')
            ->assertSee('You can’t remove every page.')
            ->assertSet('result', null);
    }

    public function test_remove_pages_needs_a_selection(): void
    {
        $this->tool('remove-pages', $this->pdf(2))
            ->call('process')
            ->assertHasErrors('options.pages');
    }

    public function test_extract_pages_into_one_pdf(): void
    {
        $component = $this->tool('extract-pages', $this->pdf(6))
            ->set('options.pages', [5, 2, 3])
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.name', 'report_extracted.pdf')
            ->assertSet('result.pages', 3);

        $this->assertSame(['Page 2', 'Page 3', 'Page 5'], $this->texts($this->resultPath($component)));
    }

    public function test_extract_pages_as_separate_pdfs(): void
    {
        $component = $this->tool('extract-pages', $this->pdf(6))
            ->set('options.pages', [1, 4])
            ->set('options.as', 'separate')
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.name', 'report_extracted.zip');

        $this->assertSame([
            'report_1.pdf' => ['Page 1'],
            'report_4.pdf' => ['Page 4'],
        ], $this->zipTexts($this->resultPath($component)));
    }

    public function test_extract_pages_rejects_missing_pages(): void
    {
        $this->tool('extract-pages', $this->pdf(2))
            ->set('options.pages', [3])
            ->call('process')
            ->assertHasErrors('options.pages.0');
    }

    public function test_organize_reorders_duplicates_deletes_and_rotates(): void
    {
        $component = $this->tool('organize-pdf', $this->pdf(4))
            ->set('options.pages', [
                ['page' => 3, 'rotation' => 0],
                ['page' => 1, 'rotation' => 90],
                ['page' => 1, 'rotation' => 0],
                ['page' => 2, 'rotation' => 270],
            ])
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.name', 'report_organized.pdf')
            ->assertSet('result.pages', 4);

        $this->assertSame([
            ['Page 3', 0],
            ['Page 1', 90],
            ['Page 1', 0],
            ['Page 2', 270],
        ], $this->pages($this->resultPath($component)));
    }

    public function test_organize_adds_to_existing_rotation(): void
    {
        $rotated = PdfFactory::withText(['Page 1', 'Page 2']);
        $source = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($source, $rotated);
        Process::run(['qpdf', $source, '--rotate=+90:1', '--replace-input'])->throw();

        $component = $this->tool('organize-pdf', UploadedFile::fake()->createWithContent('turned.pdf', file_get_contents($source)))
            ->set('options.pages', [['page' => 1, 'rotation' => 90], ['page' => 2, 'rotation' => 0]])
            ->call('process')
            ->assertHasNoErrors();
        unlink($source);

        $this->assertSame([['Page 1', 180], ['Page 2', 0]], $this->pages($this->resultPath($component)));
    }

    public function test_organize_rejects_invalid_rotation(): void
    {
        $this->tool('organize-pdf', $this->pdf(2))
            ->set('options.pages', [['page' => 1, 'rotation' => 45]])
            ->call('process')
            ->assertHasErrors('options.pages.0.rotation');
    }

    private function scan(string $name, string $text, bool $alpha = false): UploadedFile
    {
        $image = imagecreatetruecolor(1240, 1754);
        imagesavealpha($image, $alpha);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        $font = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
        is_file($font)
            ? imagettftext($image, 64, 0, 120, 300, imagecolorallocate($image, 0, 0, 0), $font, $text)
            : imagestring($image, 5, 120, 300, $text, imagecolorallocate($image, 0, 0, 0));

        if ($alpha) {
            imagefilledrectangle($image, 0, 1600, 1240, 1754, imagecolorallocatealpha($image, 0, 0, 0, 127));
        }

        ob_start();
        str_ends_with($name, '.png') ? imagepng($image) : imagejpeg($image, null, 90);

        return UploadedFile::fake()->createWithContent($name, ob_get_clean());
    }

    public function test_scan_to_pdf_makes_searchable_pages(): void
    {
        $this->requireBinary('img2pdf');
        $this->requireBinary('ocrmypdf');

        $component = $this->tool('scan-to-pdf', $this->scan('one.jpg', 'Invoice number'), $this->scan('two.png', 'Delivery receipt', alpha: true))
            ->set('options.pageSize', 'a4')
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.name', 'scan.pdf')
            ->assertSet('result.pages', 2);

        $texts = $this->texts($this->resultPath($component));
        $this->assertStringContainsString('Invoice', $texts[0]);
        $this->assertStringContainsString('Delivery', $texts[1]);
    }

    public function test_scan_to_pdf_without_ocr_has_no_text(): void
    {
        $this->requireBinary('img2pdf');

        $component = $this->tool('scan-to-pdf', $this->scan('one.jpg', 'Invoice number'))
            ->set('options.ocr', false)
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.pages', 1);

        $this->assertSame([''], $this->texts($this->resultPath($component)));
    }

    public function test_scan_to_pdf_only_takes_images(): void
    {
        Livewire::test(PdfTool::class, ['tool' => 'scan-to-pdf'])
            ->set('uploads', [$this->pdf(1)])
            ->assertHasErrors('uploads.0')
            ->assertSet('files', []);
    }
}
