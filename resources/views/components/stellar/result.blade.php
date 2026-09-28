{{-- Step 3 of every tool: success heading, file card with download, next steps. --}}
@props(['title', 'subtitle', 'name', 'meta', 'again', 'ext' => 'pdf', 'current' => null])

@php
    $tools = collect(App\Support\ToolCatalog::flat())->keyBy('slug');
    $next = collect(['compress-pdf', 'protect-pdf', 'sign-pdf', 'add-page-numbers', 'merge-pdf', 'split-pdf'])
        ->reject(fn ($slug) => $slug === $current)
        ->take(4)
        ->map(fn ($slug) => $tools[$slug]);
    $short = fn ($name) => $name === 'Add page numbers' ? 'Page numbers' : Str::replaceLast(' PDF', '', $name);
@endphp

<main class="flex-grow px-[18px]">
    <div class="mx-auto flex max-w-[680px] flex-col items-center gap-7 py-[72px] text-center">
        <div>
            <div class="mx-auto mb-3.5 flex h-[68px] w-[68px] items-center justify-center rounded-full bg-success-soft text-success">
                <x-stellar.icon d="M5 12.5l4.5 4.5L19 7.5" :size="32" :width="2.3" />
            </div>
            <h1 class="m-0 text-[clamp(30px,4vw,42px)] font-extrabold tracking-[-0.02em]">{{ $title }}</h1>
            <p class="m-0 mt-1.5 text-[16.5px] text-muted">{{ $subtitle }}</p>
        </div>

        <div class="flex w-full flex-wrap items-center gap-4 rounded-[18px] border border-line bg-white p-5 text-left">
            <div class="flex h-[62px] w-[50px] shrink-0 items-end justify-center rounded-lg bg-accent-soft pb-[9px] text-xs font-extrabold uppercase text-accent">{{ $ext }}</div>
            <div class="min-w-[160px] flex-1">
                <strong class="block break-all text-[17px]">{{ $name }}</strong>
                <span class="text-[13.5px] text-muted">{{ $meta }}</span>
            </div>
            <button type="button" wire:click="download" class="inline-flex h-12 items-center justify-center gap-[9px] rounded-xl bg-accent px-[22px] text-[15.5px] font-bold text-white hover:bg-accent-dark">
                <x-stellar.icon d="M12 4v12M7 11l5 5 5-5M4 20h16" :size="18" :width="2.1" />
                Download
            </button>
        </div>

        {{ $slot }}

        @if ($ext === 'pdf')
            <div class="w-full text-left">
                <h3 class="m-0 mb-3 text-[15px] font-bold">Keep working on this file</h3>
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
                    @foreach ($next as $tool)
                        <a href="{{ $tool['url'] }}" class="flex h-[50px] items-center justify-center gap-2 rounded-xl border border-line-strong bg-white text-[14.5px] font-semibold text-ink no-underline hover:border-ink">
                            <x-stellar.icon :d="$tool['icon']" :size="17" :stroke="$tool['color']" />
                            {{ $short($tool['name']) }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex gap-[22px] font-semibold">
            <button type="button" wire:click="startOver" class="font-semibold text-ink underline">{{ $again }}</button>
            <a href="{{ route('home') }}" class="text-ink">All tools</a>
        </div>
    </div>
</main>
