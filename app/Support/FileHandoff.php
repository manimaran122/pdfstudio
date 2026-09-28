<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Files dropped on the home page, waiting for the user to pick a tool.
 *
 * The home drop stores uploads in a workspace owned by this session, then
 * redirects to the chosen tool with ?from=<token>; the tool takes the files
 * over with take(). Tokens are single use.
 */
class FileHandoff
{
    private const KEY = 'pdf.handoff';

    /** Merge has its own component, so it isn't in PdfTools. */
    private const MERGE = ['accept' => ['pdf'], 'multiple' => true, 'fileLabels' => null, 'readCheck' => true];

    /**
     * @param  array<int, array{id: string, name: string, ext: string, pages: ?int, bytes: int}>  $files
     */
    public static function put(string $workspace, array $files): string
    {
        $token = Str::random(24);
        session()->put(self::KEY.'.'.$token, ['workspace' => $workspace, 'files' => $files]);

        return $token;
    }

    /**
     * @return array{workspace: string, files: array}|null
     */
    public static function take(?string $token, string $tool): ?array
    {
        if (! $token || ! preg_match('/^[A-Za-z0-9]{24}$/', $token)) {
            return null;
        }

        $handoff = session()->pull(self::KEY.'.'.$token);

        if (! $handoff || ! PdfWorkspace::ownedBySession($handoff['workspace']) || ! self::fits($tool, $handoff['files'])) {
            return null;
        }

        return $handoff;
    }

    /**
     * Slugs of the tools that can take these files, most used first.
     *
     * @return array<int, string>
     */
    public static function toolsFor(array $files): array
    {
        $slugs = ['merge-pdf', ...array_keys(PdfTools::all())];
        $fitting = array_values(array_filter($slugs, fn ($slug) => self::fits($slug, $files) && ($slug === 'merge-pdf' || PdfTools::available($slug))));
        $popular = array_values(array_intersect(ToolCatalog::POPULAR, $fitting));

        return [...$popular, ...array_values(array_diff($fitting, $popular))];
    }

    public static function fits(string $tool, array $files): bool
    {
        $definition = $tool === 'merge-pdf' ? self::MERGE : (PdfTools::has($tool) ? PdfTools::get($tool) : null);
        $count = count($files);

        if (! $definition || $count === 0) {
            return false;
        }

        $countOk = match (true) {
            $definition['fileLabels'] !== null => $count === count($definition['fileLabels']),
            $tool === 'merge-pdf' => $count >= 2,
            $definition['multiple'] => $count <= config('pdf.max_files'),
            default => $count === 1,
        };

        foreach ($files as $file) {
            $unreadable = $file['ext'] === 'pdf' && $file['pages'] === null;

            if (! in_array($file['ext'], $definition['accept'], true) || ($unreadable && $definition['readCheck'])) {
                return false;
            }
        }

        return $countOk;
    }
}
