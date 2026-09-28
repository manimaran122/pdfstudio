<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class PdfFactory
{
    /**
     * Build a Letter-size PDF with one page per string, drawn as real
     * (extractable) text with PyMuPDF. Lines split on "\n"; a line starting
     * with "# " is drawn large, like a heading.
     *
     * @param  array<int, string>  $pages
     */
    public static function withText(array $pages): string
    {
        $script = <<<'PY'
import fitz, json, sys
doc = fitz.open()
for text in json.loads(sys.argv[1]):
    page = doc.new_page(width=612, height=792)
    y = 72
    for line in text.split("\n"):
        size = 22 if line.startswith("# ") else 12
        page.insert_text((72, y), line[2:] if size == 22 else line, fontsize=size)
        y += size * 1.6
sys.stdout.buffer.write(doc.tobytes())
PY;

        $result = Process::run([config('pdf.binaries.python', 'python3'), '-c', $script, json_encode(array_values($pages))]);

        if ($result->failed()) {
            throw new RuntimeException('PdfFactory::withText needs python3-fitz: '.$result->errorOutput());
        }

        return $result->output();
    }

    /**
     * Build a minimal, valid PDF with the given number of blank pages.
     */
    public static function make(int $pages = 1): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '',
        ];

        $kids = [];

        for ($i = 0; $i < $pages; $i++) {
            $id = 3 + $i;
            $kids[] = "{$id} 0 R";
            $objects[$id] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] >>';
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids)."] /Count {$pages} >>";

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $count = count($objects) + 1;
        $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
