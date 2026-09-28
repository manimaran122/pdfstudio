<?php

namespace App\Services\Pdf\Processors\ConvertFrom;

use App\Services\Pdf\Processors\Processor;
use Illuminate\Support\Str;

/**
 * Rebuilds the PDF's text as Word paragraphs (headings, lists, bold and
 * italic kept) with its images and tables, via PyMuPDF + python-docx.
 * Built from the text rather than the layout so the result is editable.
 */
class PdfToWord extends Processor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        $result = $this->python('pdf_to_docx', ['input' => $inputs[0], 'output' => $output]);
        $this->assertOutput($output);

        $parts = collect(['images' => 'image', 'tables' => 'table'])
            ->filter(fn ($noun, $key) => ($result[$key] ?? 0) > 0)
            ->map(fn ($noun, $key) => $result[$key].' '.Str::plural($noun, $result[$key]));

        return ['meta' => ['summary' => 'Converted '.$result['pages'].' '.Str::plural('page', $result['pages'])
            .($parts->isEmpty() ? '' : ' with '.$parts->join(', ', ' and ')).'.']];
    }
}
