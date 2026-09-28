<?php

namespace App\Services\Pdf\Processors\Ai;

use App\Services\Ai\TextModel;
use App\Services\Pdf\Processors\Processor;

/**
 * Summarizes a PDF with Claude and returns the summary as Markdown.
 *
 * PDFs within the API's document limits are sent as-is, so Claude also
 * sees tables, charts and scanned pages; larger ones are sent as their
 * extracted text.
 */
class AiSummarizer extends Processor
{
    private const MAX_PAGES = 600;

    private const LENGTHS = [
        'brief' => 'Write one short paragraph (at most 120 words) that captures the main point.',
        'standard' => 'Write a two-to-three sentence overview, then a "Key points" section of 5–8 bullets, then a "Takeaways" section if there are decisions, deadlines, numbers or action items.',
        'detailed' => 'Write a short overview, then summarize the document section by section with a heading for each section, keeping important figures, names and dates.',
    ];

    public function __construct(private TextModel $model) {}

    public function process(array $inputs, string $output, array $options): ?array
    {
        $name = $options['_names'][0] ?? 'document.pdf';
        $pages = $options['_pages'][0] ?? null;
        $fitsAsDocument = filesize($inputs[0]) <= config('pdf.ai.max_document_mb') * 1024 * 1024
            && ($pages === null || $pages <= self::MAX_PAGES);

        $document = $fitsAsDocument
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'mediaType' => 'application/pdf', 'data' => base64_encode(file_get_contents($inputs[0]))]]
            : ['type' => 'text', 'text' => $this->pagesAsText($inputs[0])];

        $language = $options['language'] === 'same' ? 'the same language as the document' : $options['language'];
        $focus = trim((string) $options['focus']);

        $summary = $this->model->complete(
            system: 'You summarize documents for busy colleagues. Be accurate and neutral, and only state what the document supports. Reply with the summary in Markdown only: no preamble, and no title line.',
            content: [
                $document,
                ['type' => 'text', 'text' => implode("\n\n", array_filter([
                    'Summarize the attached document, "'.$name.'".',
                    self::LENGTHS[$options['length']],
                    "Write the summary in {$language}.",
                    $focus !== '' ? "Pay particular attention to: {$focus}" : null,
                ]))],
            ],
        );

        $markdown = '# Summary of '.pathinfo($name, PATHINFO_FILENAME)."\n\n".$summary."\n";
        file_put_contents($output, $markdown);

        return ['preview' => $summary, 'meta' => ['summary' => 'Here’s the summary. Download it as a Markdown file to keep it.']];
    }

    private function pagesAsText(string $path): string
    {
        $pages = $this->python('ai_text', ['input' => $path])['pages'];

        return collect($pages)->map(fn ($text, $i) => '<page number="'.($i + 1)."\">\n{$text}\n</page>")->implode("\n");
    }
}
