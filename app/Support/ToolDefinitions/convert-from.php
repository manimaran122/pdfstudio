<?php

// Convert from PDF tools. See App\Support\PdfTools for the definition format.

use App\Services\Pdf\Processors\ConvertFrom;

return [
    'pdf-to-jpg' => [
        'processor' => ConvertFrom\PdfToJpg::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'output' => 'zip',
        'action' => 'Convert to JPG',
        'working' => 'Converting…',
        'done' => 'Your images are ready.',
        'note' => 'Several images are downloaded together as a ZIP file.',
        'options' => [
            'mode' => ['type' => 'choice', 'label' => 'What to save', 'default' => 'pages', 'choices' => [
                'pages' => ['Convert pages', 'Each page becomes one JPG.'],
                'images' => ['Extract images', 'Save the photos and pictures inside the PDF.'],
            ]],
            'quality' => ['type' => 'choice', 'label' => 'Image quality', 'default' => '150', 'when' => ['mode' => ['pages']], 'choices' => [
                '72' => ['Low', '72 DPI. Small files for screens.'],
                '150' => ['Medium', '150 DPI. Good for most uses.'],
                '300' => ['High', '300 DPI. Sharp enough to print.'],
            ]],
        ],
    ],
    'pdf-to-word' => [
        'processor' => ConvertFrom\PdfToWord::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'output' => 'docx',
        'action' => 'Convert to Word',
        'working' => 'Converting…',
        'done' => 'Your PDF is now a Word document.',
        'note' => 'Text, headings, images and tables become editable. Complex layouts are simplified. Scanned PDFs need OCR PDF first.',
        'options' => [],
    ],
    'pdf-to-powerpoint' => [
        'processor' => ConvertFrom\PdfToPowerPoint::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'output' => 'pptx',
        'action' => 'Convert to PowerPoint',
        'working' => 'Converting…',
        'done' => 'Your PDF is now a PowerPoint deck.',
        'note' => 'Each page becomes one slide with editable text boxes.',
        'options' => [],
    ],
    'pdf-to-excel' => [
        'processor' => ConvertFrom\PdfToExcel::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'output' => 'xlsx',
        'action' => 'Convert to Excel',
        'working' => 'Converting…',
        'done' => 'Your PDF is now an Excel workbook.',
        'note' => 'Numbers are stored as numbers, so you can calculate with them right away.',
        'options' => [
            'mode' => ['type' => 'choice', 'label' => 'What to export', 'default' => 'tables', 'choices' => [
                'tables' => ['Tables only', 'Each table gets its own sheet.'],
                'text' => ['Every text line', 'One sheet per page; wide gaps split columns.'],
            ]],
        ],
    ],
    'pdf-to-pdfa' => [
        'processor' => ConvertFrom\PdfToPdfa::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Convert to PDF/A',
        'working' => 'Converting…',
        'suffix' => '_pdfa',
        'done' => 'Your PDF is ready for archiving.',
        'note' => 'PDF/A is the standard for long-term archiving: fonts and colors are embedded so the file looks the same for years.',
        'options' => [
            'level' => ['type' => 'choice', 'label' => 'Standard', 'default' => 'pdfa-2', 'choices' => [
                'pdfa-2' => ['PDF/A-2b', 'Recommended. Accepted by most archives.'],
                'pdfa-1' => ['PDF/A-1b', 'Oldest version, for systems that require it.'],
                'pdfa-3' => ['PDF/A-3b', 'Like PDF/A-2b, and allows attached files.'],
            ]],
        ],
    ],
    'pdf-to-markdown' => [
        'processor' => ConvertFrom\PdfToMarkdown::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'output' => 'md',
        'action' => 'Convert to Markdown',
        'working' => 'Converting…',
        'done' => 'Your PDF is now Markdown.',
        'preview' => true,
        'note' => 'Headings, lists, bold, italic and tables are kept. Runs on our server, without AI.',
        'options' => [
            'pageBreaks' => ['type' => 'toggle', 'label' => 'Add page breaks as ---', 'hint' => 'Marks where each PDF page ends.', 'default' => false],
        ],
    ],
];
