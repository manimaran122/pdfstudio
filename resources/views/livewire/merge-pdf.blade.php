@php
    use App\Support\PdfWorkspace;

    $merge = collect($category['tools'])->firstWhere('name', 'Merge PDF');
    $uploadErrors = collect($errors->getMessages())
        ->filter(fn ($messages, $key) => Str::startsWith($key, 'uploads'))
        ->flatten()
        ->unique();
    $dropFiles = "if (\$event.dataTransfer.files.length) \$wire.uploadMultiple('uploads', Array.from(\$event.dataTransfer.files))";
    $card = 'flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-white hover:border-ink';
    $input = 'h-[46px] rounded-[10px] border-line-strong px-[13px] text-[15px] text-ink focus:border-line-strong focus:ring-0';
@endphp

<div class="flex flex-grow flex-col">
    <input id="tool-files" type="file" wire:model="uploads" multiple accept="application/pdf,.pdf" class="sr-only" tabindex="-1" aria-label="Upload PDF files">

    <x-stellar.tool-head :tool="$merge" :category="$category" :step="$result ? 2 : ($files ? 1 : 0)" />

    @if ($result)
        <x-stellar.result
            title="Your file is ready"
            :subtitle="$result['count'].' files were merged into one document.'"
            :name="$result['name']"
            :meta="$result['pages'].' '.Str::plural('page', $result['pages']).' · '.PdfWorkspace::formatBytes($result['bytes'])"
            again="Merge PDF again"
            current="merge-pdf"
        >
            @error('merge')
                <p class="m-0 w-full rounded-xl bg-accent-soft px-4 py-3 text-sm font-semibold text-accent-dark" role="alert">{{ $message }}</p>
            @enderror
        </x-stellar.result>
    @else
        <div class="mx-auto w-full max-w-[1320px] px-[18px] md:px-10">
            <div class="grid items-start gap-7 pb-16 pt-7 lg:grid-cols-[minmax(0,1fr)_380px]">
                <section aria-label="Files" class="flex min-w-0 flex-col gap-4">
                    @if (! $files)
                        <x-stellar.dropzone
                            title="Drop PDF files here"
                            hint="Add 2 or more files. You can reorder them next."
                            button="Choose files"
                            :limits="'Up to '.config('pdf.max_files').' files · '.config('pdf.max_file_mb').' MB each · PDF only'"
                            :messages="$uploadErrors"
                        />
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <strong class="text-[17px]">{{ count($files) }} {{ Str::plural('file', count($files)) }} · drag to reorder</strong>
                            <div class="flex gap-2">
                                <button type="button" wire:click="sortByName" class="inline-flex h-[42px] items-center gap-2 rounded-[10px] border border-line-strong bg-white px-[15px] text-[14.5px] font-semibold hover:border-ink">
                                    <x-stellar.icon d="M4 6h10M4 12h7M4 18h4M17 4v16M14 17l3 3 3-3" :size="17" />
                                    Sort A–Z
                                </button>
                                <button type="button" wire:click="startOver" class="inline-flex h-[42px] items-center rounded-[10px] border border-line-strong bg-white px-[15px] text-[14.5px] font-semibold hover:border-ink">Clear</button>
                            </div>
                        </div>

                        <x-stellar.errors :messages="$uploadErrors" />

                        <div x-data="{ dragging: null, over: null }" class="grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-4">
                            @foreach ($files as $i => $file)
                                <div
                                    wire:key="file-{{ $file['id'] }}"
                                    draggable="true"
                                    x-on:dragstart="dragging = @js($file['id']); $event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('text/plain', @js($file['id']))"
                                    x-on:dragend="dragging = null; over = null"
                                    x-on:dragover.prevent="if (dragging) over = @js($file['id'])"
                                    x-on:drop.prevent="if (dragging && dragging !== @js($file['id'])) $wire.move(dragging, @js($file['id'])); dragging = null; over = null"
                                    x-bind:class="{ 'opacity-40': dragging === @js($file['id']), 'border-accent': over === @js($file['id']) && dragging !== @js($file['id']) }"
                                    class="flex cursor-grab flex-col gap-2.5 rounded-[18px] border border-line bg-white p-3 active:cursor-grabbing"
                                >
                                    <div class="relative flex h-[200px] items-center justify-center overflow-hidden rounded-[10px] bg-sand">
                                        <img src="{{ route('workspace.page', [$workspace, $file['id'], 1, 'thumb']) }}" alt="" loading="lazy" draggable="false"
                                            class="max-h-[176px] max-w-[85%] rounded-[3px] bg-white shadow-[0_2px_8px_rgba(27,29,34,0.12)] transition-transform duration-200"
                                            style="transform: rotate({{ $file['rotation'] }}deg) scale({{ $file['rotation'] % 180 ? 0.8 : 1 }})">
                                        <span class="absolute left-2 top-2 flex h-[26px] min-w-[26px] items-center justify-center rounded-full bg-ink px-1.5 text-[12.5px] font-bold text-white">{{ $i + 1 }}</span>
                                        <span class="absolute bottom-2 left-2 rounded-md bg-ink px-[7px] py-0.5 text-[11px] font-bold text-white">PDF</span>
                                        <div class="absolute right-1.5 top-1.5 flex gap-[5px]">
                                            <button type="button" wire:click="rotate(@js($file['id']))" aria-label="Rotate {{ $file['name'] }}" class="{{ $card }}">
                                                <x-stellar.icon :d="App\Support\ToolCatalog::ICONS['rotate']" :size="15" />
                                            </button>
                                            <button type="button" wire:click="remove(@js($file['id']))" aria-label="Remove {{ $file['name'] }}" class="{{ $card }}">
                                                <x-stellar.icon d="M6 6l12 12M18 6L6 18" :size="15" />
                                            </button>
                                        </div>
                                    </div>
                                    <div class="flex min-w-0 items-center gap-2">
                                        <button
                                            type="button"
                                            aria-label="Move {{ $file['name'] }}, position {{ $i + 1 }} of {{ count($files) }}. Use the left and right arrow keys."
                                            x-on:keydown.arrow-left.prevent="$wire.nudge(@js($file['id']), -1)"
                                            x-on:keydown.arrow-right.prevent="$wire.nudge(@js($file['id']), 1)"
                                            class="-m-1 shrink-0 rounded p-1 text-muted-light"
                                        >
                                            <x-stellar.icon d="M9 6h.01M15 6h.01M9 12h.01M15 12h.01M9 18h.01M15 18h.01" :size="16" />
                                        </button>
                                        <div class="min-w-0">
                                            <strong class="block truncate text-sm" title="{{ $file['name'] }}">{{ $file['name'] }}</strong>
                                            <span class="text-[12.5px] text-muted">{{ $file['pages'] }} {{ Str::plural('page', $file['pages']) }} · {{ PdfWorkspace::formatBytes($file['bytes']) }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            @if (count($files) < config('pdf.max_files'))
                                <button
                                    type="button"
                                    x-on:click="document.getElementById('tool-files').click()"
                                    x-data="{ over: false }"
                                    x-on:dragover.prevent="if (! dragging) over = true"
                                    x-on:dragleave.prevent="over = false"
                                    x-on:drop.prevent="over = false; {{ $dropFiles }}"
                                    x-bind:class="over ? 'border-accent text-ink' : 'border-line-strong text-muted'"
                                    class="flex min-h-[268px] flex-col items-center justify-center gap-2 rounded-[18px] border-2 border-dashed font-semibold hover:border-ink hover:text-ink"
                                >
                                    <span wire:loading.remove wire:target="uploads" class="flex flex-col items-center gap-2">
                                        <x-stellar.icon d="M12 5v14M5 12h14" :size="26" />
                                        Add files
                                    </span>
                                    <span wire:loading wire:target="uploads">Uploading…</span>
                                </button>
                            @endif
                        </div>
                    @endif
                </section>

                <aside aria-label="Settings" class="flex flex-col gap-[22px] rounded-[18px] border border-line bg-white p-6 lg:sticky lg:top-[84px]">
                    <h2 class="m-0 text-[19px] font-extrabold tracking-[-0.02em]">Merge PDF settings</h2>

                    <div class="flex flex-col gap-[7px]">
                        <label for="outname" class="text-[13.5px] font-bold">Output file name</label>
                        <input id="outname" type="text" wire:model="outputName" class="{{ $input }}">
                        @error('outputName') <span class="text-[12.5px] font-semibold text-accent">{{ $message }}</span> @enderror
                    </div>

                    <fieldset class="m-0 flex flex-col gap-2 border-none p-0">
                        <legend class="mb-[7px] text-[13.5px] font-bold">Page order</legend>
                        @foreach (['arranged' => ['As arranged', 'Drag files on the left to change the order.'], 'alphabetical' => ['Alphabetical', 'Sort by file name.']] as $value => [$label, $hint])
                            <label class="flex cursor-pointer items-start gap-[11px] rounded-[10px] border border-line-strong px-[13px] py-3 text-[14.5px] has-[:checked]:border-ink has-[:checked]:bg-[#FBFBFA]">
                                <input type="radio" name="order" value="{{ $value }}" wire:model="order" class="mt-[3px] h-[17px] w-[17px] border-line-strong text-ink focus:ring-focus">
                                <span>{{ $label }}<small class="block text-[12.5px] text-muted">{{ $hint }}</small></span>
                            </label>
                        @endforeach
                    </fieldset>

                    <label class="flex cursor-pointer items-start gap-[11px] text-[14.5px]">
                        <input type="checkbox" wire:model="bookmarks" class="mt-[3px] h-[17px] w-[17px] rounded border-line-strong text-ink focus:ring-focus">
                        <span>Add a bookmark for each file<small class="block text-[12.5px] text-muted">Uses the original file names.</small></span>
                    </label>

                    <div class="flex justify-between gap-4 rounded-[10px] bg-paper px-3.5 py-3 text-sm">
                        <span class="text-muted">Selected</span>
                        <span class="text-right font-bold">{{ $files ? $summary : 'No files yet' }}</span>
                    </div>

                    @error('merge')
                        <p class="m-0 rounded-[10px] bg-accent-soft px-3.5 py-3 text-sm font-semibold text-accent-dark" role="alert">{{ $message }}</p>
                    @enderror

                    <button
                        type="button"
                        wire:click="merge"
                        {{-- Not wire:loading.attr="disabled": Livewire restores the attribute it saw when loading began, which would keep the button disabled after the first upload. --}}
                        wire:loading.class="pointer-events-none opacity-70"
                        wire:target="merge, uploads"
                        @disabled(count($files) < 2)
                        class="inline-flex h-14 w-full items-center justify-center gap-[9px] rounded-xl bg-accent px-7 text-[16.5px] font-bold text-white hover:bg-accent-dark disabled:cursor-not-allowed disabled:bg-accent-faded"
                    >
                        @if ($files && count($files) < 2)
                            Add 1 more file
                        @else
                            Merge{{ $files ? ' '.count($files).' files' : '' }}
                        @endif
                        <x-stellar.icon d="M5 12h14M13 6l6 6-6 6" :size="18" />
                    </button>
                    <div class="-mt-2.5 text-center text-[12.5px] text-muted">Files are removed after {{ PdfWorkspace::retention() }}.</div>
                </aside>
            </div>
        </div>

        <x-stellar.processing target="merge" title="Merge PDF in progress" :note="count($files).' files in progress. This page updates when it’s done.'" />
    @endif
</div>
