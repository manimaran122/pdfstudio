<?php

namespace App\Services\Pdf\Processors\ConvertFrom;

use App\Services\Pdf\Processors\Processor;

/**
 * Markdown built locally from the PDF's text: headings from font sizes,
 * lists, bold/italic and GFM tables. No AI involved.
 */
class PdfToMarkdown extends Processor
{
    private const PREVIEW_CHARS = 4000;

    public function process(array $inputs, string $output, array $options): ?array
    {
        $result = $this->python('pdf_to_markdown', [
            'input' => $inputs[0],
            'output' => $output,
            'pageBreaks' => (bool) ($options['pageBreaks'] ?? false),
            'previewChars' => self::PREVIEW_CHARS,
        ]);
        $this->assertOutput($output);

        return [
            'meta' => ['summary' => $result['chars'] > self::PREVIEW_CHARS
                ? 'Here’s the start of it. Download the file for the rest.'
                : 'Here’s how it looks.'],
            'preview' => $result['preview'],
        ];
    }
}
