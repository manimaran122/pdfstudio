<?php

// PDF Intelligence tools backed by the Claude API. See App\Support\PdfTools
// for the definition format. PDF to Markdown runs locally and lives with
// the other conversions in convert-from.php.

use App\Services\Pdf\Processors\Ai;

$languages = Ai\TranslateLanguages::NAMES;

return [
    'ai-summarizer' => [
        'processor' => Ai\AiSummarizer::class,
        'requires' => 'ai',
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'output' => 'md',
        'suffix' => '_summary',
        'action' => 'Summarize',
        'working' => 'Reading and summarizing…',
        'done' => 'Here’s the summary.',
        'preview' => true,
        'note' => 'The document is sent to Anthropic’s Claude API to write the summary. Long documents can take a minute.',
        'options' => [
            'length' => ['type' => 'choice', 'label' => 'Summary length', 'default' => 'standard', 'choices' => [
                'brief' => ['Brief', 'One short paragraph.'],
                'standard' => ['Key points', 'Overview plus the main points as bullets.'],
                'detailed' => ['Detailed', 'Section by section.'],
            ]],
            'language' => ['type' => 'select', 'label' => 'Summary language', 'default' => 'same', 'choices' => ['same' => 'Same as the document', ...array_combine(array_values($languages), array_values($languages))]],
            'focus' => ['type' => 'textarea', 'label' => 'Anything to focus on?', 'placeholder' => 'e.g. deadlines and costs', 'rows' => 3, 'max' => 500],
        ],
    ],

    'translate-pdf' => [
        'processor' => Ai\TranslatePdf::class,
        'requires' => 'ai',
        'accept' => ['pdf'],
        'noun' => ['PDF', 'PDFs'],
        'suffix' => '_translated',
        'action' => 'Translate PDF',
        'working' => 'Translating…',
        'done' => 'Your PDF has been translated.',
        'note' => 'The text is sent to Anthropic’s Claude API and written back in place of the original. Images stay as they are; scanned pages need OCR PDF first.',
        'options' => [
            'language' => ['type' => 'select', 'label' => 'Translate into', 'default' => 'en', 'choices' => $languages],
            'tone' => ['type' => 'choice', 'label' => 'Tone', 'default' => 'neutral', 'choices' => [
                'neutral' => ['Neutral', 'Faithful to the original wording.'],
                'formal' => ['Formal', 'For contracts, letters and official documents.'],
                'plain' => ['Plain language', 'Simpler words, same meaning.'],
            ]],
        ],
    ],
];
