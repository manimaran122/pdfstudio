<?php

namespace App\Console\Commands;

use App\Support\PdfWorkspace;
use Illuminate\Console\Command;

class PrunePdfWorkspaces extends Command
{
    protected $signature = 'pdf:prune';

    protected $description = 'Delete uploaded and merged PDFs older than the retention period';

    public function handle(): int
    {
        $disk = PdfWorkspace::disk();
        $cutoff = now()->subMinutes(config('pdf.retention_minutes'))->getTimestamp();
        $pruned = 0;

        foreach ($disk->directories(config('pdf.directory')) as $directory) {
            $files = $disk->allFiles($directory);
            $newest = collect($files)->map(fn ($file) => $disk->lastModified($file))->max() ?? 0;

            if ($newest < $cutoff) {
                $disk->deleteDirectory($directory);
                $pruned++;
            }
        }

        $this->info("Pruned {$pruned} workspace(s).");

        return self::SUCCESS;
    }
}
