<?php

namespace Tests\Feature\Tools;

use App\Livewire\PdfTool;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use RuntimeException;
use Tests\Support\PdfFactory;
use Tests\TestCase;

class EditToolsTest extends TestCase
{
    private string $disk;

    protected function setUp(): void
    {
        parent::setUp();

        // Like Storage::fake('local'), but in a folder of its own so parallel
        // test runs can't wipe this test's files.
        $this->disk = storage_path('framework/testing/disks/edit-tools-'.Str::random(10));
        Storage::set('local', Storage::createLocalDriver(['root' => $this->disk]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->disk);

        parent::tearDown();
    }

    // --- rotate-pdf ------------------------------------------------------

    public function test_rotate_turns_chosen_pages_relative_to_their_rotation(): void
    {
        $pdf = $this->fitzPdf('for r in (90, 0, 0): doc.new_page(width=612, height=792).set_rotation(r)');

        $component = $this->tool('rotate-pdf', $pdf)
            ->set('options.rotation', [1 => 90, 3 => 180, 2 => 0])
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.name', 'document_rotated.pdf');

        $this->assertSame([180, 0, 180], $this->inspect($component, 'out = [p.rotation for p in doc]'));
    }

    public function test_rotate_needs_at_least_one_turned_page(): void
    {
        $this->tool('rotate-pdf', PdfFactory::make(2))
            ->set('options.rotation', [1 => 0])
            ->call('process')
            ->assertHasErrors(['process' => 'Rotate at least one page.']);
    }

    // --- add-page-numbers ------------------------------------------------

    public function test_page_numbers_skip_the_cover_and_sit_bottom_center(): void
    {
        $component = $this->tool('add-page-numbers', PdfFactory::withText(['Cover', 'Intro', 'Body']))
            ->set('options.format', 'Page {n} of {total}')
            ->set('options.skipFirst', true)
            ->set('options.margin', 10)
            ->call('process')
            ->assertHasNoErrors();

        $lines = $this->inspect($component, self::LINES);

        $this->assertSame([], $this->find($lines[0], 'Page'));
        $this->assertSame('Page 1 of 2', $this->find($lines[1], 'Page')[0]['text']);
        $number = $this->find($lines[2], 'Page')[0];
        $this->assertSame('Page 2 of 2', $number['text']);
        $this->assertEqualsWithDelta(306, ($number['bbox'][0] + $number['bbox'][2]) / 2, 2);
        $this->assertEqualsWithDelta(792 - 10 * 72 / 25.4, $number['bbox'][3], 6);
    }

    public function test_page_numbers_stay_upright_on_rotated_pages(): void
    {
        $pdf = $this->fitzPdf('doc.new_page(width=612, height=792).set_rotation(90)');

        $component = $this->tool('add-page-numbers', $pdf)
            ->set('options.position', 'top-right')
            ->set('options.start', 7)
            ->set('options.size', 20)
            ->call('process')
            ->assertHasNoErrors();

        $number = $this->find($this->inspect($component, self::LINES)[0], '7')[0];

        // Displayed page is 792 × 612 (landscape); the number is top right and reads left to right.
        $this->assertSame([1.0, 0.0], $number['dir']);
        $this->assertGreaterThan(700, $number['bbox'][0]);
        $this->assertLessThan(792, $number['bbox'][2]);
        $this->assertLessThan(60, $number['bbox'][3]);
    }

    // --- add-watermark ---------------------------------------------------

    public function test_text_watermark_is_diagonal_and_translucent(): void
    {
        $component = $this->tool('add-watermark', PdfFactory::withText(['Hello', 'World']))
            ->set('options.text', 'DRAFT')
            ->set('options.opacity', 30)
            ->call('process')
            ->assertHasNoErrors();

        $result = $this->inspect($component, <<<'PY'
out = []
for page in doc:
    spans = [s for s in page.get_texttrace() if "".join(chr(c[0]) for c in s["chars"]) == "DRAFT"]
    line = [l for b in page.get_text("dict")["blocks"] for l in b.get("lines", []) if "DRAFT" in l["spans"][0]["text"]][0]
    out.append({"opacity": round(spans[0]["opacity"], 2), "dir": [round(v, 2) for v in line["dir"]], "center": [round((line["bbox"][0] + line["bbox"][2]) / 2), round((line["bbox"][1] + line["bbox"][3]) / 2)]})
PY);

        $this->assertCount(2, $result);
        $this->assertSame(0.3, $result[0]['opacity']);
        $this->assertSame([0.71, -0.71], $result[0]['dir']);
        $this->assertEqualsWithDelta(306, $result[0]['center'][0], 10);
        $this->assertEqualsWithDelta(396, $result[0]['center'][1], 10);
    }

    public function test_tiled_text_watermark_repeats_behind_content(): void
    {
        $component = $this->tool('add-watermark', PdfFactory::make(1))
            ->set('options.text', 'COPY')
            ->set('options.rotation', 'horizontal')
            ->set('options.position', 'tiled')
            ->set('options.behind', true)
            ->call('process')
            ->assertHasNoErrors();

        $count = $this->inspect($component, 'out = doc[0].get_text().count("COPY")');
        $this->assertGreaterThanOrEqual(8, $count);
    }

    public function test_image_watermark_is_inserted_with_baked_in_transparency(): void
    {
        $component = $this->tool('add-watermark', PdfFactory::make(2));
        $id = $component->call('addAsset', $this->pngDataUrl())->get('assets');
        $id = array_key_first($id);

        $component->set('options.type', 'image')
            ->set('options.image', $id)
            ->set('options.opacity', 50)
            ->set('options.scale', 50)
            ->call('process')
            ->assertHasNoErrors();

        $result = $this->inspect($component, <<<'PY'
out = []
for page in doc:
    info = page.get_image_info(xrefs=True)[0]
    img = page.get_images()[0]
    out.append({"bbox": [round(v) for v in info["bbox"]], "smask": img[1] > 0})
PY);

        $this->assertCount(2, $result);
        $this->assertTrue($result[0]['smask']);
        // Diagonal: the 200×100 image is turned 45°, so its box is square-ish and centered.
        [$x0, $y0, $x1, $y1] = $result[0]['bbox'];
        $this->assertEqualsWithDelta(306, ($x0 + $x1) / 2, 3);
        $this->assertEqualsWithDelta(396, ($y0 + $y1) / 2, 3);
        $this->assertEqualsWithDelta(306, $x1 - $x0, 3);
    }

    public function test_image_watermark_needs_an_image(): void
    {
        $this->tool('add-watermark', PdfFactory::make(1))
            ->set('options.type', 'image')
            ->call('process')
            ->assertHasErrors('options.image');
    }

    // --- crop-pdf --------------------------------------------------------

    public function test_crop_by_margins_trims_every_page(): void
    {
        $component = $this->tool('crop-pdf', PdfFactory::make(2))
            ->set('options.top', 20)
            ->set('options.left', 0)
            ->call('process')
            ->assertHasNoErrors();

        $mm = 72 / 25.4;
        foreach ($this->inspect($component, 'out = [list(p.cropbox) for p in doc]') as $box) {
            $this->assertEqualsWithDelta([0, 20 * $mm, 612 - 10 * $mm, 792 - 10 * $mm], $box, 0.1);
        }
    }

    public function test_crop_by_area_on_one_page(): void
    {
        $component = $this->tool('crop-pdf', PdfFactory::make(2))
            ->set('options.mode', 'area')
            ->set('options.allPages', false)
            ->set('options.area', [['kind' => 'rect', 'page' => 2, 'x' => 0.1, 'y' => 0.25, 'w' => 0.5, 'h' => 0.5]])
            ->call('process')
            ->assertHasNoErrors();

        [$first, $second] = $this->inspect($component, 'out = [list(p.cropbox) for p in doc]');
        $this->assertEqualsWithDelta([0, 0, 612, 792], $first, 0.1);
        $this->assertEqualsWithDelta([61.2, 198, 367.2, 594], $second, 0.1);
    }

    public function test_crop_area_uses_the_displayed_orientation(): void
    {
        $pdf = $this->fitzPdf('doc.new_page(width=612, height=792).set_rotation(90)');

        // Top-left quarter of the landscape page as shown.
        $component = $this->tool('crop-pdf', $pdf)
            ->set('options.mode', 'area')
            ->set('options.area', [['kind' => 'rect', 'page' => 1, 'x' => 0, 'y' => 0, 'w' => 0.5, 'h' => 0.5]])
            ->call('process')
            ->assertHasNoErrors();

        $result = $this->inspect($component, 'out = {"rect": list(doc[0].rect), "cropbox": list(doc[0].cropbox)}');
        $this->assertEqualsWithDelta([0, 0, 396, 306], $result['rect'], 0.1);
        // Turned 90° clockwise, the displayed top left is the unrotated bottom left.
        $this->assertEqualsWithDelta([0, 396, 306, 792], $result['cropbox'], 0.1);
    }

    public function test_crop_rejects_a_missing_or_tiny_area(): void
    {
        $this->tool('crop-pdf', PdfFactory::make(1))
            ->set('options.mode', 'area')
            ->call('process')
            ->assertHasErrors(['process' => 'Draw the area to keep on a page first.']);

        $this->tool('crop-pdf', PdfFactory::make(1))
            ->set('options.mode', 'area')
            ->set('options.area', [['kind' => 'rect', 'page' => 1, 'x' => 0.1, 'y' => 0.1, 'w' => 0.02, 'h' => 0.02]])
            ->call('process')
            ->assertHasErrors(['process' => 'The area to keep is too small.']);
    }

    // --- edit-pdf --------------------------------------------------------

    public function test_edit_applies_every_kind_of_placement(): void
    {
        $component = $this->tool('edit-pdf', PdfFactory::withText(["Some words to mark\nsecond line"]));
        $asset = array_key_first($component->call('addAsset', $this->pngDataUrl())->get('assets'));

        $component->set('options.items', [
            ['kind' => 'text', 'page' => 1, 'x' => 0.5, 'y' => 0.5, 'w' => 0.4, 'h' => 0.05, 'text' => 'Added text', 'size' => 14, 'color' => '#FF0000'],
            ['kind' => 'rect', 'page' => 1, 'x' => 0.1, 'y' => 0.7, 'w' => 0.2, 'h' => 0.1, 'color' => '#2338A8'],
            // Over "Some words" (drawn at y=72 on a 792pt page).
            ['kind' => 'highlight', 'page' => 1, 'x' => 0.1, 'y' => 72 / 792 - 0.02, 'w' => 0.3, 'h' => 0.03, 'color' => '#FACC15'],
            ['kind' => 'highlight', 'page' => 1, 'x' => 0.5, 'y' => 0.9, 'w' => 0.3, 'h' => 0.03],
            ['kind' => 'image', 'page' => 1, 'x' => 0.6, 'y' => 0.1, 'w' => 0.2, 'h' => 0.1, 'asset' => $asset],
            ['kind' => 'note', 'page' => 1, 'x' => 0.8, 'y' => 0.8, 'w' => 0.035, 'h' => 0.035, 'text' => 'Check this'],
        ])->call('process')->assertHasNoErrors();

        $result = $this->inspect($component, <<<'PY'
page = doc[0]
added = [w for w in page.get_text("words") if w[4] in ("Added", "text")]
out = {
    "added": [round(v) for v in added[0][:4]] if added else None,
    "annots": sorted(a.type[1] for a in page.annots()),
    "note": [a.info["content"] for a in page.annots() if a.type[1] == "Text"],
    "images": len(page.get_images()),
    "rects": len([d for d in page.get_drawings() if d["color"] and abs(d["rect"].x0 - 61.2) < 2]),
}
PY);

        $this->assertNotNull($result['added']);
        $this->assertEqualsWithDelta(306, $result['added'][0], 4);
        $this->assertEqualsWithDelta(396, $result['added'][1], 6);
        $this->assertSame(['Highlight', 'Square', 'Text'], $result['annots']);
        $this->assertSame(['Check this'], $result['note']);
        $this->assertSame(1, $result['images']);
        $this->assertSame(1, $result['rects']);
    }

    public function test_edit_text_is_upright_on_a_rotated_page_and_shrinks_to_fit(): void
    {
        $pdf = $this->fitzPdf('doc.new_page(width=612, height=792).set_rotation(270)');

        $component = $this->tool('edit-pdf', $pdf)
            ->set('options.items', [
                ['kind' => 'text', 'page' => 1, 'x' => 0.1, 'y' => 0.2, 'w' => 0.3, 'h' => 0.1, 'text' => 'Rotated page note', 'size' => 16],
                ['kind' => 'text', 'page' => 1, 'x' => 0.5, 'y' => 0.8, 'w' => 0.1, 'h' => 0.05, 'text' => 'Far too much text for this tiny box', 'size' => 40],
            ])
            ->call('process')
            ->assertHasNoErrors();

        $lines = $this->inspect($component, self::LINES)[0];
        $note = $this->find($lines, 'Rotated')[0];

        // Displayed page is 792 × 612; the box starts at (79.2, 122.4).
        $this->assertSame([1.0, 0.0], $note['dir']);
        $this->assertEqualsWithDelta(79.2, $note['bbox'][0], 4);
        $this->assertEqualsWithDelta(122.4, $note['bbox'][1], 6);
        $this->assertLessThan(40, $this->find($lines, 'Far')[0]['size']);
    }

    public function test_edit_needs_at_least_one_item(): void
    {
        $this->tool('edit-pdf', PdfFactory::make(1))
            ->call('process')
            ->assertHasErrors(['process' => 'Add something to a page first.']);
    }

    // --- pdf-forms -------------------------------------------------------

    public function test_forms_fill_existing_fields_and_add_new_ones(): void
    {
        $component = $this->tool('pdf-forms', $this->formPdf())
            ->assertCount('fields', 2)
            ->assertSet('fields.0.name', 'name')
            ->assertSet('options.values', ['', false])
            ->set('options.values.0', 'Ada Lovelace')
            ->set('options.values.1', true)
            ->set('options.add', [
                ['kind' => 'field-text', 'page' => 1, 'x' => 0.1, 'y' => 0.5, 'w' => 0.3, 'h' => 0.04, 'name' => 'name'],
                ['kind' => 'field-checkbox', 'page' => 1, 'x' => 0.1, 'y' => 0.6, 'w' => 0.03, 'h' => 0.03, 'name' => 'check_1'],
            ])
            ->call('process')
            ->assertHasNoErrors();

        $widgets = $this->inspect($component, 'out = [[w.field_name, w.field_type_string, w.field_value] for w in doc[0].widgets()]');

        $this->assertSame([
            ['name', 'Text', 'Ada Lovelace'],
            ['agree', 'CheckBox', 'Yes'],
            ['name_2', 'Text', ''],
            ['check_1', 'CheckBox', 'Off'],
        ], $widgets);
    }

    public function test_forms_can_be_flattened(): void
    {
        $component = $this->tool('pdf-forms', $this->formPdf())
            ->set('options.values.0', 'Grace Hopper')
            ->set('options.flatten', true)
            ->call('process')
            ->assertHasNoErrors();

        $result = $this->inspect($component, 'out = {"widgets": len(list(doc[0].widgets())), "text": doc[0].get_text()}');

        $this->assertSame(0, $result['widgets']);
        $this->assertStringContainsString('Grace Hopper', $result['text']);
    }

    public function test_forms_need_fields_to_work_with(): void
    {
        $this->tool('pdf-forms', PdfFactory::make(1))
            ->assertSet('fields', [])
            ->call('process')
            ->assertHasErrors(['process' => 'This PDF has no form fields. Add a field on the page first.']);
    }

    // --- helpers ---------------------------------------------------------

    /** Python that sets `out` to every text line per page, in displayed coordinates. */
    private const LINES = <<<'PY'
out = []
for page in doc:
    m = page.rotation_matrix
    lines = []
    for block in page.get_text("dict")["blocks"]:
        for line in block.get("lines", []):
            d = fitz.Point(line["dir"]) * m - fitz.Point(0, 0) * m
            lines.append({
                "text": "".join(s["text"] for s in line["spans"]),
                "bbox": list(fitz.Rect(line["bbox"]) * m),
                "dir": [round(d.x, 2) + 0.0, round(d.y, 2) + 0.0],
                "size": line["spans"][0]["size"],
            })
    out.append(lines)
PY;

    private function tool(string $slug, string $pdf): Testable
    {
        return Livewire::test(PdfTool::class, ['tool' => $slug])
            ->set('uploads', [UploadedFile::fake()->createWithContent('document.pdf', $pdf)])
            ->assertHasNoErrors();
    }

    private function find(array $lines, string $prefix): array
    {
        return array_values(array_filter($lines, fn ($line) => str_starts_with($line['text'], $prefix)));
    }

    /**
     * Run Python against the tool's output PDF (`doc`) and return `out`.
     */
    private function inspect(Testable $component, string $code): mixed
    {
        $path = Storage::disk('local')->path($component->get('result.path'));

        return json_decode($this->python("doc = fitz.open(sys.argv[1])\n{$code}\nprint(json.dumps(out))", [$path]), true);
    }

    /**
     * Build a PDF with PyMuPDF; the code adds pages to `doc`.
     */
    private function fitzPdf(string $code): string
    {
        return $this->python("doc = fitz.open()\n{$code}\nsys.stdout.buffer.write(doc.tobytes())");
    }

    private function formPdf(): string
    {
        return $this->fitzPdf(<<<'PY'
page = doc.new_page(width=612, height=792)
for kind, name, rect in ((fitz.PDF_WIDGET_TYPE_TEXT, "name", (72, 72, 300, 96)), (fitz.PDF_WIDGET_TYPE_CHECKBOX, "agree", (72, 120, 90, 138))):
    w = fitz.Widget()
    w.field_type, w.field_name, w.rect = kind, name, fitz.Rect(rect)
    w.field_value = False if kind == fitz.PDF_WIDGET_TYPE_CHECKBOX else ""
    page.add_widget(w)
PY);
    }

    private function python(string $code, array $args = []): string
    {
        $result = Process::run([config('pdf.binaries.python', 'python3'), '-c', "import fitz, json, sys\n{$code}", ...$args]);

        if ($result->failed()) {
            throw new RuntimeException($result->errorOutput());
        }

        return $result->output();
    }

    /** A 200×100 opaque PNG. */
    private function pngDataUrl(): string
    {
        $image = imagecreatetruecolor(200, 100);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 60, 200));
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode(ob_get_clean());
    }
}
