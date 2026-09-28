<?php

namespace App\Services\Pdf;

use RuntimeException;

class PdfToolException extends RuntimeException
{
    /**
     * A message that is safe to show the user as-is (e.g. "Wrong password"),
     * as opposed to raw tool output, which is only logged.
     */
    public ?string $userMessage = null;

    public static function forUser(string $message): self
    {
        $exception = new self($message);
        $exception->userMessage = $message;

        return $exception;
    }
}
