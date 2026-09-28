<?php

namespace App\Support;

use Carbon\CarbonInterval;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PdfWorkspace
{
    public static function disk(): Filesystem
    {
        return Storage::disk(config('pdf.disk'));
    }

    /**
     * Start a new workspace owned by the current session.
     */
    public static function create(): string
    {
        $workspace = (string) Str::uuid();
        session()->push('pdf.workspaces', $workspace);

        return $workspace;
    }

    public static function ownedBySession(string $workspace): bool
    {
        return in_array($workspace, session('pdf.workspaces', []), true);
    }

    public static function path(string $workspace, string $file = ''): string
    {
        return trim(config('pdf.directory').'/'.$workspace.'/'.$file, '/');
    }

    /**
     * Human-readable retention period, e.g. "1 hour".
     */
    public static function retention(): string
    {
        return CarbonInterval::minutes(config('pdf.retention_minutes'))->cascade()->forHumans();
    }

    public static function formatBytes(int $bytes): string
    {
        return number_format($bytes / 1048576, 1).' MB';
    }
}
