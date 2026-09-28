<?php

namespace Tests\Feature\Tools;

use App\Livewire\PdfTool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use RuntimeException;
use Tests\Support\PdfFactory;
use Tests\TestCase;
use ZipArchive;

class ConvertFromToolsTest extends TestCase
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
     * A page with a bold heading, a paragraph with bold and italic words,
     * a bullet, a numbered item, a ruled table and (optionally) images;
     * then any extra plain-text pages.
     */
    private function richPdf(string $name = 'report.pdf', int $images = 1, array $morePages = []): UploadedFile
    {
        $script = <<<'PY'
import fitz, json, sys
images, more = int(sys.argv[1]), json.loads(sys.argv[2])
doc = fitz.open()
p = doc.new_page(width=612, height=792)
p.insert_text((72, 72), "Quarterly Report", fontsize=24, fontname="hebo")
x = 72
for text, font in (("Sales were ", "helv"), ("strong", "hebo"), (" this ", "helv"), ("quarter", "heit")):
    p.insert_text((x, 110), text, fontsize=12, fontname=font)
    x += fitz.get_text_length(text, fontname=font, fontsize=12)
p.insert_text((72, 135), "• First point", fontsize=12)
p.insert_text((72, 155), "1. Numbered one", fontsize=12)
rows = [["Item", "Qty", "Price"], ["Paper", "4", "1,250.50"], ["Ink", "12", "$3.00"]]
for r, row in enumerate(rows):
    for c, value in enumerate(row):
        rect = fitz.Rect(72 + c * 130, 200 + r * 20, 202 + c * 130, 220 + r * 20)
        p.draw_rect(rect, color=(0, 0, 0), width=0.8)
        p.insert_text((rect.x0 + 4, rect.y1 - 6), value, fontsize=11)
for i in range(images):
    pix = fitz.Pixmap(fitz.csRGB, fitz.IRect(0, 0, 80 + i, 60), False)
    pix.clear_with(60 + 60 * i)
    p.insert_image(fitz.Rect(72 + i * 150, 300, 192 + i * 150, 390), pixmap=pix)
for text in more:
    doc.new_page(width=612, height=792).insert_text((72, 72), text, fontsize=12)
sys.stdout.buffer.write(doc.tobytes())
PY;

        $result = Process::run(['python3', '-c', $script, (string) $images, json_encode($morePages)]);

        if ($result->failed()) {
            throw new RuntimeException($result->errorOutput());
        }

        return UploadedFile::fake()->createWithContent($name, $result->output());
    }

    private function textPdf(string $name, array $pages): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, PdfFactory::withText($pages));
    }

    private function blankPdf(string $name = 'scan.pdf', int $pages = 1): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, PdfFactory::make($pages));
    }

    private function convert(string $tool, UploadedFile $file, array $options = []): Testable
    {
        $component = Livewire::test(PdfTool::class, ['tool' => $tool])->set('uploads', [$file])->assertHasNoErrors();

        foreach ($options as $name => $value) {
            $component->set("options.{$name}", $value);
        }

        return $component->call('process');
    }

    private function outputPath(Testable $component): string
    {
        return Storage::disk('local')->path($component->get('result.path'));
    }

    /**
     * @return array<string, string> entry name => contents
     */
    private function zipEntries(string $path): array
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true, 'Output is not a ZIP file.');
        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entries[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }

        $zip->close();

        return $entries;
    }

    /**
     * @return array<string, array<int, array<int, array{0: mixed, 1: bool, 2: string}>>> sheet => rows of [value, bold, format]
     */
    private function sheets(string $path): array
    {
        $script = <<<'PY'
import json, sys, openpyxl
book = openpyxl.load_workbook(sys.argv[1])
print(json.dumps({s.title: [[[c.value, bool(c.font.b), c.number_format] for c in row] for row in s.iter_rows()] for s in book}))
PY;

        return json_decode(Process::run(['python3', '-c', $script, $path])->throw()->output(), true);
    }

    // ------------------------------------------------------------ PDF to JPG

    public function test_pdf_to_jpg_zips_one_jpg_per_page(): void
    {
        $this->requireBinary('pdftoppm');

        $component = $this->convert('pdf-to-jpg', $this->textPdf('report.pdf', ['One', 'Two', 'Three']))
            ->assertHasNoErrors()
            ->assertSet('result.ext', 'zip')
            ->assertSet('result.name', 'report.zip')
            ->assertSet('result.meta.summary', 'Converted 3 pages to JPG.');

        $entries = $this->zipEntries($this->outputPath($component));
        $this->assertSame(['report-01.jpg', 'report-02.jpg', 'report-03.jpg'], array_keys($entries));
        $size = getimagesizefromstring($entries['report-01.jpg']);
        $this->assertSame(IMAGETYPE_JPEG, $size[2]);
        $this->assertSame(1275, $size[0]); // 8.5 in at the default 150 DPI
    }

    public function test_pdf_to_jpg_returns_a_single_page_as_a_jpg_at_the_chosen_quality(): void
    {
        $this->requireBinary('pdftoppm');

        $component = $this->convert('pdf-to-jpg', $this->textPdf('memo.pdf', ['Only page']), ['quality' => '72'])
            ->assertHasNoErrors()
            ->assertSet('result.ext', 'jpg')
            ->assertSet('result.name', 'memo.jpg');

        $size = getimagesize($this->outputPath($component));
        $this->assertSame([612, 792, IMAGETYPE_JPEG], [$size[0], $size[1], $size[2]]);
    }

    public function test_pdf_to_jpg_extracts_embedded_images(): void
    {
        $this->requireBinary('pdfimages');

        $component = $this->convert('pdf-to-jpg', $this->richPdf('photos.pdf', images: 2), ['mode' => 'images'])
            ->assertHasNoErrors()
            ->assertSet('result.ext', 'zip')
            ->assertSet('result.meta.summary', 'Extracted 2 images.');

        $entries = $this->zipEntries($this->outputPath($component));
        $this->assertSame(['photos-01.jpg', 'photos-02.jpg'], array_keys($entries));
        $this->assertSame([80, 60], array_slice(getimagesizefromstring($entries['photos-01.jpg']), 0, 2));
        $this->assertSame(IMAGETYPE_JPEG, getimagesizefromstring($entries['photos-02.jpg'])[2]);
    }

    public function test_pdf_to_jpg_reports_a_pdf_without_images(): void
    {
        $this->requireBinary('pdfimages');

        $this->convert('pdf-to-jpg', $this->textPdf('plain.pdf', ['No pictures here']), ['mode' => 'images'])
            ->assertHasErrors('process')
            ->assertSee('no embedded images')
            ->assertSet('result', null);
    }

    public function test_pdf_to_jpg_rejects_an_unknown_quality(): void
    {
        $this->convert('pdf-to-jpg', $this->blankPdf(), ['quality' => '600'])
            ->assertHasErrors('options.quality');
    }

    // ----------------------------------------------------------- PDF to Word

    public function test_pdf_to_word_keeps_headings_styles_lists_tables_and_images(): void
    {
        $component = $this->convert('pdf-to-word', $this->richPdf('report.pdf', morePages: ['Second page text']))
            ->assertHasNoErrors()
            ->assertSet('result.ext', 'docx')
            ->assertSet('result.name', 'report.docx')
            ->assertSet('result.meta.summary', 'Converted 2 pages with 1 image and 1 table.');

        $entries = $this->zipEntries($this->outputPath($component));
        $xml = $entries['word/document.xml'];

        $this->assertMatchesRegularExpression('#<w:pStyle w:val="Heading1"/>.*?Quarterly Report#s', $xml);
        $this->assertMatchesRegularExpression('#<w:b/>.*?<w:t>strong</w:t>#s', $xml);
        $this->assertMatchesRegularExpression('#<w:i/>.*?<w:t>quarter</w:t>#s', $xml);
        $this->assertMatchesRegularExpression('#<w:pStyle w:val="ListBullet"/>.*?First point#s', $xml);
        $this->assertStringContainsString('<w:tbl>', $xml);
        $this->assertStringContainsString('1,250.50', $xml);
        $this->assertSame(1, substr_count($xml, '1,250.50'), 'Table text should not repeat as paragraphs.');
        $this->assertSame(1, substr_count($xml, '<w:br w:type="page"/>'));
        $this->assertStringContainsString('Second page text', $xml);
        $this->assertNotEmpty(preg_grep('#^word/media/#', array_keys($entries)));
    }

    public function test_pdf_to_word_asks_for_ocr_on_scanned_pdfs(): void
    {
        $this->convert('pdf-to-word', $this->blankPdf('scan.pdf', 2))
            ->assertHasErrors('process')
            ->assertSee('Run OCR PDF on it first')
            ->assertSet('result', null);
    }

    // ----------------------------------------------------- PDF to PowerPoint

    public function test_pdf_to_powerpoint_makes_one_slide_per_page(): void
    {
        $this->requireBinary('soffice');

        $component = $this->convert('pdf-to-powerpoint', $this->textPdf('deck.pdf', ["# Welcome\nIntro slide", 'Closing slide']))
            ->assertHasNoErrors()
            ->assertSet('result.ext', 'pptx')
            ->assertSet('result.name', 'deck.pptx')
            ->assertSet('result.meta.summary', 'Created 2 slides, one per page.');

        $entries = $this->zipEntries($this->outputPath($component));
        $this->assertArrayHasKey('ppt/slides/slide1.xml', $entries);
        $this->assertArrayHasKey('ppt/slides/slide2.xml', $entries);
        $this->assertStringContainsString('Welcome', $entries['ppt/slides/slide1.xml']);
        $this->assertStringContainsString('Closing slide', $entries['ppt/slides/slide2.xml']);
    }

    // ---------------------------------------------------------- PDF to Excel

    public function test_pdf_to_excel_puts_each_table_on_a_sheet_with_numbers(): void
    {
        $component = $this->convert('pdf-to-excel', $this->richPdf('prices.pdf'))
            ->assertHasNoErrors()
            ->assertSet('result.ext', 'xlsx')
            ->assertSet('result.name', 'prices.xlsx')
            ->assertSet('result.meta.summary', 'Found 1 table, one per sheet.');

        $sheets = $this->sheets($this->outputPath($component));
        $this->assertSame(['Page 1'], array_keys($sheets));
        [$header, $paper, $ink] = $sheets['Page 1'];

        $this->assertSame([['Item', true], ['Qty', true], ['Price', true]], array_map(fn ($c) => array_slice($c, 0, 2), $header));
        $this->assertSame(['Paper', false, 'General'], $paper[0]);
        $this->assertSame(4, $paper[1][0]);
        $this->assertSame([1250.5, false, '#,##0.00'], $paper[2]);
        $this->assertSame([3, false, '"$"#,##0.00'], $ink[2]);
    }

    public function test_pdf_to_excel_text_mode_exports_every_line(): void
    {
        $component = $this->convert('pdf-to-excel', $this->textPdf('notes.pdf', ["Alpha\nBeta", 'Gamma']), ['mode' => 'text'])
            ->assertHasNoErrors()
            ->assertSet('result.meta.summary', 'Exported 3 lines of text.');

        $sheets = $this->sheets($this->outputPath($component));
        $this->assertSame(['Page 1', 'Page 2'], array_keys($sheets));
        $this->assertSame(['Alpha', 'Beta'], array_map(fn ($row) => $row[0][0], $sheets['Page 1']));
        $this->assertSame('Gamma', $sheets['Page 2'][0][0][0]);
    }

    public function test_pdf_to_excel_suggests_text_mode_when_no_tables_are_found(): void
    {
        $this->convert('pdf-to-excel', $this->textPdf('letter.pdf', ['Just a letter']))
            ->assertHasErrors('process')
            ->assertSee('No tables were found')
            ->assertSet('result', null);
    }

    public function test_pdf_to_excel_asks_for_ocr_on_scanned_pdfs(): void
    {
        $this->convert('pdf-to-excel', $this->blankPdf(), ['mode' => 'text'])
            ->assertHasErrors('process')
            ->assertSee('Run OCR PDF on it first');
    }

    // ---------------------------------------------------------- PDF to PDF/A

    public function test_pdf_to_pdfa_writes_the_chosen_conformance_level(): void
    {
        $this->requireBinary('ocrmypdf');

        $component = $this->convert('pdf-to-pdfa', $this->textPdf('contract.pdf', ['Signed contract text']), ['level' => 'pdfa-3'])
            ->assertHasNoErrors()
            ->assertSet('result.ext', 'pdf')
            ->assertSet('result.name', 'contract_pdfa.pdf')
            ->assertSet('result.pages', 1)
            ->assertSet('result.meta.summary', 'Saved as PDF/A-3b, ready for long-term archiving.');

        $pdf = file_get_contents($this->outputPath($component));
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertMatchesRegularExpression('#pdfaid:part(>|=")3#', $pdf);
    }

    // ------------------------------------------------------- PDF to Markdown

    public function test_pdf_to_markdown_renders_structure_and_a_preview(): void
    {
        $component = $this->convert('pdf-to-markdown', $this->richPdf('report.pdf', images: 0, morePages: ['Page two text']), ['pageBreaks' => true])
            ->assertHasNoErrors()
            ->assertSet('result.ext', 'md')
            ->assertSet('result.name', 'report.md')
            ->assertSee('Quarterly Report');

        $markdown = file_get_contents($this->outputPath($component));

        $this->assertStringStartsWith("# Quarterly Report\n\n", $markdown);
        $this->assertStringContainsString('Sales were **strong** this *quarter*', $markdown);
        $this->assertStringContainsString("- First point\n1. Numbered one", $markdown);
        $this->assertStringContainsString("| Item | Qty | Price |\n| --- | --- | --- |\n| Paper | 4 | 1,250.50 |", $markdown);
        $this->assertSame(1, substr_count($markdown, 'Paper'), 'Table text should not repeat as paragraphs.');
        $this->assertStringContainsString("\n\n---\n\nPage two text", $markdown);
        $this->assertSame($markdown, $component->get('result.preview'));
    }

    public function test_pdf_to_markdown_preview_is_trimmed_for_long_documents(): void
    {
        $paragraph = str_repeat('Lorem ipsum dolor sit amet. ', 3);
        $pages = array_fill(0, 8, implode("\n\n", array_fill(0, 30, $paragraph)));

        $component = $this->convert('pdf-to-markdown', $this->textPdf('long.pdf', $pages))
            ->assertHasNoErrors()
            ->assertSet('result.meta.summary', 'Here’s the start of it. Download the file for the rest.');

        $this->assertLessThanOrEqual(4010, mb_strlen($component->get('result.preview')));
        $this->assertGreaterThan(8000, filesize($this->outputPath($component)));
        $this->assertStringNotContainsString('---', file_get_contents($this->outputPath($component)));
    }

    public function test_pdf_to_markdown_asks_for_ocr_on_scanned_pdfs(): void
    {
        $this->convert('pdf-to-markdown', $this->blankPdf())
            ->assertHasErrors('process')
            ->assertSee('Run OCR PDF on it first');
    }

    public function test_convert_from_pages_render(): void
    {
        foreach (['pdf-to-jpg', 'pdf-to-word', 'pdf-to-powerpoint', 'pdf-to-excel', 'pdf-to-pdfa', 'pdf-to-markdown'] as $tool) {
            $this->get(route('tools.show', $tool))->assertOk()->assertSee('Drop');
        }
    }
}
