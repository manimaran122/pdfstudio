<?php

namespace App\Support;

use App\Services\Pdf\Processors\Processor;
use Closure;

/**
 * Tools served by the generic PdfTool component, keyed by slug and loaded
 * from app/Support/ToolDefinitions/*.php (one file per category). Merge PDF
 * has its own component and is not listed here.
 *
 * Definition keys:
 *   processor   Processor class, or a closure returning a Processor.
 *   accept      Allowed upload extensions.
 *   noun        [singular, plural] for what the user uploads.
 *   multiple    Accept several files (default false). "fileLabels" names
 *               each slot and fixes the count, e.g. ['Original', 'Revised'].
 *   readCheck   Reject PDFs pdftk can't open (default true; off for
 *               Repair and Unlock, which exist to handle those files).
 *   output      Extension of the result (default "pdf"). A processor may
 *               return a different "file" at run time (e.g. zip).
 *   outputName  Fixed base name for the result instead of the upload's.
 *   suffix      Appended to the base name, e.g. "_compressed".
 *   action / working / done   Button text, busy text, success sentence.
 *   note        Help text shown on the upload and options steps.
 *   savings     Show "% smaller" on the result (Compress).
 *   preview     Show the processor's "preview" markdown on the result.
 *   fields      Read the PDF's form fields after upload (PDF Forms).
 *   requires    "ai" when the tool needs ANTHROPIC_API_KEY.
 *   options     name => option; types:
 *     choice      radio cards; "choices" => [value => [label, hint]]
 *     select      dropdown; "choices" => [value => label]
 *     toggle      checkbox; "hint"
 *     text / password / textarea   "placeholder", "max", "required"
 *     number      "min", "max", "step", "unit"
 *     range       slider; "min", "max", "step", "unit"
 *     color       color picker (hex)
 *     image       one uploaded image (PNG/JPG), stored as an asset id
 *     pages       page grid; "mode" => select | organize | rotate
 *     placements  click-to-place page editor; "kinds" => [kind, ...]
 *                 from: text, rect, highlight, image, note, signature,
 *                 redact, field-text, field-checkbox
 *     form        inputs for the PDF's own form fields (needs "fields")
 *   Any option may carry "when" => [otherOption => [values...]] to show it
 *   only while another option has one of those values.
 */
class PdfTools
{
    private static ?array $definitions = null;

    public static function all(): array
    {
        if (self::$definitions === null) {
            self::$definitions = [];

            foreach (glob(__DIR__.'/ToolDefinitions/*.php') as $file) {
                self::$definitions += require $file;
            }
        }

        return self::$definitions;
    }

    public static function has(string $slug): bool
    {
        return array_key_exists($slug, self::all());
    }

    public static function get(string $slug): array
    {
        return [
            'multiple' => false,
            'fileLabels' => null,
            'readCheck' => true,
            'output' => 'pdf',
            'outputName' => null,
            'suffix' => '',
            'savings' => false,
            'preview' => false,
            'fields' => false,
            'requires' => null,
            'note' => null,
            'options' => [],
            ...self::all()[$slug],
        ];
    }

    /**
     * Whether the tool's external requirements (e.g. an API key) are met.
     */
    public static function available(string $slug): bool
    {
        return match (self::get($slug)['requires']) {
            'ai' => filled(config('pdf.ai.key')),
            default => true,
        };
    }

    public static function processor(string $slug): Processor
    {
        $processor = self::get($slug)['processor'];

        return $processor instanceof Closure ? $processor() : app($processor);
    }

    /**
     * Default option values for a tool.
     *
     * @return array<string, mixed>
     */
    public static function defaults(string $slug): array
    {
        return array_map(
            fn (array $option) => $option['default'] ?? match ($option['type']) {
                'toggle' => false,
                'pages', 'placements', 'form' => [],
                'image' => null,
                'number', 'range' => $option['min'] ?? 0,
                default => '',
            },
            self::get($slug)['options'],
        );
    }
}
