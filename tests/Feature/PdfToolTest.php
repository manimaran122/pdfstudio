<?php

namespace Tests\Feature;

use App\Livewire\PdfTool;
use App\Support\PdfTools;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\PdfFactory;
use Tests\TestCase;

class PdfToolTest extends TestCase
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

    private function pdf(string $name = 'scan.pdf', int $pages = 2): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, PdfFactory::make($pages));
    }

    private function jpeg(string $name): UploadedFile
    {
        $image = imagecreatetruecolor(400, 300);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 5, 40, 40, 'Hello 12345', imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagejpeg($image);

        return UploadedFile::fake()->createWithContent($name, ob_get_clean());
    }

    public static function tools(): array
    {
        return [
            'compress' => ['compress-pdf', 'gs', fn (self $t) => [$t->pdf('report.pdf')], 'report_compressed.pdf', 2],
            'repair' => ['repair-pdf', 'qpdf', fn (self $t) => [UploadedFile::fake()->createWithContent('broken.pdf', substr(PdfFactory::make(2), 0, -40))], 'broken_repaired.pdf', 2],
            'ocr' => ['ocr-pdf', 'ocrmypdf', fn (self $t) => [$t->pdf('scan.pdf')], 'scan_ocr.pdf', 2],
            'jpg' => ['jpg-to-pdf', 'img2pdf', fn (self $t) => [$t->jpeg('a.jpg'), $t->jpeg('b.jpg')], 'images.pdf', 2],
            'word' => ['word-to-pdf', 'soffice', fn () => [UploadedFile::fake()->createWithContent('letter.rtf', '{\rtf1\ansi Hello world\par}')], 'letter.pdf', 1],
            'excel' => ['excel-to-pdf', 'soffice', fn () => [UploadedFile::fake()->createWithContent('budget.csv', "item,cost\npaper,4\n")], 'budget.pdf', 1],
            'html' => ['html-to-pdf', 'soffice', fn () => [UploadedFile::fake()->createWithContent('page.html', '<h1>Hello</h1><p>World</p>')], 'page.pdf', 1],
        ];
    }

    #[DataProvider('tools')]
    public function test_tool_produces_a_downloadable_pdf(string $tool, string $binary, \Closure $files, string $name, int $pages): void
    {
        $this->requireBinary($binary);

        $component = Livewire::test(PdfTool::class, ['tool' => $tool])
            ->set('uploads', $files($this))
            ->assertHasNoErrors()
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.name', $name)
            ->assertSet('result.pages', $pages)
            ->assertSee('Your file is ready');

        $output = Storage::disk('local')->get($component->get('result.path'));
        $this->assertStringStartsWith('%PDF', $output);

        $component->call('download')->assertFileDownloaded($name);
    }

    public function test_every_catalog_tool_page_renders(): void
    {
        // AI tools show a setup notice instead of the upload area without a key.
        config(['pdf.ai.key' => 'test-key']);

        foreach (array_keys(PdfTools::all()) as $tool) {
            $this->get(route('tools.show', $tool))->assertOk()->assertSee('Drop');
        }

        $this->get('/tools/no-such-tool')->assertNotFound();
    }

    public function test_wrong_file_type_is_rejected(): void
    {
        Livewire::test(PdfTool::class, ['tool' => 'word-to-pdf'])
            ->set('uploads', [$this->pdf()])
            ->assertHasErrors('uploads.0')
            ->assertSet('files', []);
    }

    public function test_single_file_tools_replace_the_previous_upload(): void
    {
        Livewire::test(PdfTool::class, ['tool' => 'compress-pdf'])
            ->set('uploads', [$this->pdf('first.pdf')])
            ->set('uploads', [$this->pdf('second.pdf')])
            ->assertCount('files', 1)
            ->assertSet('files.0.name', 'second.pdf')
            ->set('uploads', [$this->pdf('a.pdf'), $this->pdf('b.pdf')])
            ->assertHasErrors('uploads');
    }

    public function test_invalid_option_is_rejected(): void
    {
        Livewire::test(PdfTool::class, ['tool' => 'compress-pdf'])
            ->set('uploads', [$this->pdf()])
            ->set('options.level', 'maximum')
            ->call('process')
            ->assertHasErrors('options.level')
            ->assertSet('result', null);
    }

    public function test_images_can_be_reordered(): void
    {
        $component = Livewire::test(PdfTool::class, ['tool' => 'jpg-to-pdf'])
            ->set('uploads', [$this->jpeg('a.jpg'), $this->jpeg('b.jpg')]);

        $component->call('nudge', $component->get('files.1.id'), -1)
            ->assertSet('files.0.name', 'b.jpg');
    }
}
