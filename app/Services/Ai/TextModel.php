<?php

namespace App\Services\Ai;

use App\Services\Pdf\PdfToolException;

/**
 * A language model that turns a prompt into text. Bound to Claude in
 * AppServiceProvider; tests bind a fake so they never call the API.
 */
interface TextModel
{
    /**
     * @param  array<int, array<string, mixed>>  $content  Messages API user content blocks.
     *
     * @throws PdfToolException
     */
    public function complete(string $system, array $content, int $maxTokens = 16000): string;
}
