<?php

namespace App\Livewire;

use App\Services\Pdf\Pdftk;
use App\Services\Pdf\PdfToolException;
use App\Support\FileHandoff;
use App\Support\PdfWorkspace;
use App\Support\RecentFiles;
use App\Support\ToolCatalog;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MergePdf extends Component
{
    use WithFileUploads;

    /**
     * Storage folder for this merge session; locked so the client cannot
     * point it at someone else's files.
     */
    #[Locked]
    public string $workspace = '';

    /**
     * Files in merge order.
     *
     * @var array<int, array{id: string, name: string, pages: int, bytes: int, rotation: int}>
     */
    #[Locked]
    public array $files = [];

    /**
     * @var array{name: string, path: string, pages: int, bytes: int, count: int}|null
     */
    #[Locked]
    public ?array $result = null;

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    public string $outputName = 'merged.pdf';

    public string $order = 'arranged';

    public bool $bookmarks = true;

    public function mount(): void
    {
        $this->workspace = PdfWorkspace::create();

        if ($handoff = FileHandoff::take(request()->query('from'), 'merge-pdf')) {
            $this->workspace = $handoff['workspace'];
            $this->files = array_map(
                fn ($file) => ['id' => $file['id'], 'name' => $file['name'], 'pages' => $file['pages'], 'bytes' => $file['bytes'], 'rotation' => 0],
                array_slice($handoff['files'], 0, config('pdf.max_files')),
            );
        }
    }

    public function updatedUploads(Pdftk $pdftk): void
    {
        $maxFiles = config('pdf.max_files');
        $maxMb = config('pdf.max_file_mb');

        try {
            $this->validate([
                'uploads' => ['array', 'max:'.max(0, $maxFiles - count($this->files))],
                'uploads.*' => ['file', 'mimes:pdf', 'max:'.($maxMb * 1024)],
            ], [
                'uploads.max' => "You can merge up to {$maxFiles} files at a time.",
                'uploads.*.mimes' => 'Only PDF files can be merged.',
                'uploads.*.max' => "Each file must be {$maxMb} MB or smaller.",
            ]);
        } catch (ValidationException $e) {
            $this->reset('uploads');

            throw $e;
        }

        $disk = PdfWorkspace::disk();

        foreach ($this->uploads as $upload) {
            $id = Str::random(12);
            $path = $upload->storeAs(PdfWorkspace::path($this->workspace), "{$id}.pdf", config('pdf.disk'));

            try {
                $pages = $pdftk->pageCount($disk->path($path));
            } catch (PdfToolException) {
                $disk->delete($path);
                $this->addError('uploads', "“{$upload->getClientOriginalName()}” couldn’t be read. It may be damaged or password protected.");

                continue;
            }

            $this->files[] = [
                'id' => $id,
                'name' => $upload->getClientOriginalName(),
                'pages' => $pages,
                'bytes' => $upload->getSize(),
                'rotation' => 0,
            ];
        }

        $this->reset('uploads');
    }

    public function move(string $id, string $targetId): void
    {
        $from = $this->indexOf($id);
        $to = $this->indexOf($targetId);

        if ($from === null || $to === null || $from === $to) {
            return;
        }

        $file = array_splice($this->files, $from, 1);
        array_splice($this->files, $to, 0, $file);
    }

    public function nudge(string $id, int $offset): void
    {
        $from = $this->indexOf($id);
        $target = $this->files[$from + $offset]['id'] ?? null;

        if ($target !== null) {
            $this->move($id, $target);
        }
    }

    public function rotate(string $id): void
    {
        $index = $this->indexOf($id);

        if ($index !== null) {
            $this->files[$index]['rotation'] = ($this->files[$index]['rotation'] + 90) % 360;
        }
    }

    public function remove(string $id): void
    {
        $index = $this->indexOf($id);

        if ($index === null) {
            return;
        }

        PdfWorkspace::disk()->delete(PdfWorkspace::path($this->workspace, "{$id}.pdf"));
        array_splice($this->files, $index, 1);
    }

    public function sortByName(): void
    {
        usort($this->files, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
    }

    public function merge(Pdftk $pdftk): void
    {
        $this->validate([
            'outputName' => ['required', 'string', 'max:120'],
            'order' => ['required', 'in:arranged,alphabetical'],
            'bookmarks' => ['boolean'],
        ]);

        if (count($this->files) < 2) {
            $this->addError('merge', 'Add at least two files to merge.');

            return;
        }

        $disk = PdfWorkspace::disk();
        $files = $this->files;

        if ($this->order === 'alphabetical') {
            usort($files, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
        }

        $parts = [];
        $bookmarks = [];
        $page = 1;

        foreach ($files as $file) {
            $parts[] = [
                'path' => $disk->path(PdfWorkspace::path($this->workspace, "{$file['id']}.pdf")),
                'rotation' => $file['rotation'],
            ];
            $bookmarks[] = ['title' => $file['name'], 'page' => $page];
            $page += $file['pages'];
        }

        // A fresh name per run, so earlier results stay downloadable from Recent files.
        $output = PdfWorkspace::path($this->workspace, 'output-'.Str::random(8).'.pdf');

        try {
            $pdftk->merge($parts, $disk->path($output), $this->bookmarks ? $bookmarks : []);
        } catch (PdfToolException $e) {
            report($e);
            $this->addError('merge', 'Something went wrong while merging. Please try again.');

            return;
        }

        $this->result = [
            'name' => $this->safeOutputName(),
            'path' => $output,
            'pages' => $page - 1,
            'bytes' => $disk->size($output),
            'count' => count($files),
        ];

        RecentFiles::add('merge-pdf', $output, $this->result['name'], $this->result['bytes']);
    }

    public function download(): ?StreamedResponse
    {
        $output = $this->result['path'] ?? null;

        if (! $output || ! PdfWorkspace::disk()->exists($output)) {
            $this->result = null;
            $this->addError('merge', 'This file has expired. Please merge again.');

            return null;
        }

        return PdfWorkspace::disk()->download($output, $this->result['name']);
    }

    public function startOver(): void
    {
        // The old workspace is left for pdf:prune so its result stays in Recent files.
        $this->reset('files', 'result', 'uploads', 'outputName', 'order', 'bookmarks');
        $this->resetErrorBag();
        $this->workspace = PdfWorkspace::create();
    }

    public function render()
    {
        $pages = array_sum(array_column($this->files, 'pages'));
        $bytes = array_sum(array_column($this->files, 'bytes'));
        $count = count($this->files);

        return view('livewire.merge-pdf', [
            'category' => ToolCatalog::category('organize'),
            'summary' => $count.' '.Str::plural('file', $count).' · '.$pages.' '.Str::plural('page', $pages).' · '.PdfWorkspace::formatBytes($bytes),
        ]);
    }

    private function indexOf(string $id): ?int
    {
        $index = array_search($id, array_column($this->files, 'id'), true);

        return $index === false ? null : $index;
    }

    private function safeOutputName(): string
    {
        $name = preg_replace('/[^\w\-. ()]+/u', '_', basename(trim($this->outputName)));
        $name = trim((string) preg_replace('/\.pdf$/i', '', $name), ' ._');

        return ($name === '' ? 'merged' : $name).'.pdf';
    }
}
