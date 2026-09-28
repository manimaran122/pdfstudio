<?php

// Optimize PDF tools. See App\Support\PdfTools for the definition format.

use App\Services\Pdf\Processors;

return [
    'compress-pdf' => [
        'processor' => Processors\CompressPdf::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Compress PDF',
        'working' => 'Compressing…',
        'suffix' => '_compressed',
        'done' => 'Your PDF has been compressed.',
        'savings' => true,
        'options' => [
            'level' => ['type' => 'choice', 'label' => 'Compression level', 'default' => 'recommended', 'choices' => [
                'recommended' => ['Recommended', 'Good quality, much smaller file.'],
                'extreme' => ['Extreme', 'Smallest file, lower image quality.'],
                'low' => ['Light', 'Best quality, less reduction.'],
            ]],
        ],
    ],
    'repair-pdf' => [
        'processor' => Processors\RepairPdf::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Repair PDF',
        'working' => 'Repairing…',
        'suffix' => '_repaired',
        'done' => 'We rebuilt your PDF.',
        'readCheck' => false,
        'note' => 'Rebuilds the file structure so damaged PDFs open again. Content that is missing from the file can’t be recovered.',
        'options' => [],
    ],
    'ocr-pdf' => [
        'processor' => Processors\OcrPdf::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Run OCR',
        'working' => 'Recognizing text…',
        'suffix' => '_ocr',
        'done' => 'Your PDF is now searchable.',
        'note' => 'Text recognition runs in English and can take a minute for long documents.',
        'options' => [
            'mode' => ['type' => 'choice', 'label' => 'Pages with existing text', 'default' => 'skip', 'choices' => [
                'skip' => ['Skip them', 'Only scanned pages are processed.'],
                'force' => ['OCR every page', 'Rasterizes all pages; existing text is replaced.'],
            ]],
            'deskew' => ['type' => 'toggle', 'label' => 'Straighten crooked scans', 'hint' => 'Deskews each page before recognizing text.', 'default' => true],
        ],
    ],
];
