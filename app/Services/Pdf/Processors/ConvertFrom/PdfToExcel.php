<?php

namespace App\Services\Pdf\Processors\ConvertFrom;

use App\Services\Pdf\Processors\Processor;
use Illuminate\Support\Str;

/**
 * Tables (PyMuPDF find_tables) or every text line into an Excel workbook,
 * one sheet per table or page. Numeric-looking cells are stored as numbers
 * so they can be summed straight away.
 */
class PdfToExcel extends Processor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        $mode = $options['mode'] ?? 'tables';
        $result = $this->python('pdf_to_xlsx', ['input' => $inputs[0], 'output' => $output, 'mode' => $mode]);
        $this->assertOutput($output);

        $summary = $mode === 'tables'
            ? 'Found '.$result['tables'].' '.Str::plural('table', $result['tables']).', one per sheet.'
            : 'Exported '.$result['rows'].' '.Str::plural('line', $result['rows']).' of text.';

        return ['meta' => ['summary' => $summary]];
    }
}
