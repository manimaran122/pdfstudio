<?php

// Edit PDF tools. See App\Support\PdfTools for the definition format.

use App\Services\Pdf\Processors\Edit;

return [
    'rotate-pdf' => [
        'processor' => Edit\RotatePdf::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Rotate PDF',
        'working' => 'Rotating…',
        'suffix' => '_rotated',
        'done' => 'Your pages have been rotated.',
        'options' => [
            'rotation' => ['type' => 'pages', 'mode' => 'rotate', 'label' => 'Rotate pages'],
        ],
    ],
    'add-page-numbers' => [
        'processor' => Edit\AddPageNumbers::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Add page numbers',
        'working' => 'Numbering pages…',
        'suffix' => '_numbered',
        'done' => 'Page numbers have been added.',
        'options' => [
            'position' => ['type' => 'choice', 'label' => 'Position', 'default' => 'bottom-center', 'choices' => [
                'top-left' => ['Top left', ''],
                'top-center' => ['Top center', ''],
                'top-right' => ['Top right', ''],
                'bottom-left' => ['Bottom left', ''],
                'bottom-center' => ['Bottom center', ''],
                'bottom-right' => ['Bottom right', ''],
            ]],
            'format' => ['type' => 'select', 'label' => 'Format', 'default' => '{n}', 'choices' => [
                '{n}' => '1',
                'Page {n}' => 'Page 1',
                'Page {n} of {total}' => 'Page 1 of 10',
                '{n} / {total}' => '1 / 10',
            ]],
            'start' => ['type' => 'number', 'label' => 'First number', 'default' => 1, 'min' => 0, 'max' => 100000],
            'size' => ['type' => 'number', 'label' => 'Font size', 'default' => 11, 'min' => 6, 'max' => 48, 'unit' => 'pt'],
            'color' => ['type' => 'color', 'label' => 'Color', 'default' => '#17181C'],
            'margin' => ['type' => 'number', 'label' => 'Margin', 'default' => 10, 'min' => 0, 'max' => 100, 'unit' => 'mm'],
            'skipFirst' => ['type' => 'toggle', 'label' => 'Skip the first page', 'hint' => 'Leaves a cover page unnumbered.', 'default' => false],
        ],
    ],
    'add-watermark' => [
        'processor' => Edit\AddWatermark::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Add watermark',
        'working' => 'Adding watermark…',
        'suffix' => '_watermarked',
        'done' => 'Your watermark has been added.',
        'options' => [
            'type' => ['type' => 'choice', 'label' => 'Watermark', 'default' => 'text', 'choices' => [
                'text' => ['Text', 'A word or short phrase.'],
                'image' => ['Image', 'A logo or stamp (PNG or JPG).'],
            ]],
            'text' => ['type' => 'text', 'label' => 'Text', 'default' => 'CONFIDENTIAL', 'max' => 100, 'required' => true, 'when' => ['type' => ['text']]],
            'size' => ['type' => 'number', 'label' => 'Font size', 'default' => 48, 'min' => 8, 'max' => 200, 'unit' => 'pt', 'when' => ['type' => ['text']]],
            'color' => ['type' => 'color', 'label' => 'Color', 'default' => '#D92D20', 'when' => ['type' => ['text']]],
            'image' => ['type' => 'image', 'label' => 'Image', 'required' => true, 'when' => ['type' => ['image']]],
            'scale' => ['type' => 'range', 'label' => 'Size', 'default' => 40, 'min' => 5, 'max' => 100, 'unit' => '%', 'hint' => 'Width as a share of the page.', 'when' => ['type' => ['image']]],
            'opacity' => ['type' => 'range', 'label' => 'Opacity', 'default' => 40, 'min' => 10, 'max' => 100, 'step' => 5, 'unit' => '%'],
            'rotation' => ['type' => 'choice', 'label' => 'Angle', 'default' => 'diagonal', 'choices' => [
                'diagonal' => ['Diagonal', 'Turned 45°.'],
                'horizontal' => ['Horizontal', 'Level with the text.'],
            ]],
            'position' => ['type' => 'choice', 'label' => 'Layout', 'default' => 'center', 'choices' => [
                'center' => ['Centered', 'Once, in the middle of each page.'],
                'tiled' => ['Tiled', 'Repeated across each page.'],
            ]],
            'behind' => ['type' => 'toggle', 'label' => 'Behind page content', 'hint' => 'Text and images on the page stay on top.', 'default' => false],
        ],
    ],
    'crop-pdf' => [
        'processor' => Edit\CropPdf::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Crop PDF',
        'working' => 'Cropping…',
        'suffix' => '_cropped',
        'done' => 'Your pages have been cropped.',
        'note' => 'Cropping hides the area outside the crop box; the content is still in the file.',
        'options' => [
            'mode' => ['type' => 'choice', 'label' => 'Crop by', 'default' => 'margins', 'choices' => [
                'margins' => ['Margins', 'Trim the same amount from every page.'],
                'area' => ['Area', 'Draw the part of the page to keep.'],
            ]],
            'top' => ['type' => 'number', 'label' => 'Top', 'default' => 10, 'min' => 0, 'max' => 500, 'unit' => 'mm', 'when' => ['mode' => ['margins']]],
            'right' => ['type' => 'number', 'label' => 'Right', 'default' => 10, 'min' => 0, 'max' => 500, 'unit' => 'mm', 'when' => ['mode' => ['margins']]],
            'bottom' => ['type' => 'number', 'label' => 'Bottom', 'default' => 10, 'min' => 0, 'max' => 500, 'unit' => 'mm', 'when' => ['mode' => ['margins']]],
            'left' => ['type' => 'number', 'label' => 'Left', 'default' => 10, 'min' => 0, 'max' => 500, 'unit' => 'mm', 'when' => ['mode' => ['margins']]],
            'area' => ['type' => 'placements', 'label' => 'Area to keep', 'kinds' => ['rect'], 'hint' => 'Draw the area to keep.', 'when' => ['mode' => ['area']]],
            'allPages' => ['type' => 'toggle', 'label' => 'Apply to all pages', 'hint' => 'Otherwise only the page you drew on is cropped.', 'default' => true, 'when' => ['mode' => ['area']]],
        ],
    ],
    'edit-pdf' => [
        'processor' => Edit\EditPdf::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Save changes',
        'working' => 'Saving…',
        'suffix' => '_edited',
        'done' => 'Your changes have been saved.',
        'options' => [
            'items' => ['type' => 'placements', 'label' => 'Edit pages', 'kinds' => ['text', 'rect', 'highlight', 'image', 'note']],
        ],
    ],
    'pdf-forms' => [
        'processor' => Edit\PdfForms::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Save form',
        'working' => 'Saving form…',
        'suffix' => '_form',
        'done' => 'Your form has been saved.',
        'fields' => true,
        'options' => [
            'values' => ['type' => 'form', 'label' => 'Fill in fields'],
            'add' => ['type' => 'placements', 'label' => 'Add fields', 'kinds' => ['field-text', 'field-checkbox'], 'hint' => 'Pick a field type, then click or drag on the page to place it.'],
            'flatten' => ['type' => 'toggle', 'label' => 'Flatten form (make fields uneditable)', 'hint' => 'Values become part of the page.', 'default' => false],
        ],
    ],
];
