<?php

namespace App\Support;

use Illuminate\Support\Str;

class ToolCatalog
{
    /**
     * 24px stroke icon paths, keyed by icon name.
     */
    public const ICONS = [
        'merge' => 'M6 3v4a5 5 0 0 0 5 5h1M18 3v4a5 5 0 0 1-5 5M12 12v9M9 18l3 3 3-3',
        'split' => 'M12 3v6M12 9l-6 6v6M12 9l6 6v6M9 6l3-3 3 3',
        'remove' => 'M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14M10 11v6M14 11v6',
        'extract' => 'M13 3H6v18h12v-7M16 3h5v5M21 3l-9 9',
        'organize' => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
        'scan' => 'M4 8V4h4M16 4h4v4M20 16v4h-4M8 20H4v-4M4 12h16',
        'compress' => 'M9 3v6H3M15 3v6h6M9 21v-6H3M15 21v-6h6',
        'repair' => 'M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z',
        'ocr' => 'M4 8V4h4M16 4h4v4M20 16v4h-4M8 20H4v-4M8 8h8M12 8v8',
        'toPdf' => 'M14 3H6v18h12V7zM14 3v4h4M12 10v7M9 14l3 3 3-3',
        'fromPdf' => 'M14 3H6v18h12V7zM14 3v4h4M12 17v-7M9 13l3-3 3 3',
        'rotate' => 'M20 12a8 8 0 1 1-2.3-5.7M20 4v5h-5',
        'numbers' => 'M4 9h16M4 15h16M10 3L8 21M16 3l-2 18',
        'watermark' => 'M12 3c3 4 6 7.5 6 11a6 6 0 0 1-12 0c0-3.5 3-7 6-11z',
        'crop' => 'M6 2v16h16M2 6h16v16',
        'edit' => 'M4 20h4L19 9l-4-4L4 16v4zM13 7l4 4',
        'forms' => 'M4 4h16v16H4zM8 9h8M8 13h8M8 17h4',
        'unlock' => 'M5 11h14v10H5zM8 11V7a4 4 0 0 1 7.5-2',
        'protect' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6zM9 12l2 2 4-4',
        'sign' => 'M3 17c3 0 4-8 6-8s1 8 3 8 2-4 4-4 2 2 5 2M3 21h18',
        'redact' => 'M4 6h16M4 12h6M14 12h6M4 18h16',
        'compare' => 'M4 4h7v16H4zM13 4h7v16h-7zM6.5 9h2M15.5 9h2M6.5 13h2M15.5 13h2',
        'summarize' => 'M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8zM19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8z',
        'translate' => 'M4 5h8M8 3v2M6 5c0 4 3 7 6 8M10 5c0 4-3 7-6 8M13 21l4-10 4 10M14.5 17.5h5',
        'markdown' => 'M3 6h18v12H3zM6 15V9l3 3 3-3v6M16 9v6M14 13l2 2 2-2',
    ];

    /** "Most used" on the home page, and the order the home drop suggests tools in. */
    public const POPULAR = ['merge-pdf', 'split-pdf', 'compress-pdf', 'pdf-to-word', 'word-to-pdf', 'edit-pdf', 'sign-pdf', 'protect-pdf'];

    private const CATEGORIES = [
        ['id' => 'organize', 'name' => 'Organize PDF', 'blurb' => 'Combine, split and rearrange pages', 'color' => '#B4410C', 'tint' => '#FCE9DC', 'tools' => [
            ['Merge PDF', 'Combine several PDFs into one, in the order you choose.', 'merge'],
            ['Split PDF', 'Break a PDF into separate files by page range.', 'split'],
            ['Remove pages', 'Delete the pages you don’t need.', 'remove'],
            ['Extract pages', 'Pull selected pages out into a new PDF.', 'extract'],
            ['Organize PDF', 'Reorder, duplicate or delete pages visually.', 'organize'],
            ['Scan to PDF', 'Turn camera or scanner images into a PDF.', 'scan'],
        ]],
        ['id' => 'optimize', 'name' => 'Optimize PDF', 'blurb' => 'Make files smaller, fixed and searchable', 'color' => '#15803D', 'tint' => '#DDF3E4', 'tools' => [
            ['Compress PDF', 'Reduce file size while keeping quality readable.', 'compress'],
            ['Repair PDF', 'Recover data from a damaged or corrupt PDF.', 'repair'],
            ['OCR PDF', 'Make scanned pages searchable and selectable.', 'ocr'],
        ]],
        ['id' => 'to', 'name' => 'Convert to PDF', 'blurb' => 'Turn other formats into PDF', 'color' => '#A16207', 'tint' => '#FBF1CF', 'tools' => [
            ['JPG to PDF', 'Convert images into a PDF, one per page.', 'toPdf', 'JPG'],
            ['Word to PDF', 'Convert DOC and DOCX documents to PDF.', 'toPdf', 'DOC'],
            ['PowerPoint to PDF', 'Convert slides from PPT and PPTX to PDF.', 'toPdf', 'PPT'],
            ['Excel to PDF', 'Convert spreadsheets from XLS and XLSX to PDF.', 'toPdf', 'XLS'],
            ['HTML to PDF', 'Save a web page or HTML file as a PDF.', 'toPdf', 'HTML'],
        ]],
        ['id' => 'from', 'name' => 'Convert from PDF', 'blurb' => 'Get editable files back out of a PDF', 'color' => '#1D4ED8', 'tint' => '#DFE7FB', 'tools' => [
            ['PDF to JPG', 'Export each page as an image, or pull out images.', 'fromPdf', 'JPG'],
            ['PDF to Word', 'Turn a PDF into an editable DOCX file.', 'fromPdf', 'DOC'],
            ['PDF to PowerPoint', 'Turn PDF pages into editable slides.', 'fromPdf', 'PPT'],
            ['PDF to Excel', 'Extract tables into a spreadsheet.', 'fromPdf', 'XLS'],
            ['PDF to PDF/A', 'Convert to the archival PDF/A standard.', 'fromPdf', 'PDF/A'],
        ]],
        ['id' => 'edit', 'name' => 'Edit PDF', 'blurb' => 'Change what’s on the page', 'color' => '#9D174D', 'tint' => '#F8E1EC', 'tools' => [
            ['Rotate PDF', 'Rotate one page or the whole document.', 'rotate'],
            ['Add page numbers', 'Number pages with your choice of position and style.', 'numbers'],
            ['Add watermark', 'Stamp text or an image over your pages.', 'watermark'],
            ['Crop PDF', 'Trim margins or select an area to keep.', 'crop'],
            ['Edit PDF', 'Add text, shapes, images and annotations.', 'edit'],
            ['PDF Forms', 'Create fillable form fields or fill existing ones.', 'forms'],
        ]],
        ['id' => 'security', 'name' => 'PDF Security', 'blurb' => 'Protect, sign and review documents', 'color' => '#0F766E', 'tint' => '#D9F2EF', 'tools' => [
            ['Unlock PDF', 'Remove a password you already know.', 'unlock'],
            ['Protect PDF', 'Encrypt a PDF with a password.', 'protect'],
            ['Sign PDF', 'Draw, type or upload your signature and place it.', 'sign'],
            ['Redact PDF', 'Permanently black out sensitive text.', 'redact'],
            ['Compare PDF', 'Highlight differences between two versions.', 'compare'],
        ]],
        ['id' => 'ai', 'name' => 'PDF Intelligence', 'blurb' => 'AI tools for reading and reuse', 'color' => '#6D28D9', 'tint' => '#ECE4FB', 'tools' => [
            ['AI Summarizer', 'Get a short summary of a long document.', 'summarize'],
            ['Translate PDF', 'Translate a document while keeping its layout.', 'translate'],
            ['PDF to Markdown', 'Convert content to clean Markdown text.', 'markdown', 'MD'],
        ]],
    ];

    /**
     * @return array<int, array{id: string, name: string, blurb: string, color: string, tint: string, tools: array<int, array{name: string, desc: string, icon: string, badge: string, url: ?string}>}>
     */
    public static function categories(): array
    {
        return array_map(fn (array $category) => [
            ...$category,
            'tools' => array_map(fn (array $tool) => [
                'slug' => $slug = Str::slug($tool[0]),
                'name' => $tool[0],
                'desc' => $tool[1],
                'icon' => self::ICONS[$tool[2]],
                'badge' => $tool[3] ?? '',
                'url' => self::url($slug),
            ], $category['tools']),
        ], self::CATEGORIES);
    }

    /**
     * The tool with this slug and the category it belongs to.
     *
     * @return array{0: array, 1: array}|null
     */
    public static function find(string $slug): ?array
    {
        foreach (self::categories() as $category) {
            foreach ($category['tools'] as $tool) {
                if ($tool['slug'] === $slug) {
                    return [$tool, $category];
                }
            }
        }

        return null;
    }

    /**
     * URL of a tool that has a working implementation, otherwise null.
     */
    private static function url(string $slug): ?string
    {
        return match (true) {
            $slug === 'merge-pdf' => route('tools.merge'),
            PdfTools::has($slug) => route('tools.show', $slug),
            default => null,
        };
    }

    public static function category(string $id): array
    {
        return collect(self::categories())->firstWhere('id', $id);
    }

    /**
     * Every tool with its category, for menus and the search palette.
     *
     * @return array<int, array{slug: string, name: string, desc: string, badge: string, icon: string, url: ?string, category: string, color: string, tint: string}>
     */
    public static function flat(): array
    {
        $tools = [];

        foreach (self::categories() as $category) {
            foreach ($category['tools'] as $tool) {
                $tools[] = [...$tool, 'category' => $category['name'], 'color' => $category['color'], 'tint' => $category['tint']];
            }
        }

        return $tools;
    }

    /**
     * @return array<int, array>
     */
    public static function popular(): array
    {
        $tools = collect(self::flat())->keyBy('slug');

        return array_map(fn ($slug) => $tools[$slug], self::POPULAR);
    }

    public static function toolCount(): int
    {
        return array_sum(array_map(fn (array $category) => count($category['tools']), self::CATEGORIES));
    }
}
