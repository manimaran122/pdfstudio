<?php

namespace App\Services\Pdf\Processors;

use App\Services\Pdf\PdfToolException;
use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;

abstract class Processor
{
    /**
     * Produce the tool's output file.
     *
     * $output is the path for the tool's default output type (see the
     * definition's "output" key). A processor that decides the type at run
     * time (e.g. one page → JPG, many → ZIP) writes elsewhere and returns
     * that path as "file".
     *
     * Options also carry resolved context: "_assets" (asset id => absolute
     * path) and "_pages" (page count per input, null when unknown).
     *
     * @param  array<int, string>  $inputs  Absolute paths, in order.
     * @param  array<string, mixed>  $options  Validated tool options.
     * @return array{file?: string, meta?: array<string, mixed>, preview?: string}|null
     */
    abstract public function process(array $inputs, string $output, array $options): ?array;

    protected function binary(string $name): string
    {
        return config("pdf.binaries.{$name}");
    }

    /**
     * @param  array<int, string>  $command
     * @param  array<int, int>  $okExitCodes
     */
    protected function run(array $command, array $okExitCodes = [0], array $env = []): ProcessResult
    {
        $result = Process::timeout(config('pdf.timeout'))->env($env)->run($command);

        if (! in_array($result->exitCode(), $okExitCodes, true)) {
            throw new PdfToolException(trim($result->errorOutput() ?: $result->output()) ?: basename($command[0]).' failed.');
        }

        return $result;
    }

    /**
     * Run a command of resources/python/pdfops.py (PyMuPDF) and return its
     * JSON result. The script exits with code 2 and {"error": "..."} for
     * problems the user should see (e.g. "No tables were found").
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function python(string $command, array $payload): array
    {
        $args = tempnam(sys_get_temp_dir(), 'pdfops');
        file_put_contents($args, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        try {
            $result = Process::timeout(config('pdf.timeout'))->run([
                config('pdf.binaries.python'), resource_path('python/pdfops.py'), $command, $args,
            ]);
        } finally {
            @unlink($args);
        }

        $data = json_decode(trim($result->output()) ?: 'null', true);

        if ($result->exitCode() === 2 && isset($data['error'])) {
            throw PdfToolException::forUser($data['error']);
        }

        if ($result->failed() || ! is_array($data)) {
            throw new PdfToolException(trim($result->errorOutput()) ?: "pdfops {$command} failed.");
        }

        return $data;
    }

    protected function assertOutput(string $output): void
    {
        if (! is_file($output) || filesize($output) === 0) {
            throw new PdfToolException('No output was produced.');
        }
    }
}
