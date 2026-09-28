<?php

// PDF Security tools. See App\Support\PdfTools for the definition format.

use App\Services\Pdf\Processors\Security;

return [
    'unlock-pdf' => [
        'processor' => Security\UnlockPdf::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Unlock PDF',
        'working' => 'Unlocking…',
        'suffix' => '_unlocked',
        'done' => 'Your PDF is no longer password protected.',
        'readCheck' => false,
        'note' => 'You need the password that opens the file. We can’t unlock a PDF without it.',
        'options' => [
            'password' => ['type' => 'password', 'label' => 'Password', 'placeholder' => 'Password that opens the PDF', 'hint' => 'Leave blank if the file opens without one and only restricts printing or copying.'],
        ],
    ],
    'protect-pdf' => [
        'processor' => Security\ProtectPdf::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Protect PDF',
        'working' => 'Encrypting…',
        'suffix' => '_protected',
        'done' => 'Your PDF is now password protected.',
        'note' => 'Uses 256-bit AES encryption. Keep the password safe: it can’t be recovered.',
        'options' => [
            'password' => ['type' => 'password', 'label' => 'Password', 'placeholder' => 'At least 6 characters', 'required' => true, 'max' => 128],
            'confirm' => ['type' => 'password', 'label' => 'Confirm password', 'placeholder' => 'Type it again', 'required' => true, 'max' => 128],
            'print' => ['type' => 'toggle', 'label' => 'Allow printing', 'default' => true],
            'copy' => ['type' => 'toggle', 'label' => 'Allow copying text', 'default' => false],
            'edit' => ['type' => 'toggle', 'label' => 'Allow editing', 'hint' => 'Changing pages, filling forms and adding comments.', 'default' => false],
        ],
    ],
    'sign-pdf' => [
        'processor' => Security\SignPdf::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Sign PDF',
        'working' => 'Signing…',
        'suffix' => '_signed',
        'done' => 'Your signature has been added.',
        'note' => 'Adds your signature as an image on the page. This is not a certificate-based digital signature.',
        'options' => [
            'placements' => ['type' => 'placements', 'label' => 'Sign your document', 'hint' => 'Draw your signature, then click the page to place it. Add text for your printed name or the date.', 'kinds' => ['signature', 'text']],
        ],
    ],
    'redact-pdf' => [
        'processor' => Security\RedactPdf::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'action' => 'Redact PDF',
        'working' => 'Redacting…',
        'suffix' => '_redacted',
        'done' => 'Your PDF has been redacted.',
        'note' => 'Redacted text is removed from the file, not just covered. Scanned pages have no text to search, so draw boxes over them instead.',
        'options' => [
            'terms' => ['type' => 'textarea', 'label' => 'Words or phrases to redact', 'placeholder' => "One per line, e.g.\nJane Doe\nProject Falcon", 'rows' => 4],
            'emails' => ['type' => 'toggle', 'label' => 'Email addresses', 'default' => false],
            'phones' => ['type' => 'toggle', 'label' => 'Phone numbers', 'default' => false],
            'cards' => ['type' => 'toggle', 'label' => 'Credit card numbers', 'default' => false],
            'matchCase' => ['type' => 'toggle', 'label' => 'Match case', 'hint' => 'Only redact words with the same capitals.', 'default' => false],
            'metadata' => ['type' => 'toggle', 'label' => 'Remove document metadata', 'hint' => 'Clears the title, author and other hidden details.', 'default' => true],
            'areas' => ['type' => 'placements', 'label' => 'Redact areas', 'hint' => 'Optional. Drag over anything else to black out, such as images or signatures.', 'kinds' => ['redact']],
        ],
    ],
    'compare-pdf' => [
        'processor' => Security\ComparePdf::class,
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'multiple' => true,
        'fileLabels' => ['Original', 'Revised'],
        'action' => 'Compare PDFs',
        'working' => 'Comparing…',
        'suffix' => '_comparison',
        'done' => 'Your comparison report is ready.',
        'note' => 'Compares the text of both files word by word. Removed words are marked red, added words green.',
        'options' => [],
    ],
];
