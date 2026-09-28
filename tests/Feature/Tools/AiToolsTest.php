<?php

namespace Tests\Feature\Tools;

use App\Livewire\PdfTool;
use App\Services\Ai\TextModel;
use App\Services\Pdf\PdfToolException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\PdfFactory;
use Tests\TestCase;

class AiToolsTest extends TestCase
{
    /** @var array<int, array{system: string, content: array}> */
    private array $calls = [];

    private ?\Closure $reply = null;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config(['pdf.ai.key' => 'test-key']);

        $test = $this;
        $this->app->instance(TextModel::class, new class($test) implements TextModel
        {
            public function __construct(private AiToolsTest $test) {}

            public function complete(string $system, array $content, int $maxTokens = 16000): string
            {
                return $this->test->answer($system, $content);
            }
        });
    }

    public function answer(string $system, array $content): string
    {
        $this->calls[] = compact('system', 'content');

        return ($this->reply)($system, $content);
    }

    private function pdf(array $pages, string $name = 'report.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, PdfFactory::withText($pages));
    }

    public function test_tools_are_unavailable_without_an_api_key(): void
    {
        config(['pdf.ai.key' => null]);

        $this->get(route('tools.show', 'ai-summarizer'))->assertOk()->assertSee('isn’t set up yet')->assertSee('ANTHROPIC_API_KEY');

        Livewire::test(PdfTool::class, ['tool' => 'translate-pdf'])
            ->call('process')
            ->assertHasErrors('process');

        $this->assertSame([], $this->calls);
    }

    public function test_summarizer_sends_the_pdf_and_returns_markdown(): void
    {
        $this->reply = fn () => "Quarterly revenue grew.\n\n- Costs fell\n- Hiring paused";

        $component = Livewire::test(PdfTool::class, ['tool' => 'ai-summarizer'])
            ->set('uploads', [$this->pdf(["# Q3 report\nRevenue grew 12%."])])
            ->set('options.length', 'brief')
            ->set('options.focus', 'costs')
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.name', 'report_summary.md')
            ->assertSet('result.ext', 'md')
            ->assertSee('Quarterly revenue grew.')
            ->assertSee('<li>Costs fell</li>', false);

        [$document, $prompt] = $this->calls[0]['content'];
        $this->assertSame('document', $document['type']);
        $this->assertSame('application/pdf', $document['source']['mediaType']);
        $this->assertStringContainsString('one short paragraph', $prompt['text']);
        $this->assertStringContainsString('Pay particular attention to: costs', $prompt['text']);

        $markdown = Storage::disk('local')->get($component->get('result.path'));
        $this->assertStringStartsWith("# Summary of report\n\nQuarterly revenue grew.", $markdown);
    }

    public function test_summary_markdown_is_escaped_on_the_page(): void
    {
        $this->reply = fn () => '<script>alert(1)</script> Summary';

        Livewire::test(PdfTool::class, ['tool' => 'ai-summarizer'])
            ->set('uploads', [$this->pdf(['Hello'])])
            ->call('process')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_large_pdfs_are_sent_as_text(): void
    {
        config(['pdf.ai.max_document_mb' => 0]);
        $this->reply = fn () => 'Short summary.';

        Livewire::test(PdfTool::class, ['tool' => 'ai-summarizer'])
            ->set('uploads', [$this->pdf(['First page text', 'Second page text'])])
            ->call('process')
            ->assertHasNoErrors();

        $document = $this->calls[0]['content'][0];
        $this->assertSame('text', $document['type']);
        $this->assertStringContainsString("<page number=\"2\">\nSecond page text\n</page>", $document['text']);
    }

    public function test_ai_errors_are_shown_to_the_user(): void
    {
        $this->reply = fn () => throw PdfToolException::forUser('The AI declined to process this document.');

        Livewire::test(PdfTool::class, ['tool' => 'ai-summarizer'])
            ->set('uploads', [$this->pdf(['Hello'])])
            ->call('process')
            ->assertHasErrors('process')
            ->assertSee('The AI declined to process this document.');
    }

    public function test_translate_replaces_text_in_place(): void
    {
        $this->reply = function ($system, $content) {
            $texts = json_decode(substr($content[0]['text'], strpos($content[0]['text'], '[')), true);

            return "```json\n".json_encode(array_map(fn ($text) => 'ES '.$text, $texts))."\n```";
        };

        $component = Livewire::test(PdfTool::class, ['tool' => 'translate-pdf'])
            ->set('uploads', [$this->pdf(["# Annual report\nHello world", 'Second page'])])
            ->set('options.language', 'es')
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.name', 'report_translated.pdf')
            ->assertSet('result.pages', 2);

        $this->assertStringContainsString('Spanish', $this->calls[0]['content'][0]['text']);

        $output = Storage::disk('local')->path($component->get('result.path'));
        $text = Process::run(['python3', '-c', 'import fitz,sys; print("|".join(p.get_text() for p in fitz.open(sys.argv[1])))', $output])->output();
        // Longer translations wrap inside their box.
        $text = preg_replace('/[ \n]+/', ' ', $text);

        $this->assertStringContainsString('ES Annual report', $text);
        $this->assertStringContainsString('ES Hello world', $text);
        $this->assertStringContainsString('ES Second page', $text);
        $this->assertStringNotContainsString('Hello world', str_replace('ES Hello world', '', $text));
    }

    public function test_translate_rejects_a_mismatched_reply(): void
    {
        $this->reply = fn () => '["only one"]';

        Livewire::test(PdfTool::class, ['tool' => 'translate-pdf'])
            ->set('uploads', [$this->pdf(["Line one\n\n\n\n\n\nLine two far below"])])
            ->call('process')
            ->assertHasErrors('process')
            ->assertSee('incomplete');
    }

    public function test_translate_needs_selectable_text(): void
    {
        $this->reply = fn () => '[]';

        Livewire::test(PdfTool::class, ['tool' => 'translate-pdf'])
            ->set('uploads', [UploadedFile::fake()->createWithContent('scan.pdf', PdfFactory::make(1))])
            ->call('process')
            ->assertHasErrors('process')
            ->assertSee('OCR PDF');

        $this->assertSame([], $this->calls);
    }

    public function test_translate_into_japanese_uses_a_cjk_font(): void
    {
        $this->reply = fn ($system, $content) => json_encode(array_fill(0, count(json_decode(substr($content[0]['text'], strpos($content[0]['text'], '[')))), 'こんにちは'), JSON_UNESCAPED_UNICODE);

        $component = Livewire::test(PdfTool::class, ['tool' => 'translate-pdf'])
            ->set('uploads', [$this->pdf(['Hello'])])
            ->set('options.language', 'ja')
            ->call('process')
            ->assertHasNoErrors();

        $output = Storage::disk('local')->path($component->get('result.path'));
        $text = Process::run(['python3', '-c', 'import fitz,sys; doc = fitz.open(sys.argv[1]); print(doc[0].get_text())', $output])->output();
        $this->assertStringContainsString('こんにちは', preg_replace('/\s+/u', '', $text));
    }
}
