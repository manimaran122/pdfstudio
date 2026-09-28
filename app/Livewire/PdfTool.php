<?php

namespace App\Livewire;

use App\Services\Pdf\Pdftk;
use App\Services\Pdf\PdfToolException;
use App\Support\FileHandoff;
use App\Support\PdfTools;
use App\Support\PdfWorkspace;
use App\Support\RecentFiles;
use App\Support\ToolCatalog;
use App\Support\ToolOptionRules;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Upload → options → download flow shared by every tool in PdfTools.
 */
class PdfTool extends Component
{
    use WithFileUploads;

    private const MAX_ASSET_BYTES = 5 * 1024 * 1024;

    #[Locked]
    public string $tool = '';

    /**
     * Storage folder for this session; locked so the client cannot point it
     * at someone else's files.
     */
    #[Locked]
    public string $workspace = '';

    /**
     * Input files in order.
     *
     * @var array<int, array{id: string, name: string, ext: string, pages: ?int, bytes: int}>
     */
    #[Locked]
    public array $files = [];

    /**
     * Images added while choosing options (signatures, watermark and editor
     * images), keyed by asset id.
     *
     * @var array<string, array{ext: string, width: int, height: int}>
     */
    #[Locked]
    public array $assets = [];

    /**
     * Form fields of the uploaded PDF, for tools with "fields".
     *
     * @var array<int, array{name: string, label: string, type: string, value: mixed, options: array, page: int}>
     */
    #[Locked]
    public array $fields = [];

    /**
     * @var array{name: string, path: string, ext: string, pages: ?int, bytes: int, inputBytes: int, meta: array, preview: ?string}|null
     */
    #[Locked]
    public ?array $result = null;

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    /** @var array<string, mixed> */
    public array $options = [];

    public function mount(string $tool): void
    {
        abort_unless(PdfTools::has($tool), 404);

        $this->tool = $tool;
        $this->workspace = PdfWorkspace::create();
        $this->options = PdfTools::defaults($tool);

        if ($handoff = FileHandoff::take(request()->query('from'), $tool)) {
            $this->workspace = $handoff['workspace'];
            $this->files = array_slice($handoff['files'], 0, $this->maxFiles());

            if ($this->definition()['fields'] && $this->files) {
                $this->loadFields();
            }
        }
    }

    public function updatedUploads(Pdftk $pdftk): void
    {
        $definition = $this->definition();
        $maxFiles = $this->maxFiles() - ($definition['multiple'] ? count($this->files) : 0);
        $maxMb = config('pdf.max_file_mb');
        $formats = $this->formats();

        try {
            $this->validate([
                'uploads' => ['array', 'max:'.max(0, $maxFiles)],
                'uploads.*' => ['file', 'extensions:'.implode(',', $definition['accept']), 'max:'.($maxMb * 1024)],
            ], [
                'uploads.max' => match (true) {
                    $this->maxFiles() === 1 => 'Add one file at a time.',
                    $definition['fileLabels'] !== null => 'This tool takes exactly '.$this->maxFiles().' files.',
                    default => 'You can add up to '.$this->maxFiles().' files at a time.',
                },
                'uploads.*.extensions' => "Only {$formats} files are supported here.",
                'uploads.*.max' => "Each file must be {$maxMb} MB or smaller.",
            ]);
        } catch (ValidationException $e) {
            $this->reset('uploads');

            throw $e;
        }

        if (! $definition['multiple']) {
            $this->clearFiles();
        }

        $disk = PdfWorkspace::disk();

        foreach ($this->uploads as $upload) {
            $id = Str::random(12);
            $ext = strtolower($upload->getClientOriginalExtension());
            $ext = $ext === 'jpeg' ? 'jpg' : $ext;
            $path = $upload->storeAs(PdfWorkspace::path($this->workspace), "{$id}.{$ext}", config('pdf.disk'));
            $pages = null;

            if ($ext === 'pdf') {
                try {
                    $pages = $pdftk->pageCount($disk->path($path));
                } catch (PdfToolException) {
                    if ($definition['readCheck']) {
                        $disk->delete($path);
                        $this->addError('uploads', "“{$upload->getClientOriginalName()}” couldn’t be read. It may be damaged or password protected.");

                        continue;
                    }
                }
            }

            $this->files[] = [
                'id' => $id,
                'name' => $upload->getClientOriginalName(),
                'ext' => $ext,
                'pages' => $pages,
                'bytes' => $upload->getSize(),
            ];
        }

        $this->reset('uploads');
        $this->resetPageOptions();

        if ($definition['fields'] && $this->files) {
            $this->loadFields();
        }
    }

    public function nudge(string $id, int $offset): void
    {
        $from = $this->indexOf($id);
        $to = $from === null ? null : $from + $offset;

        if ($to === null || ! isset($this->files[$to])) {
            return;
        }

        [$this->files[$from], $this->files[$to]] = [$this->files[$to], $this->files[$from]];
    }

    public function remove(string $id): void
    {
        $index = $this->indexOf($id);

        if ($index === null) {
            return;
        }

        $file = $this->files[$index];
        PdfWorkspace::disk()->delete(PdfWorkspace::path($this->workspace, "{$file['id']}.{$file['ext']}"));
        array_splice($this->files, $index, 1);
        $this->resetPageOptions();
        $this->fields = [];
    }

    /**
     * Store an image drawn or picked in the browser (signature pad, image
     * placement, watermark image) and return its asset id.
     */
    public function addAsset(string $dataUrl): ?string
    {
        if (! preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $match)
            || strlen($match[2]) > self::MAX_ASSET_BYTES * 4 / 3) {
            $this->addError('assets', 'Images must be PNG or JPG and 5 MB or smaller.');

            return null;
        }

        $bytes = base64_decode($match[2], true);
        $size = $bytes === false ? false : @getimagesizefromstring($bytes);

        if (! $size || ! in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            $this->addError('assets', 'That image couldn’t be read.');

            return null;
        }

        $id = Str::random(12);
        $ext = $size[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
        PdfWorkspace::disk()->put(PdfWorkspace::path($this->workspace, "assets/{$id}.{$ext}"), $bytes);
        $this->assets[$id] = ['ext' => $ext, 'width' => $size[0], 'height' => $size[1]];
        $this->resetErrorBag('assets');

        return $id;
    }

    public function process(): void
    {
        if (! PdfTools::available($this->tool)) {
            $this->addError('process', 'This tool isn’t set up yet.');

            return;
        }

        if (count($this->files) < $this->minFiles()) {
            $this->addError('process', $this->minFiles() > 1 ? 'Add '.$this->minFiles().' files first.' : 'Add a file first.');

            return;
        }

        $rules = new ToolOptionRules($this->definition(), $this->options, $this->pageCount(), $this->assets, $this->fields);

        // Livewire treats validate([]) as "no rules defined" and throws.
        if ($ruleset = $rules->rules()) {
            $this->validate($ruleset, $rules->messages(), $rules->attributes());
        }

        set_time_limit(config('pdf.timeout') + 30);

        $disk = PdfWorkspace::disk();
        $definition = $this->definition();
        $inputs = array_map(fn (array $file) => $disk->path($this->filePath($file)), $this->files);
        // A fresh name per run, so earlier results stay downloadable from Recent files.
        $output = $disk->path(PdfWorkspace::path($this->workspace, 'output-'.Str::random(8).'.'.$definition['output']));

        $options = [
            ...$this->options,
            '_assets' => collect($this->assets)->map(fn ($asset, $id) => $disk->path(PdfWorkspace::path($this->workspace, "assets/{$id}.{$asset['ext']}")))->all(),
            '_pages' => array_column($this->files, 'pages'),
            '_names' => array_column($this->files, 'name'),
            '_fields' => $this->fields,
        ];

        try {
            $outcome = PdfTools::processor($this->tool)->process($inputs, $output, $options) ?? [];
        } catch (PdfToolException $e) {
            report($e);
            $this->addError('process', $e->userMessage ?? 'We couldn’t process this file. Check that it opens correctly and try again.');

            return;
        }

        $file = $outcome['file'] ?? $output;

        if (! is_file($file)) {
            $this->addError('process', 'We couldn’t process this file. Please try again.');

            return;
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        $this->result = [
            'name' => $this->outputName($ext),
            'path' => PdfWorkspace::path($this->workspace, basename($file)),
            'ext' => $ext,
            'pages' => $ext === 'pdf' ? $this->countPages($file) : null,
            'bytes' => filesize($file),
            'inputBytes' => array_sum(array_column($this->files, 'bytes')),
            'meta' => $outcome['meta'] ?? [],
            'preview' => $outcome['preview'] ?? null,
        ];

        RecentFiles::add($this->tool, $this->result['path'], $this->result['name'], $this->result['bytes']);
    }

    public function download(): ?StreamedResponse
    {
        if (! $this->result || ! PdfWorkspace::disk()->exists($this->result['path'])) {
            $this->result = null;
            $this->addError('process', 'This file has expired. Please run the tool again.');

            return null;
        }

        return PdfWorkspace::disk()->download($this->result['path'], $this->result['name']);
    }

    public function startOver(): void
    {
        // The old workspace is left for pdf:prune so its result stays in Recent files.
        $this->reset('files', 'result', 'uploads', 'assets', 'fields');
        $this->resetErrorBag();
        $this->options = PdfTools::defaults($this->tool);
        $this->workspace = PdfWorkspace::create();
    }

    public function render()
    {
        [$tool, $category] = ToolCatalog::find($this->tool);

        return view('livewire.pdf-tool', [
            'definition' => $this->definition(),
            'info' => $tool,
            'category' => $category,
            'formats' => $this->formats(),
            'available' => PdfTools::available($this->tool),
            'pageCount' => $this->pageCount(),
            'maxFiles' => $this->maxFiles(),
        ]);
    }

    private function definition(): array
    {
        return PdfTools::get($this->tool);
    }

    private function maxFiles(): int
    {
        $definition = $this->definition();

        return match (true) {
            $definition['fileLabels'] !== null => count($definition['fileLabels']),
            $definition['multiple'] => config('pdf.max_files'),
            default => 1,
        };
    }

    private function minFiles(): int
    {
        return $this->definition()['fileLabels'] !== null ? $this->maxFiles() : 1;
    }

    /**
     * Pages of the first input, which page-based options refer to.
     */
    private function pageCount(): int
    {
        return (int) ($this->files[0]['pages'] ?? 0);
    }

    private function formats(): string
    {
        return implode(', ', array_map('strtoupper', array_diff($this->definition()['accept'], ['jpeg', 'htm'])));
    }

    private function outputName(string $ext): string
    {
        $definition = $this->definition();
        $base = $definition['outputName'] ?? pathinfo($this->files[0]['name'], PATHINFO_FILENAME);
        $base = trim((string) preg_replace('/[^\w\-. ()]+/u', '_', $base), ' ._') ?: 'document';

        return $base.$definition['suffix'].'.'.$ext;
    }

    private function countPages(string $path): ?int
    {
        try {
            return app(Pdftk::class)->pageCount($path);
        } catch (PdfToolException) {
            return null;
        }
    }

    /**
     * Page selections and placements refer to the first file's pages, so
     * they reset whenever that file changes.
     */
    private function resetPageOptions(): void
    {
        $defaults = PdfTools::defaults($this->tool);

        foreach ($this->definition()['options'] as $name => $option) {
            if (in_array($option['type'], ['pages', 'placements', 'form'], true)) {
                $this->options[$name] = $defaults[$name];
            }
        }
    }

    private function loadFields(): void
    {
        $payload = tempnam(sys_get_temp_dir(), 'pdfops');
        file_put_contents($payload, json_encode(['input' => PdfWorkspace::disk()->path($this->filePath($this->files[0]))]));

        try {
            $result = Process::timeout(60)->run([config('pdf.binaries.python'), resource_path('python/pdfops.py'), 'info', $payload]);
        } finally {
            @unlink($payload);
        }

        $this->fields = $result->successful() ? (json_decode($result->output(), true)['fields'] ?? []) : [];
        // Keyed by position, not name: field names often contain dots,
        // which wire:model would treat as nesting.
        $values = array_map(fn ($field) => $field['type'] === 'checkbox'
            ? ! in_array($field['value'], [null, '', false, 'Off'], true)
            : (string) $field['value'], $this->fields);

        foreach ($this->definition()['options'] as $name => $option) {
            if ($option['type'] === 'form') {
                $this->options[$name] = $values;
            }
        }
    }

    private function filePath(array $file): string
    {
        return PdfWorkspace::path($this->workspace, "{$file['id']}.{$file['ext']}");
    }

    private function clearFiles(): void
    {
        foreach ($this->files as $file) {
            PdfWorkspace::disk()->delete($this->filePath($file));
        }

        $this->files = [];
        $this->fields = [];
    }

    private function indexOf(string $id): ?int
    {
        $index = array_search($id, array_column($this->files, 'id'), true);

        return $index === false ? null : $index;
    }
}
