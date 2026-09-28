<?php

namespace App\Services\Ai;

use Anthropic\Beta\Messages\BetaRawContentBlockDeltaEvent;
use Anthropic\Beta\Messages\BetaRawMessageDeltaEvent;
use Anthropic\Beta\Messages\BetaTextDelta;
use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Services\Pdf\PdfToolException;

/**
 * Claude over the official Anthropic PHP SDK.
 *
 * Streams, because whole documents go in and long answers can come out,
 * and non-streaming requests that long risk HTTP timeouts. Uses the
 * server-side "default" fallback so a request the model declines on
 * policy grounds is retried on Anthropic's recommended fallback model
 * inside the same call instead of failing outright.
 */
class Claude implements TextModel
{
    private const FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    public function __construct(private Client $client, private string $model) {}

    public function complete(string $system, array $content, int $maxTokens = 16000): string
    {
        $text = '';
        $stopReason = null;

        try {
            $stream = $this->client->beta->messages->createStream(
                model: $this->model,
                maxTokens: $maxTokens,
                system: $system,
                messages: [['role' => 'user', 'content' => $content]],
                thinking: ['type' => 'adaptive'],
                fallbacks: 'default',
                betas: [self::FALLBACK_BETA],
            );

            foreach ($stream as $event) {
                if ($event instanceof BetaRawContentBlockDeltaEvent && $event->delta instanceof BetaTextDelta) {
                    $text .= $event->delta->text;
                } elseif ($event instanceof BetaRawMessageDeltaEvent && $event->delta->stopReason !== null) {
                    $stopReason = $event->delta->stopReason;
                }
            }
        } catch (AuthenticationException $e) {
            throw $this->fail($e, 'The Anthropic API key isn’t valid. Ask an administrator to check ANTHROPIC_API_KEY.');
        } catch (RateLimitException $e) {
            throw $this->fail($e, 'The AI service is busy right now. Please try again in a minute.');
        } catch (BadRequestException $e) {
            throw $this->fail($e, 'This document couldn’t be sent to the AI service. It may be too large or have too many pages.');
        } catch (APIStatusException|APIConnectionException $e) {
            throw $this->fail($e, 'The AI service couldn’t be reached. Please try again.');
        }

        return match ($stopReason) {
            'refusal' => throw PdfToolException::forUser('The AI declined to process this document.'),
            'max_tokens' => throw PdfToolException::forUser('The document is too long to finish in one go. Try a shorter PDF.'),
            default => trim($text),
        };
    }

    private function fail(\Throwable $e, string $message): PdfToolException
    {
        $exception = PdfToolException::forUser($message);
        report($e);

        return $exception;
    }
}
