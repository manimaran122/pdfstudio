<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Results created in this browser session, newest first. Entries whose file
 * has been pruned (see pdf:prune) drop out of the list automatically.
 */
class RecentFiles
{
    private const KEY = 'pdf.recent';

    private const LIMIT = 50;

    public static function add(string $tool, string $path, string $name, int $bytes): void
    {
        $entries = session(self::KEY, []);

        array_unshift($entries, [
            'id' => Str::random(16),
            'tool' => $tool,
            'path' => $path,
            'name' => $name,
            'bytes' => $bytes,
            'at' => now()->getTimestamp(),
        ]);

        session([self::KEY => array_slice($entries, 0, self::LIMIT), self::KEY.'-unseen' => true]);
    }

    /**
     * @return array<int, array{id: string, tool: string, path: string, name: string, bytes: int, at: int}>
     */
    public static function all(?int $limit = null): array
    {
        $disk = PdfWorkspace::disk();
        $entries = array_values(array_filter(session(self::KEY, []), fn ($entry) => $disk->exists($entry['path'])));

        return $limit ? array_slice($entries, 0, $limit) : $entries;
    }

    public static function find(string $id): ?array
    {
        return collect(self::all())->firstWhere('id', $id);
    }

    /**
     * Whether files were added since the Recent files page was last opened.
     */
    public static function unseen(): bool
    {
        return (bool) session(self::KEY.'-unseen', false);
    }

    public static function markSeen(): void
    {
        session()->forget(self::KEY.'-unseen');
    }
}
