<?php

namespace App\Services\Pdf\Processors\Security;

use App\Services\Pdf\PdfToolException;
use App\Services\Pdf\Processors\Processor;
use Illuminate\Support\Str;

class ProtectPdf extends Processor
{
    public const MIN_LENGTH = 6;

    public function process(array $inputs, string $output, array $options): ?array
    {
        $password = (string) ($options['password'] ?? '');

        if (mb_strlen($password) < self::MIN_LENGTH) {
            throw PdfToolException::forUser('Use a password of at least '.self::MIN_LENGTH.' characters.');
        }

        if ($password !== (string) ($options['confirm'] ?? '')) {
            throw PdfToolException::forUser('The passwords don’t match.');
        }

        if (preg_match('/[\r\n]/', $password)) {
            throw PdfToolException::forUser('Passwords can’t contain line breaks.');
        }

        // A random owner password keeps the restrictions in force: anyone
        // who can open the file still can't lift them.
        $arguments = [
            '--encrypt',
            '--user-password='.$password,
            '--owner-password='.Str::random(32),
            '--bits=256',
            '--print='.(($options['print'] ?? true) ? 'full' : 'none'),
            '--extract='.(($options['copy'] ?? false) ? 'y' : 'n'),
            '--modify='.(($options['edit'] ?? false) ? 'all' : 'none'),
            '--',
            $inputs[0],
            $output,
        ];

        // qpdf reads "@file" as one argument per line, which keeps the
        // passwords out of the process list.
        $argFile = tempnam(sys_get_temp_dir(), 'pdfargs');
        file_put_contents($argFile, implode("\n", $arguments)."\n");

        try {
            $this->run([$this->binary('qpdf'), '@'.$argFile], [0, 3]);
        } finally {
            @unlink($argFile);
        }

        $this->assertOutput($output);

        return null;
    }
}
