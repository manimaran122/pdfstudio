@php
    use App\Support\PdfWorkspace;
    use App\Support\ToolCatalog;

    $chips = [['id' => 'all', 'name' => 'All', 'count' => $toolCount], ...array_map(fn ($c) => ['id' => $c['id'], 'name' => $c['name'], 'count' => count($c['tools'])], $categories)];
@endphp

<x-stellar-layout title="All PDF tools">
    <main class="flex-grow">
        <div class="mx-auto max-w-[1320px] px-[18px] md:px-10">
            <section class="grid items-center gap-7 pb-12 pt-9 md:grid-cols-[1.05fr_1fr] md:gap-12 md:pt-14">
                <div>
                    <h1 class="m-0 mb-3.5 text-[clamp(36px,4.4vw,54px)] font-extrabold leading-[1.04] tracking-[-0.035em]">Work with PDFs on your own servers.</h1>
                    <p class="m-0 max-w-[520px] text-lg text-muted">Merge, split, compress, convert, sign and protect documents. Drop a file to see what you can do with it, or pick a tool below.</p>
                    <div class="mt-7 flex gap-7 text-sm text-muted">
                        <div><strong class="block text-[22px] text-ink">{{ $toolCount }}</strong>tools</div>
                        <div><strong class="block text-[22px] text-ink">{{ count($categories) }}</strong>categories</div>
                        <div><strong class="block text-[22px] text-ink">{{ PdfWorkspace::retention() }}</strong>file retention</div>
                    </div>
                </div>

                <livewire:home-drop />
            </section>

            <div class="mb-[18px] flex items-baseline justify-between gap-4">
                <h2 class="m-0 text-2xl font-extrabold tracking-[-0.02em]">Most used</h2>
                <span class="text-sm text-muted">Also in the top bar</span>
            </div>
            <div class="mb-14 grid gap-3.5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach (ToolCatalog::popular() as $tool)
                    <a href="{{ $tool['url'] }}" class="flex items-center gap-3.5 rounded-[18px] border border-line bg-white p-4 text-ink no-underline transition-colors hover:border-ink">
                        <x-stellar.tile :color="$tool['color']" :tint="$tool['tint']" :icon="$tool['icon']" :icon-size="22" />
                        <div class="min-w-0">
                            <strong class="block text-[15.5px]">{{ $tool['name'] }}</strong>
                            <span class="text-[13.5px] text-muted">{{ Str::before(rtrim($tool['desc'], '.'), ',') }}</span>
                        </div>
                    </a>
                @endforeach
            </div>

            <section x-data="{ cat: 'all' }" aria-labelledby="all">
                <div class="mb-[18px] flex items-baseline justify-between gap-4">
                    <h2 id="all" class="m-0 text-2xl font-extrabold tracking-[-0.02em]">All tools</h2>
                </div>
                <div role="toolbar" aria-label="Filter by category" class="mb-[22px] flex flex-wrap gap-2">
                    @foreach ($chips as $chip)
                        <button type="button" x-on:click="cat = @js($chip['id'])" x-bind:aria-pressed="cat === @js($chip['id'])"
                            x-bind:class="cat === @js($chip['id']) ? 'bg-ink border-ink text-white' : 'bg-white border-line-strong text-ink hover:border-ink'"
                            class="inline-flex h-[38px] items-center gap-[7px] rounded-full border px-3.5 text-sm font-semibold">
                            {{ $chip['name'] }}
                            <span class="font-medium" x-bind:class="cat === @js($chip['id']) ? 'text-muted-inverse' : 'text-muted-light'">{{ $chip['count'] }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="mb-14 grid grid-cols-[repeat(auto-fill,minmax(290px,1fr))] gap-4">
                    @foreach ($categories as $category)
                        <section x-show="cat === 'all' || cat === @js($category['id'])" class="rounded-[18px] border border-line bg-white px-5 pb-3 pt-5" aria-labelledby="cat-{{ $category['id'] }}">
                            <header class="mb-1.5 flex items-center gap-2.5">
                                <i class="h-2.5 w-2.5 rounded-[3px]" style="background: {{ $category['color'] }}" aria-hidden="true"></i>
                                <h3 id="cat-{{ $category['id'] }}" class="m-0 text-[16.5px] font-bold tracking-[-0.02em]">{{ $category['name'] }}</h3>
                            </header>
                            <p class="m-0 mb-2.5 text-[13.5px] text-muted">{{ $category['blurb'] }}</p>
                            @foreach ($category['tools'] as $tool)
                                <a href="{{ $tool['url'] ?? route('home') }}" class="-mx-2 flex items-center gap-3 rounded-[10px] px-2 py-2.5 text-ink no-underline hover:bg-sand">
                                    <x-stellar.tile :color="$category['color']" :tint="$category['tint']" :icon="$tool['icon']" :size="32" :icon-size="17" />
                                    <strong class="text-[14.5px] font-semibold">{{ $tool['name'] }}</strong>
                                    @if ($tool['badge'])
                                        <span class="ml-auto rounded-md bg-sand px-[7px] py-0.5 text-[11.5px] font-semibold text-muted">{{ $tool['badge'] }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </section>
                    @endforeach
                </div>
            </section>

            <div class="mb-[18px] flex items-baseline justify-between gap-4">
                <h2 class="m-0 text-2xl font-extrabold tracking-[-0.02em]">Recent files</h2>
                <a href="{{ route('recent') }}" class="text-sm text-muted">View all</a>
            </div>
            <div class="mb-16">
                <x-stellar.recent-table :files="$recent" />
            </div>
        </div>
    </main>

    <x-stellar.footer />
</x-stellar-layout>
