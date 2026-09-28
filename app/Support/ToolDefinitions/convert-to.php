<?php

// Convert to PDF tools. See App\Support\PdfTools for the definition format.

use App\Services\Pdf\Processors;

return [
    'jpg-to-pdf' => [
        'processor' => Processors\ImagesToPdf::class,
        'accept' => ['jpg', 'jpeg', 'png'],
        'noun' => ['image', 'images'],
        'multiple' => true,
        'action' => 'Convert to PDF',
        'working' => 'Converting…',
        'suffix' => '',
        'outputName' => 'images',
        'done' => 'Your images are now one PDF.',
        'options' => [
            'pageSize' => ['type' => 'choice', 'label' => 'Page size', 'default' => 'fit', 'choices' => [
                'fit' => ['Same as image', 'Each page matches its image.'],
                'a4' => ['A4', '210 × 297 mm, image fitted to the page.'],
                'letter' => ['US Letter', '8.5 × 11 in, image fitted to the page.'],
            ]],
            'margin' => ['type' => 'toggle', 'label' => 'Add a margin', 'hint' => 'Leaves 1 cm of white space around each image.', 'default' => false, 'when' => ['pageSize' => ['a4', 'letter']]],
        ],
    ],
    'word-to-pdf' => [
        'processor' => Processors\OfficeToPdf::class,
        'accept' => ['doc', 'docx', 'odt', 'rtf', 'txt'],
        'noun' => ['Word document', 'Word documents'],
        'action' => 'Convert to PDF',
        'working' => 'Converting…',
        'suffix' => '',
        'done' => 'Your document is now a PDF.',
        'note' => 'Layout and fonts are kept as closely as possible.',
        'options' => [],
    ],
    'powerpoint-to-pdf' => [
        'processor' => Processors\OfficeToPdf::class,
        'accept' => ['ppt', 'pptx', 'odp'],
        'noun' => ['presentation', 'presentations'],
        'action' => 'Convert to PDF',
        'working' => 'Converting…',
        'suffix' => '',
        'done' => 'Your slides are now a PDF.',
        'note' => 'Each slide becomes one page. '.'Layout and fonts are kept as closely as possible.',
        'options' => [],
    ],
    'excel-to-pdf' => [
        'processor' => Processors\OfficeToPdf::class,
        'accept' => ['xls', 'xlsx', 'ods', 'csv'],
        'noun' => ['spreadsheet', 'spreadsheets'],
        'action' => 'Convert to PDF',
        'working' => 'Converting…',
        'suffix' => '',
        'done' => 'Your spreadsheet is now a PDF.',
        'note' => 'Every sheet is included, using each sheet’s print area and page setup.',
        'options' => [],
    ],
    'html-to-pdf' => [
        'processor' => fn () => Processors\OfficeToPdf::html(),
        'accept' => ['html', 'htm'],
        'noun' => ['HTML file', 'HTML files'],
        'action' => 'Convert to PDF',
        'working' => 'Converting…',
        'suffix' => '',
        'done' => 'Your page is now a PDF.',
        'note' => 'Upload a saved .html file. Complex layouts and scripts may not render exactly as in a browser.',
        'options' => [],
    ],
];
