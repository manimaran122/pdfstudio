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

class SecurityToolsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        foreach (['qpdf', config('pdf.binaries.python')] as $binary) {
            if (Process::run(['which', $binary])->failed()) {
                $this->markTestSkipped("{$binary} is not installed.");
            }
        }
    }

    private function textPdf(array $pages, string $name = 'document.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, PdfFactory::withText($pages));
    }

    /**
     * An encrypted copy of a text PDF, made with qpdf.
     */
    private function encryptedPdf(string $user, string $owner = 'owner-secret', string $name = 'locked.pdf'): UploadedFile
    {
        $plain = tempnam(sys_get_temp_dir(), 'plain');
        $locked = tempnam(sys_get_temp_dir(), 'locked');
        file_put_contents($plain, PdfFactory::withText(["# Private\nTop secret numbers"]));

        Process::run(['qpdf', '--encrypt', $user, $owner, '256', '--print=none', '--', $plain, $locked])->throw();
        $content = file_get_contents($locked);
        @unlink($plain);
        @unlink($locked);

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function outputPath(Testable $component): string
    {
        return Storage::disk('local')->path($component->get('result.path'));
    }

    /**
     * Run a PyMuPDF snippet against a file; the path is sys.argv[1].
     */
    private function fitz(string $path, string $code): string
    {
        return trim(Process::run([config('pdf.binaries.python'), '-c', "import fitz, sys\ndoc = fitz.open(sys.argv[1])\n".$code, $path])->throw()->output());
    }

    private function signaturePng(): string
    {
        $image = imagecreatetruecolor(300, 100);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 10, 80, 290, 20, imagecolorallocate($image, 0, 0, 120));
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode(ob_get_clean());
    }

    // --- Unlock ------------------------------------------------------------

    public function test_unlock_removes_the_password(): void
    {
        $component = Livewire::test(PdfTool::class, ['tool' => 'unlock-pdf'])
            ->set('uploads', [$this->encryptedPdf('open-sesame')])
            ->assertHasNoErrors()
            ->assertCount('files', 1)
            ->set('options.password', 'open-sesame')
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.name', 'locked_unlocked.pdf')
            ->assertSet('result.pages', 1);

        $output = $this->outputPath($component);
        $this->assertSame(2, Process::run(['qpdf', '--is-encrypted', $output])->exitCode());
        $this->assertStringContainsString('Top secret numbers', $this->fitz($output, 'print(doc.needs_pass, doc[0].get_text())'));
        $this->assertStringStartsWith('False', $this->fitz($output, 'print(doc.needs_pass)'));
    }

    public function test_unlock_removes_owner_only_restrictions_without_a_password(): void
    {
        $component = Livewire::test(PdfTool::class, ['tool' => 'unlock-pdf'])
            ->set('uploads', [$this->encryptedPdf('')])
            ->call('process')
            ->assertHasNoErrors();

        $this->assertSame(2, Process::run(['qpdf', '--is-encrypted', $this->outputPath($component)])->exitCode());
    }

    public function test_unlock_rejects_a_wrong_password(): void
    {
        Livewire::test(PdfTool::class, ['tool' => 'unlock-pdf'])
            ->set('uploads', [$this->encryptedPdf('open-sesame')])
            ->set('options.password', 'guess')
            ->call('process')
            ->assertHasErrors(['process'])
            ->assertSee('That password isn’t correct.')
            ->assertDontSee('guess')
            ->assertSet('result', null);
    }

    public function test_unlock_asks_for_the_password(): void
    {
        Livewire::test(PdfTool::class, ['tool' => 'unlock-pdf'])
            ->set('uploads', [$this->encryptedPdf('open-sesame')])
            ->call('process')
            ->assertHasErrors(['process'])
            ->assertSee('Enter the password to unlock this PDF.');
    }

    public function test_unlock_rejects_an_unprotected_pdf(): void
    {
        Livewire::test(PdfTool::class, ['tool' => 'unlock-pdf'])
            ->set('uploads', [$this->textPdf(['Hello'])])
            ->set('options.password', 'anything')
            ->call('process')
            ->assertHasErrors(['process'])
            ->assertSee('This PDF isn’t password protected.');
    }

    // --- Protect -----------------------------------------------------------

    public function test_protect_encrypts_with_the_chosen_permissions(): void
    {
        $component = Livewire::test(PdfTool::class, ['tool' => 'protect-pdf'])
            ->set('uploads', [$this->textPdf(['Hello'], 'plan.pdf')])
            ->set('options.password', 'correct horse')
            ->set('options.confirm', 'correct horse')
            ->set('options.copy', true)
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.name', 'plan_protected.pdf');

        $output = $this->outputPath($component);
        $this->assertSame(0, Process::run(['qpdf', '--requires-password', $output])->exitCode());

        $encryption = Process::run(['qpdf', '--password=correct horse', '--show-encryption', $output])->output();
        $this->assertStringContainsString('Supplied password is user password', $encryption);
        $this->assertStringContainsString('print high resolution: allowed', $encryption);
        $this->assertStringContainsString('extract for any purpose: allowed', $encryption);
        $this->assertStringContainsString('modify other: not allowed', $encryption);
        $this->assertStringContainsString('file encryption method: AESv3', $encryption);
    }

    public function test_protect_can_block_printing(): void
    {
        $component = Livewire::test(PdfTool::class, ['tool' => 'protect-pdf'])
            ->set('uploads', [$this->textPdf(['Hello'])])
            ->set('options.password', 'secret123')
            ->set('options.confirm', 'secret123')
            ->set('options.print', false)
            ->call('process')
            ->assertHasNoErrors();

        $encryption = Process::run(['qpdf', '--password=secret123', '--show-encryption', $this->outputPath($component)])->output();
        $this->assertStringContainsString('print high resolution: not allowed', $encryption);
        $this->assertStringContainsString('extract for any purpose: not allowed', $encryption);
    }

    public function test_protect_rejects_mismatched_passwords(): void
    {
        Livewire::test(PdfTool::class, ['tool' => 'protect-pdf'])
            ->set('uploads', [$this->textPdf(['Hello'])])
            ->set('options.password', 'secret123')
            ->set('options.confirm', 'secret124')
            ->call('process')
            ->assertHasErrors(['process'])
            ->assertSee('The passwords don’t match.')
            ->assertSet('result', null);
    }

    public function test_protect_rejects_short_and_missing_passwords(): void
    {
        Livewire::test(PdfTool::class, ['tool' => 'protect-pdf'])
            ->set('uploads', [$this->textPdf(['Hello'])])
            ->set('options.password', 'abc')
            ->set('options.confirm', 'abc')
            ->call('process')
            ->assertHasErrors(['process'])
            ->assertSee('Use a password of at least 6 characters.')
            ->set('options.password', '')
            ->set('options.confirm', '')
            ->call('process')
            ->assertHasErrors(['options.password', 'options.confirm']);
    }

    // --- Sign --------------------------------------------------------------

    public function test_sign_places_the_signature_image_and_text(): void
    {
        $component = Livewire::test(PdfTool::class, ['tool' => 'sign-pdf'])
            ->set('uploads', [$this->textPdf(['Page one', 'Please sign here'], 'contract.pdf')])
            ->call('addAsset', $this->signaturePng())
            ->assertHasNoErrors();

        $asset = array_key_first($component->get('assets'));

        $component
            ->set('options.placements', [
                ['kind' => 'signature', 'page' => 2, 'x' => 0.1, 'y' => 0.7, 'w' => 0.3, 'h' => 0.1, 'asset' => $asset],
                ['kind' => 'text', 'page' => 2, 'x' => 0.1, 'y' => 0.82, 'w' => 0.4, 'h' => 0.04, 'text' => 'Jane Doe, 1 May 2026', 'size' => 14, 'color' => '#17181C'],
            ])
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.name', 'contract_signed.pdf')
            ->assertSet('result.pages', 2)
            ->assertSee('Signature added.');

        $output = $this->outputPath($component);
        $this->assertSame('0 1', $this->fitz($output, 'print(len(doc[0].get_images()), len(doc[1].get_images()))'));
        $this->assertStringContainsString('Jane Doe, 1 May 2026', $this->fitz($output, 'print(doc[1].get_text())'));

        // The image sits in the box the user placed (bottom-left of page 2).
        $box = explode(' ', $this->fitz($output, 'r = doc[1].get_image_rects(doc[1].get_images()[0][0])[0]; print(round(r.x0), round(r.y1))'));
        $this->assertEqualsWithDelta(61, (int) $box[0], 2);
        $this->assertLessThanOrEqual(634, (int) $box[1]);
    }

    public function test_sign_requires_a_signature(): void
    {
        Livewire::test(PdfTool::class, ['tool' => 'sign-pdf'])
            ->set('uploads', [$this->textPdf(['Please sign here'])])
            ->set('options.placements', [
                ['kind' => 'text', 'page' => 1, 'x' => 0.1, 'y' => 0.8, 'w' => 0.4, 'h' => 0.04, 'text' => 'Jane Doe'],
            ])
            ->call('process')
            ->assertHasErrors(['process'])
            ->assertSee('Add your signature to the page first.');
    }

    // --- Redact ------------------------------------------------------------

    public function test_redact_removes_terms_and_patterns(): void
    {
        $component = Livewire::test(PdfTool::class, ['tool' => 'redact-pdf'])
            ->set('uploads', [$this->textPdf([
                "Agent Falcon met the client.\nMail jane.doe@example.com today.",
                "Call +1 555-123-4567 or pay with 4111 1111 1111 1111.\nfalcon rests here.",
                'Nothing sensitive on this page.',
            ], 'memo.pdf')])
            ->set('options.terms', "Falcon\n")
            ->set('options.emails', true)
            ->set('options.phones', true)
            ->set('options.cards', true)
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.name', 'memo_redacted.pdf')
            ->assertSee('5 areas redacted on 2 pages.');

        $text = $this->fitz($this->outputPath($component), 'print("".join(p.get_text() for p in doc)); print(doc.metadata.get("producer"))');

        foreach (['Falcon', 'falcon', 'jane.doe@example.com', '555-123-4567', '4111'] as $gone) {
            $this->assertStringNotContainsString($gone, $text);
        }

        $this->assertStringContainsString('Agent', $text);
        $this->assertStringContainsString('Nothing sensitive on this page.', $text);
    }

    public function test_redact_can_match_case_and_redact_drawn_areas(): void
    {
        $component = Livewire::test(PdfTool::class, ['tool' => 'redact-pdf'])
            ->set('uploads', [$this->textPdf(["Falcon and falcon\nKeep this line"])])
            ->set('options.terms', 'Falcon')
            ->set('options.matchCase', true)
            ->set('options.areas', [
                // Covers the second line (baseline at y = 72 + 19.2).
                ['kind' => 'redact', 'page' => 1, 'x' => 0.1, 'y' => 0.1, 'w' => 0.5, 'h' => 0.02],
            ])
            ->call('process')
            ->assertHasNoErrors()
            ->assertSee('2 areas redacted on 1 page.');

        $text = $this->fitz($this->outputPath($component), 'print(doc[0].get_text())');
        $this->assertStringNotContainsString('Falcon', $text);
        $this->assertStringContainsString('falcon', $text);
        $this->assertStringNotContainsString('Keep this line', $text);
    }

    public function test_redact_reports_when_nothing_matches(): void
    {
        Livewire::test(PdfTool::class, ['tool' => 'redact-pdf'])
            ->set('uploads', [$this->textPdf(['Plain text only'])])
            ->set('options.terms', 'Falcon')
            ->set('options.emails', true)
            ->call('process')
            ->assertHasErrors(['process'])
            ->assertSee('Nothing to redact was found.');
    }

    // --- Compare -----------------------------------------------------------

    public function test_compare_reports_word_changes(): void
    {
        $component = Livewire::test(PdfTool::class, ['tool' => 'compare-pdf'])
            ->set('uploads', [
                $this->textPdf(["The quick brown fox\njumps over the dog.", 'Second page stays the same.'], 'v1.pdf'),
                $this->textPdf(["The quick red fox\njumps over the lazy dog.", 'Second page stays the same.', 'A new third page.'], 'v2.pdf'),
            ])
            ->assertHasNoErrors()
            ->assertCount('files', 2)
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.name', 'v1_comparison.pdf')
            ->assertSet('result.meta.changes', 3)
            ->assertSee('3 changes found.');

        $output = $this->outputPath($component);
        // Summary page + one side-by-side page per page of the longer file.
        $this->assertSame('4 1248', $this->fitz($output, 'print(doc.page_count, round(doc[1].rect.width))'));

        $summary = $this->fitz($output, 'print(doc[0].get_text())');
        $this->assertStringContainsString('p.1: ‘brown’ → ‘red’', $summary);
        $this->assertStringContainsString('p.1: added ‘lazy’', $summary);
        $this->assertStringContainsString('added ‘A new third page.’', $summary);
    }

    public function test_compare_identical_files(): void
    {
        Livewire::test(PdfTool::class, ['tool' => 'compare-pdf'])
            ->set('uploads', [$this->textPdf(['Same text'], 'a.pdf'), $this->textPdf(['Same text'], 'b.pdf')])
            ->call('process')
            ->assertHasNoErrors()
            ->assertSet('result.meta.changes', 0)
            ->assertSee('No text differences found.');
    }

    public function test_compare_needs_two_files(): void
    {
        Livewire::test(PdfTool::class, ['tool' => 'compare-pdf'])
            ->set('uploads', [$this->textPdf(['Only one'])])
            ->call('process')
            ->assertHasErrors(['process'])
            ->assertSet('result', null);
    }
}
