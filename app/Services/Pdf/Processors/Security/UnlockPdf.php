<?php

namespace App\Services\Pdf\Processors\Security;

use App\Services\Pdf\PdfToolException;
use App\Services\Pdf\Processors\Processor;
use Illuminate\Support\Facades\Process;

class UnlockPdf extends Processor
{
    public function process(array $inputs, string $output, array $options): ?array
    {
        $input = $inputs[0];
        $password = (string) ($options['password'] ?? '');

        // The password goes through a 0600 temp file so it never shows up in
        // the process list.
        $passwordFile = tempnam(sys_get_temp_dir(), 'pdfpw');
        file_put_contents($passwordFile, $password);

        try {
            // Exit 0: a password is needed (and the one given doesn't open it),
            // 2: not encrypted or unreadable, 3: encrypted, opens as given.
            $check = Process::timeout(config('pdf.timeout'))->run([
                $this->binary('qpdf'), '--password-file='.$passwordFile, '--requires-password', $input,
            ]);

            match ($check->exitCode()) {
                0 => throw PdfToolException::forUser($password === ''
                    ? 'Enter the password to unlock this PDF.'
                    : 'That password isn’t correct.'),
                2 => throw trim($check->errorOutput()) === ''
                    ? PdfToolException::forUser('This PDF isn’t password protected.')
                    : PdfToolException::forUser('This file couldn’t be opened as a PDF.'),
                3 => null,
                default => throw new PdfToolException(trim($check->errorOutput()) ?: 'qpdf failed.'),
            };

            // Exit 3 is "succeeded with warnings".
            $this->run([$this->binary('qpdf'), '--password-file='.$passwordFile, '--decrypt', $input, $output], [0, 3]);
        } finally {
            @unlink($passwordFile);
        }

        $this->assertOutput($output);

        return null;
    }
}
