@php
    use App\Support\PdfWorkspace;

    $multiple = $definition['multiple'];
    [$singular, $plural] = $definition['noun'];
    $uploadErrors = collect($errors->getMessages())
        ->filter(fn ($messages, $key) => Str::startsWith($key, 'uploads'))
        ->flatten()
        ->unique();
    $limits = ($multiple ? 'Up to '.$maxFiles.' files · ' : 'One file · ')
        .config('pdf.max_file_mb').' MB'.($multiple ? ' each' : '').' · '.$formats;
    $wide = collect($definition['options'])->filter(fn ($option) => in_array($option['type'], ['pages', 'placements', 'form'], true));
    $narrow = collect($definition['options'])->diffKeys($wide);
    $fileMeta = fn (?int $pages, int $bytes) => ($pages ? $pages.' '.Str::plural('page', $pages).' · ' : '').PdfWorkspace::formatBytes($bytes);
    $minFiles = $definition['fileLabels'] ? count($definition['fileLabels']) : 1;
    $missing = max(0, $minFiles - count($files));
    $card = 'flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-white hover:border-ink disabled:opacity-40';
@endphp

<div class="flex flex-grow flex-col">
    <input
        id="tool-files"
        type="file"
        wire:model="uploads"
        @if ($multiple) multiple @endif
        accept="{{ collect($definition['accept'])->map(fn ($ext) => '.'.$ext)->implode(',') }}"
        class="sr-only"
        tabindex="-1"
        aria-label="Upload {{ $multiple ? $plural : $singular }}"
    >

    <x-stellar.tool-head :tool="$info" :category="$category" :step="$result ? 2 : ($files ? 1 : 0)" />

    @if ($result)
        @php
            $saved = $result['inputBytes'] > 0 ? (int) round((1 - $result['bytes'] / $result['inputBytes']) * 100) : 0;
            $subtitle = match (true) {
                isset($result['meta']['summary']) => $result['meta']['summary'],
                $definition['savings'] && $saved > 0 => "It’s now {$saved}% smaller.",
                $definition['savings'] => 'This PDF was already well optimized, so its size is unchanged.',
                default => $definition['done'],
            };
            $meta = $definition['savings'] && $saved > 0
                ? PdfWorkspace::formatBytes($result['inputBytes']).' → '.PdfWorkspace::formatBytes($result['bytes'])
                : $fileMeta($result['pages'], $result['bytes']);
            $title = match ($result['ext']) {
                'md' => $tool === 'ai-summarizer' ? 'Your summary is ready' : 'Your Markdown is ready',
                default => 'Your file is ready',
            };
        @endphp

        <x-stellar.result
            :title="$title"
            :subtitle="$subtitle"
            :name="$result['name']"
            :meta="$meta"
            :ext="$result['ext']"
            :again="$info['name'].' again'"
            :current="$tool"
        >
            @error('process')
                <p class="m-0 w-full rounded-xl bg-accent-soft px-4 py-3 text-sm font-semibold text-accent-dark" role="alert">{{ $message }}</p>
            @enderror

            @if ($result['preview'])
                <article class="prose prose-neutral w-full max-w-none rounded-[18px] border border-line bg-white p-6 text-left prose-a:text-accent sm:p-8">
                    {!! Str::markdown($result['preview'], ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                </article>
            @endif
        </x-stellar.result>
    @else
        <div class="mx-auto w-full max-w-[1320px] px-[18px] md:px-10">
            <div class="grid items-start gap-7 pb-16 pt-7 lg:grid-cols-[minmax(0,1fr)_380px]">
                <section aria-label="Files" class="flex min-w-0 flex-col gap-6">
                    @if (! $available)
                        <div class="flex min-h-[420px] flex-col items-center justify-center gap-3 rounded-[22px] border-2 border-dashed border-line-strong bg-white p-8 text-center">
                            <strong class="text-[22px]">This tool isn’t set up yet</strong>
                            <p class="m-0 max-w-[560px] leading-relaxed text-muted">
                                {{ $info['name'] }} uses the Claude API. An administrator needs to add an Anthropic API key as
                                <code class="rounded bg-sand px-1.5 py-0.5 text-[13px] text-ink">ANTHROPIC_API_KEY</code>
                                in the app’s <code class="rounded bg-sand px-1.5 py-0.5 text-[13px] text-ink">.env</code> file. Each run is billed to that key’s account.
                            </p>
                        </div>
                    @elseif (! $files)
                        <x-stellar.dropzone
                            :title="'Drop '.($multiple ? $plural : 'a '.$singular).' here'"
                            :hint="$definition['fileLabels']
                                ? 'Add the '.Str::lower(implode(' and the ', $definition['fileLabels'])).' version.'
                                : ($multiple ? 'Add one or more files. You can reorder them next.' : 'Add one file. You can choose settings next.')"
                            :button="'Choose '.($multiple ? 'files' : 'a file')"
                            :limits="$limits"
                            :messages="$uploadErrors"
                        />
                    @else
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <strong class="text-[17px]">
                                {{ count($files) }} {{ Str::plural('file', count($files)) }}@if ($multiple && count($files) > 1 && ! $definition['fileLabels']) · use the arrows to reorder @endif
                            </strong>
                            <div class="flex gap-2">
                                @if (! $multiple || count($files) < $maxFiles)
                                    <button type="button" x-on:click="document.getElementById('tool-files').click()" class="inline-flex h-[42px] items-center gap-2 rounded-[10px] border border-line-strong bg-white px-[15px] text-[14.5px] font-semibold hover:border-ink">
                                        <span wire:loading.remove wire:target="uploads" class="flex items-center gap-2">
                                            <x-stellar.icon :d="$multiple ? 'M12 5v14M5 12h14' : App\Support\ToolCatalog::ICONS['rotate']" :size="17" />
                                            {{ $multiple ? 'Add files' : 'Replace file' }}
                                        </span>
                                        <span wire:loading wire:target="uploads">Uploading…</span>
                                    </button>
                                @endif
                                <button type="button" wire:click="startOver" class="inline-flex h-[42px] items-center rounded-[10px] border border-line-strong bg-white px-[15px] text-[14.5px] font-semibold hover:border-ink">Clear</button>
                            </div>
                        </div>

                        <x-stellar.errors :messages="$uploadErrors" />

                        <div
                            class="grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-4"
                            x-data="{ over: false }"
                            x-on:dragover.prevent="over = true"
                            x-on:dragleave.prevent="over = false"
                            x-on:drop.prevent="over = false; if ($event.dataTransfer.files.length) $wire.uploadMultiple('uploads', Array.from($event.dataTransfer.files))"
                            x-bind:class="over && 'rounded-[18px] outline-dashed outline-2 outline-offset-4 outline-accent'"
                        >
                            @foreach ($files as $i => $file)
                                <div wire:key="file-{{ $file['id'] }}" class="flex flex-col gap-2.5 rounded-[18px] border border-line bg-white p-3">
                                    <div class="relative flex h-[200px] items-center justify-center overflow-hidden rounded-[10px] bg-sand">
                                        @if ($file['ext'] === 'pdf' && $file['pages'])
                                            <img src="{{ route('workspace.page', [$workspace, $file['id'], 1, 'thumb']) }}" alt="" loading="lazy" class="max-h-[176px] max-w-[85%] rounded-[3px] bg-white shadow-[0_2px_8px_rgba(27,29,34,0.12)]">
                                        @else
                                            <div class="flex h-[156px] w-[116px] flex-col gap-[7px] rounded-[3px] bg-white px-[13px] py-[15px] shadow-[0_2px_8px_rgba(27,29,34,0.12)]" aria-hidden="true">
                                                <i class="block h-[9px] w-[68%] rounded-sm bg-line-strong"></i>
                                                <i class="block h-[5px] rounded-sm bg-sand-dark"></i>
                                                <i class="block h-[5px] rounded-sm bg-sand-dark"></i>
                                                <i class="block h-[5px] w-4/5 rounded-sm bg-sand-dark"></i>
                                                <i class="mt-1 block h-9 rounded-sm bg-sand"></i>
                                                <i class="block h-[5px] rounded-sm bg-sand-dark"></i>
                                                <i class="block h-[5px] w-3/5 rounded-sm bg-sand-dark"></i>
                                            </div>
                                        @endif

                                        @if ($definition['fileLabels'])
                                            <span class="absolute left-2 top-2 flex h-[26px] items-center justify-center rounded-full bg-ink px-2.5 text-[12.5px] font-bold text-white">{{ $definition['fileLabels'][$i] ?? '' }}</span>
                                        @elseif ($multiple)
                                            <span class="absolute left-2 top-2 flex h-[26px] min-w-[26px] items-center justify-center rounded-full bg-ink px-1.5 text-[12.5px] font-bold text-white">{{ $i + 1 }}</span>
                                        @endif
                                        <span class="absolute bottom-2 left-2 rounded-md bg-ink px-[7px] py-0.5 text-[11px] font-bold uppercase text-white">{{ $file['ext'] }}</span>

                                        <div class="absolute right-1.5 top-1.5 flex gap-[5px]">
                                            @if ($multiple && count($files) > 1)
                                                <button type="button" wire:click="nudge(@js($file['id']), -1)" @disabled($i === 0) aria-label="Move {{ $file['name'] }} earlier" class="{{ $card }}">
                                                    <x-stellar.icon d="M15 18l-6-6 6-6" :size="15" />
                                                </button>
                                                <button type="button" wire:click="nudge(@js($file['id']), 1)" @disabled($i === count($files) - 1) aria-label="Move {{ $file['name'] }} later" class="{{ $card }}">
                                                    <x-stellar.icon d="M9 6l6 6-6 6" :size="15" />
                                                </button>
                                            @endif
                                            <button type="button" wire:click="remove(@js($file['id']))" aria-label="Remove {{ $file['name'] }}" class="{{ $card }}">
                                                <x-stellar.icon d="M6 6l12 12M18 6L6 18" :size="15" />
                                            </button>
                                        </div>
                                    </div>
                                    <div class="min-w-0">
                                        <strong class="block truncate text-sm" title="{{ $file['name'] }}">{{ $file['name'] }}</strong>
                                        <span class="text-[12.5px] text-muted">{{ $fileMeta($file['pages'], $file['bytes']) }}</span>
                                    </div>
                                </div>
                            @endforeach

                            @if ($multiple && count($files) < $maxFiles)
                                <button type="button" x-on:click="document.getElementById('tool-files').click()" class="flex min-h-[268px] flex-col items-center justify-center gap-2 rounded-[18px] border-2 border-dashed border-line-strong font-semibold text-muted hover:border-ink hover:text-ink">
                                    <x-stellar.icon d="M12 5v14M5 12h14" :size="26" />
                                    {{ $definition['fileLabels'] ? 'Add the '.Str::lower($definition['fileLabels'][count($files)] ?? '').' file' : 'Add files' }}
                                </button>
                            @endif
                        </div>

                        @foreach ($wide as $name => $option)
                            @if ($option['type'] === 'form')
                                <x-stellar.form-fields :name="$name" :option="$option" :fields="$fields" />
                            @elseif (! $pageCount)
                                <x-stellar.errors :messages="['The pages of this PDF couldn’t be read. It may be password protected.']" />
                            @elseif ($option['type'] === 'pages')
                                <x-stellar.page-grid :name="$name" :option="$option" :workspace="$workspace" :file="$files[0]" :count="$pageCount" />
                            @else
                                <x-stellar.page-editor :name="$name" :option="$option" :workspace="$workspace" :file="$files[0]" :count="$pageCount" />
                            @endif
                        @endforeach
                    @endif
                </section>

                <aside aria-label="Settings" class="flex flex-col gap-[22px] rounded-[18px] border border-line bg-white p-6 lg:sticky lg:top-[84px]">
                    <h2 class="m-0 text-[19px] font-extrabold tracking-[-0.02em]">{{ $info['name'] }} settings</h2>

                    @forelse ($narrow as $name => $option)
                        @php
                            $when = collect($option['when'] ?? [])
                                ->map(fn ($values, $field) => json_encode($values).'.includes($wire.options.'.$field.')')
                                ->implode(' && ');
                        @endphp

                        <div wire:key="option-{{ $name }}" @if ($when) x-show="{{ $when }}" x-cloak @endif>
                            <x-stellar.option :name="$name" :option="$option" />
                        </div>
                    @empty
                        @if ($wide->isEmpty())
                            <p class="m-0 text-sm text-muted">No extra settings needed. Add your {{ $multiple ? 'files' : 'file' }} and run {{ $info['name'] }}.</p>
                        @endif
                    @endforelse

                    @if ($definition['note'])
                        <p class="m-0 rounded-[10px] bg-paper p-3.5 text-sm leading-relaxed text-muted">{{ $definition['note'] }}</p>
                    @endif

                    <div class="flex justify-between gap-4 rounded-[10px] bg-paper px-3.5 py-3 text-sm">
                        <span class="text-muted">Selected</span>
                        <span class="text-right font-bold">
                            @if ($files)
                                {{ count($files) }} {{ Str::plural('file', count($files)) }}@if ($pageCount) · {{ $pageCount }} {{ Str::plural('page', $pageCount) }}@endif · {{ PdfWorkspace::formatBytes(array_sum(array_column($files, 'bytes'))) }}
                            @else
                                No files yet
                            @endif
                        </span>
                    </div>

                    @error('process')
                        <p class="m-0 rounded-[10px] bg-accent-soft px-3.5 py-3 text-sm font-semibold text-accent-dark" role="alert">{{ $message }}</p>
                    @enderror

                    <button
                        type="button"
                        wire:click="process"
                        {{-- Not wire:loading.attr="disabled": Livewire restores the attribute it saw when loading began, which would keep the button disabled after the first upload. --}}
                        wire:loading.class="pointer-events-none opacity-70"
                        wire:target="process, uploads"
                        @disabled(! $available || ! $files || $missing > 0)
                        class="inline-flex h-14 w-full items-center justify-center gap-[9px] rounded-xl bg-accent px-7 text-[16.5px] font-bold text-white hover:bg-accent-dark disabled:cursor-not-allowed disabled:bg-accent-faded"
                    >
                        {{ $files && $missing ? 'Add '.$missing.' more '.Str::plural('file', $missing) : $definition['action'] }}
                        <x-stellar.icon d="M5 12h14M13 6l6 6-6 6" :size="18" />
                    </button>
                    <div class="-mt-2.5 text-center text-[12.5px] text-muted">Files are removed after {{ PdfWorkspace::retention() }}.</div>
                </aside>
            </div>
        </div>

        <x-stellar.processing
            target="process"
            :title="$definition['working']"
            :note="count($files).' '.Str::plural('file', count($files)).' in progress. This page updates when it’s done.'"
        />
    @endif
</div>
