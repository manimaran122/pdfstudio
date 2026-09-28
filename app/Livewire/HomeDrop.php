<?php

namespace App\Livewire;

use App\Services\Pdf\Pdftk;
use App\Services\Pdf\PdfToolException;
use App\Support\FileHandoff;
use App\Support\PdfTools;
use App\Support\PdfWorkspace;
use App\Support\ToolCatalog;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The home page drop area: upload first, then choose what to do with the
 * files. The chosen tool takes them over through FileHandoff.
 */
class HomeDrop extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $workspace = '';

    /** @var array<int, array{id: string, name: string, ext: string, pages: ?int, bytes: int}> */
    #[Locked]
    public array $files = [];

    /** @var array<int, TemporaryUploadedFile> */
    public array $uploads = [];

    public function updatedUploads(Pdftk $pdftk): void
    {
        $maxMb = config('pdf.max_file_mb');

        try {
            $this->validate([
                'uploads' => ['array', 'max:'.config('pdf.max_files')],
                'uploads.*' => ['file', 'extensions:'.implode(',', self::extensions()), 'max:'.($maxMb * 1024)],
            ], [
                'uploads.max' => 'Add up to '.config('pdf.max_files').' files at a time.',
                'uploads.*.extensions' => 'Use PDF, Word, Excel, PowerPoint, JPG, PNG or HTML files.',
                'uploads.*.max' => "Each file must be {$maxMb} MB or smaller.",
            ]);
        } catch (ValidationException $e) {
            $this->reset('uploads');

            throw $e;
        }

        $this->workspace = PdfWorkspace::create();
        $this->files = [];
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
                    // Still useful for Repair and Unlock.
                }
            }

            $this->files[] = ['id' => $id, 'name' => $upload->getClientOriginalName(), 'ext' => $ext, 'pages' => $pages, 'bytes' => $upload->getSize()];
        }

        $this->reset('uploads');
    }

    public function pick(string $slug)
    {
        if (! in_array($slug, FileHandoff::toolsFor($this->files), true)) {
            return null;
        }

        $token = FileHandoff::put($this->workspace, $this->files);
        $url = $slug === 'merge-pdf' ? route('tools.merge') : route('tools.show', $slug);

        return $this->redirect($url.'?from='.$token);
    }

    public function cancel(): void
    {
        if ($this->workspace) {
            PdfWorkspace::disk()->deleteDirectory(PdfWorkspace::path($this->workspace));
        }

        $this->reset('files', 'workspace');
    }

    public function render()
    {
        $tools = collect(ToolCatalog::flat())->keyBy('slug');

        return view('livewire.home-drop', [
            'suggestions' => array_map(fn ($slug) => $tools[$slug], array_slice(FileHandoff::toolsFor($this->files), 0, 10)),
            'accept' => implode(',', array_map(fn ($ext) => '.'.$ext, self::extensions())),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private static function extensions(): array
    {
        return array_values(array_unique(['pdf', ...collect(PdfTools::all())->pluck('accept')->flatten()->all()]));
    }
}
