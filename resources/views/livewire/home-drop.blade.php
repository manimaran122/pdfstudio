@php
    $messages = collect($errors->getMessages())->flatten()->unique();
@endphp

<div>
    <input id="home-files" type="file" wire:model="uploads" multiple accept="{{ $accept }}" class="sr-only" tabindex="-1" aria-label="Upload files">

    <div
        x-data="{ over: false }"
        x-on:dragover.prevent="over = true"
        x-on:dragleave.prevent="over = false"
        x-on:drop.prevent="over = false; if ($event.dataTransfer.files.length) $wire.uploadMultiple('uploads', Array.from($event.dataTransfer.files))"
        x-bind:class="over ? 'border-accent bg-[#FFF8F7]' : 'border-line-strong bg-white'"
        class="flex flex-col items-center gap-3.5 rounded-[22px] border-2 border-dashed px-8 py-10 text-center transition-colors"
    >
        <div class="flex h-[78px] w-16 items-center justify-center rounded-[10px] bg-accent-soft text-accent">
            <x-stellar.icon d="M12 16V5M7 10l5-5 5 5M5 19h14" :size="30" />
        </div>
        <strong class="text-[21px]">Drop files to get started</strong>
        <span class="text-muted">PDF, Word, Excel, PowerPoint, JPG or HTML</span>
        <button type="button" x-on:click="document.getElementById('home-files').click()" class="inline-flex h-14 items-center justify-center gap-2 rounded-xl bg-accent px-7 text-[16.5px] font-bold text-white hover:bg-accent-dark">
            <span wire:loading.remove wire:target="uploads">Choose files</span>
            <span wire:loading wire:target="uploads">Uploading…</span>
        </button>
        <small class="text-[13px] text-muted-light">Up to {{ config('pdf.max_file_mb') }} MB per file</small>
        <x-stellar.errors :messages="$messages" />
    </div>

    @if ($files)
        <div
            class="fixed inset-0 z-[90] flex items-start justify-center bg-ink/45 px-4 pb-4 pt-[12vh]"
            x-data
            x-on:keydown.escape.window="$wire.cancel()"
            x-on:click.self="$wire.cancel()"
        >
            <div role="dialog" aria-modal="true" aria-labelledby="pick-h" class="w-full max-w-[620px] overflow-hidden rounded-[18px] bg-white shadow-[0_30px_80px_rgba(0,0,0,0.25)]" x-init="$nextTick(() => $el.querySelector('a, button')?.focus())">
                <div class="px-6 pb-2 pt-[22px]">
                    <h2 id="pick-h" class="m-0 text-[21px] font-extrabold tracking-[-0.02em]">What do you want to do?</h2>
                    <p class="m-0 mt-1 truncate text-sm text-muted">
                        {{ count($files) === 1 ? $files[0]['name'] : count($files).' files: '.collect($files)->pluck('name')->implode(', ') }}
                    </p>
                </div>

                <div class="grid gap-2 px-6 pb-5 pt-3.5 sm:grid-cols-2">
                    @forelse ($suggestions as $tool)
                        <button type="button" wire:click="pick(@js($tool['slug']))" class="flex items-center gap-[11px] rounded-xl border border-line p-[11px] text-left text-[14.5px] font-semibold hover:border-ink">
                            <x-stellar.tile :color="$tool['color']" :tint="$tool['tint']" :icon="$tool['icon']" :size="32" :icon-size="16" />
                            {{ $tool['name'] }}
                        </button>
                    @empty
                        <p class="col-span-full m-0 text-muted">
                            These files can’t be used together. Add files of one type, such as all PDFs or all images.
                        </p>
                    @endforelse
                </div>

                <div class="flex items-center justify-between border-t border-line px-6 py-3.5">
                    <button type="button" wire:click="cancel" class="inline-flex h-[42px] items-center rounded-[10px] border border-line-strong bg-white px-[15px] text-[14.5px] font-semibold hover:border-ink">Cancel</button>
                    <a href="#all" wire:click="cancel" class="font-semibold text-accent">See all tools</a>
                </div>
            </div>
        </div>
    @endif
</div>
