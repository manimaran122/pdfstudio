<?php

// Organize PDF tools (Merge PDF has its own component). See
// App\Support\PdfTools for the definition format.

use App\Services\Pdf\Processors\Organize;

return [
    'split-pdf' => [
        'processor' => Organize\SplitPdf::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Split PDF',
        'working' => 'Splitting…',
        'suffix' => '_split',
        'done' => 'Your PDF has been split.',
        'note' => 'Several parts are downloaded together as a ZIP.',
        'options' => [
            'mode' => ['type' => 'choice', 'label' => 'Split by', 'default' => 'ranges', 'choices' => [
                'ranges' => ['Page ranges', 'Each range becomes its own PDF.'],
                'every' => ['Fixed size', 'A new PDF every few pages.'],
                'all' => ['Every page', 'Each page becomes its own PDF.'],
            ]],
            'ranges' => ['type' => 'text', 'label' => 'Ranges', 'placeholder' => 'e.g. 1-3, 4-6, 9', 'hint' => 'Separate ranges with commas. “7-” means page 7 to the end.', 'max' => 500, 'required' => true, 'when' => ['mode' => ['ranges']]],
            'every' => ['type' => 'number', 'label' => 'Pages per PDF', 'default' => 2, 'min' => 1, 'max' => 1000, 'unit' => 'pages', 'when' => ['mode' => ['every']]],
        ],
    ],
    'remove-pages' => [
        'processor' => Organize\RemovePages::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Remove pages',
        'working' => 'Removing pages…',
        'suffix' => '_removed',
        'done' => 'The pages have been removed.',
        'options' => [
            'pages' => ['type' => 'pages', 'mode' => 'select', 'label' => 'Pages to remove', 'hint' => 'Click the pages you want to delete.'],
        ],
    ],
    'extract-pages' => [
        'processor' => Organize\ExtractPages::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Extract pages',
        'working' => 'Extracting pages…',
        'suffix' => '_extracted',
        'done' => 'Your pages have been extracted.',
        'options' => [
            'pages' => ['type' => 'pages', 'mode' => 'select', 'label' => 'Pages to extract', 'hint' => 'Click the pages you want to keep.'],
            'as' => ['type' => 'choice', 'label' => 'Save as', 'default' => 'combined', 'choices' => [
                'combined' => ['One PDF', 'All selected pages in a single file.'],
                'separate' => ['Separate PDFs', 'One file per page, downloaded as a ZIP.'],
            ]],
        ],
    ],
    'organize-pdf' => [
        'processor' => Organize\OrganizePdf::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Save changes',
        'working' => 'Rebuilding your PDF…',
        'suffix' => '_organized',
        'done' => 'Your PDF has been reorganized.',
        'options' => [
            'pages' => ['type' => 'pages', 'mode' => 'organize', 'label' => 'Pages', 'hint' => 'Drag to reorder. Rotate, duplicate or delete pages as needed.'],
        ],
    ],
    'scan-to-pdf' => [
        'processor' => Organize\ScanToPdf::class,
        'accept' => ['jpg', 'jpeg', 'png'],
        'noun' => ['image', 'images'],
        'multiple' => true,
        'action' => 'Create PDF',
        'working' => 'Creating your PDF…',
        'suffix' => '',
        'outputName' => 'scan',
        'done' => 'Your scan is now a PDF.',
        'note' => 'On a phone, the upload button can open your camera. Each image becomes one page, in the order shown.',
        'options' => [
            'pageSize' => ['type' => 'choice', 'label' => 'Page size', 'default' => 'fit', 'choices' => [
                'fit' => ['Same as image', 'Each page matches its image.'],
                'a4' => ['A4', '210 × 297 mm, image fitted to the page.'],
                'letter' => ['US Letter', '8.5 × 11 in, image fitted to the page.'],
            ]],
            'ocr' => ['type' => 'toggle', 'label' => 'Make text searchable (OCR)', 'hint' => 'Recognizes the text and straightens crooked pages. Takes a little longer.', 'default' => true],
        ],
    ],
];
