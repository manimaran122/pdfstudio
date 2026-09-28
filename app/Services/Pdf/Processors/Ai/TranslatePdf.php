<?php

namespace App\Services\Pdf\Processors\Ai;

use App\Services\Ai\TextModel;
use App\Services\Pdf\PdfToolException;
use App\Services\Pdf\Processors\Processor;

/**
 * Translates a PDF's text with Claude and writes it back in place.
 *
 * Text blocks are extracted with their positions, translated in batches as
 * a JSON array (one string per block, same order), then each block's
 * original text is removed and the translation fitted into its box. Images
 * and drawings stay as they are.
 */
class TranslatePdf extends Processor
{
    /** Characters of source text per request. */
    private const BATCH_CHARS = 12000;

    public function __construct(private TextModel $model) {}

    public function process(array $inputs, string $output, array $options): ?array
    {
        $language = $options['language'];
        $blocks = $this->python('translate_extract', ['input' => $inputs[0]])['blocks'];
        $translations = [];

        foreach ($this->batches($blocks) as $batch) {
            array_push($translations, ...$this->translate(array_column($batch, 'text'), $language, $options['tone']));
        }

        $this->python('translate_apply', [
            'input' => $inputs[0],
            'output' => $output,
            'blocks' => $blocks,
            'translations' => $translations,
            'language' => $language,
        ]);

        $this->assertOutput($output);

        return ['meta' => ['summary' => count($blocks).' text blocks translated into '.(TranslateLanguages::NAMES[$language] ?? $language).'.']];
    }

    /**
     * @return array<int, array<int, array>>
     */
    private function batches(array $blocks): array
    {
        $batches = [[]];
        $size = 0;

        foreach ($blocks as $block) {
            if ($size > 0 && $size + mb_strlen($block['text']) > self::BATCH_CHARS) {
                $batches[] = [];
                $size = 0;
            }

            $batches[array_key_last($batches)][] = $block;
            $size += mb_strlen($block['text']);
        }

        return $batches;
    }

    /**
     * @param  array<int, string>  $texts
     * @return array<int, string>
     */
    private function translate(array $texts, string $language, string $tone): array
    {
        $name = TranslateLanguages::NAMES[$language] ?? $language;
        $reply = $this->model->complete(
            system: 'You are a professional document translator. You receive a JSON array of text blocks from one document, in reading order. '
                .'Translate every block and reply with ONLY a JSON array of strings: exactly one translation per input block, in the same order. '
                .'Keep numbers, names, codes, URLs and email addresses unchanged. Never merge, split, skip or add blocks.',
            content: [['type' => 'text', 'text' => "Translate into {$name}, in a {$tone} register.\n\n".json_encode($texts, JSON_UNESCAPED_UNICODE)]],
        );

        $json = preg_replace('/^```(?:json)?\s*|\s*```$/', '', trim($reply));
        $translated = json_decode($json, true);

        if (! is_array($translated) || ! array_is_list($translated) || count($translated) !== count($texts)) {
            throw PdfToolException::forUser('The translation came back incomplete. Please try again.');
        }

        return array_map(fn ($text) => is_string($text) ? $text : (string) json_encode($text), $translated);
    }
}
