{{-- Sticky top bar: primary tools, grouped menus, search palette (Ctrl K), recent files. --}}
@props(['current' => null])

@php
    use App\Support\RecentFiles;
    use App\Support\ToolCatalog;

    $categories = collect(ToolCatalog::categories())->keyBy('id');
    $currentCategory = $current ? ToolCatalog::find($current)[1]['id'] ?? null : null;
    $direct = ['merge-pdf' => 'Merge', 'split-pdf' => 'Split', 'compress-pdf' => 'Compress'];
    $after = ['sign-pdf' => 'Sign', 'protect-pdf' => 'Protect'];
    $menus = ['convert' => ['Convert', ['to', 'from']], 'edit' => ['Edit', ['edit', 'organize']]];
    $inMenu = fn (array $ids) => $currentCategory && in_array($currentCategory, $ids, true) && ! isset($direct[$current]) && ! isset($after[$current]);
    $link = 'flex h-full items-center gap-1.5 whitespace-nowrap border-b-2 px-3 -mb-px text-[14.5px] font-semibold hover:text-accent';
    $initials = auth()->check()
        ? Str::of(auth()->user()->name)->explode(' ')->map(fn ($part) => Str::substr($part, 0, 1))->take(2)->implode('')
        : null;
    $toolUrl = fn ($tool) => $tool['url'] ?? route('home');
@endphp

<header
    class="sticky top-0 z-50 border-b border-line bg-white"
    x-data="{ menu: null, drawer: false }"
    x-on:keydown.escape.window="menu = null; drawer = false"
    x-on:click.outside="menu = null"
>
    <div class="mx-auto flex h-16 max-w-[1320px] items-center gap-7 px-[18px] md:px-10">
        <button type="button" class="flex h-10 w-10 items-center justify-center rounded-[10px] border border-line-strong bg-white hover:border-ink lg:hidden"
            x-on:click="drawer = ! drawer" x-bind:aria-expanded="drawer" aria-label="Open menu">
            <x-stellar.icon d="M4 7h16M4 12h16M4 17h16" :size="20" />
        </button>

        <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-[9px] text-[19px] font-extrabold tracking-[-0.02em] text-ink no-underline" aria-label="PDF Studio home">
            <svg width="28" height="28" viewBox="0 0 28 28" aria-hidden="true"><rect width="28" height="28" rx="8" fill="#C8322B"/><path d="M9 6.5h7l4 4v11H9z" fill="none" stroke="#fff" stroke-width="1.8" stroke-linejoin="round"/><path d="M16 6.5v4h4" fill="none" stroke="#fff" stroke-width="1.8" stroke-linejoin="round"/></svg>
            <span>PDF<b class="text-accent">Studio</b></span>
        </a>

        <ul class="m-0 hidden h-full list-none items-stretch gap-0.5 p-0 lg:flex">
            @foreach ($direct as $slug => $label)
                <li class="flex">
                    <a href="{{ $slug === 'merge-pdf' ? route('tools.merge') : route('tools.show', $slug) }}" @class([$link, 'border-accent text-accent' => $current === $slug, 'border-transparent text-ink' => $current !== $slug]) @if ($current === $slug) aria-current="page" @endif>{{ $label }}</a>
                </li>
            @endforeach

            @foreach ($menus as $key => [$label, $ids])
                <li class="relative flex">
                    <button type="button" x-on:click="menu = menu === '{{ $key }}' ? null : '{{ $key }}'" x-bind:aria-expanded="menu === '{{ $key }}'" aria-haspopup="true"
                        @class([$link, 'border-accent text-accent' => $inMenu($ids), 'border-transparent text-ink' => ! $inMenu($ids)])
                        x-bind:class="menu === '{{ $key }}' && 'text-accent'">
                        {{ $label }}
                        <x-stellar.icon d="M6 9l6 6 6-6" :size="14" class="transition-transform" x-bind:class="menu === '{{ $key }}' && 'rotate-180'" />
                    </button>
                    <div x-show="menu === '{{ $key }}'" x-cloak x-transition.opacity.duration.100ms
                        class="absolute left-0 top-[calc(100%+1px)] z-[60] rounded-b-[18px] border border-line bg-white p-[22px] shadow-[0_18px_40px_rgba(27,29,34,0.12)]">
                        <div class="grid grid-cols-[repeat(2,minmax(210px,1fr))] gap-7">
                            @foreach ($ids as $id)
                                <div>
                                    <h4 class="m-0 mb-2.5 text-[12.5px] font-bold tracking-[0.02em] text-muted-light">{{ $categories[$id]['name'] }}</h4>
                                    @foreach ($categories[$id]['tools'] as $tool)
                                        <a href="{{ $toolUrl($tool) }}" class="-mx-2 flex items-center gap-2.5 whitespace-nowrap rounded-lg px-2 py-[7px] text-[14.5px] font-medium text-ink no-underline hover:bg-sand">
                                            <x-stellar.tile :color="$categories[$id]['color']" :tint="$categories[$id]['tint']" :icon="$tool['icon']" :size="28" :icon-size="16" />
                                            {{ $tool['name'] }}
                                        </a>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </li>
            @endforeach

            @foreach ($after as $slug => $label)
                <li class="flex">
                    <a href="{{ route('tools.show', $slug) }}" @class([$link, 'border-accent text-accent' => $current === $slug, 'border-transparent text-ink' => $current !== $slug]) @if ($current === $slug) aria-current="page" @endif>{{ $label }}</a>
                </li>
            @endforeach

            <li class="flex">
                <button type="button" x-on:click="menu = menu === 'all' ? null : 'all'" x-bind:aria-expanded="menu === 'all'" aria-haspopup="true"
                    @class([$link, 'border-transparent text-ink'])
                    x-bind:class="menu === 'all' && 'text-accent'">
                    All tools
                    <x-stellar.icon d="M6 9l6 6 6-6" :size="14" class="transition-transform" x-bind:class="menu === 'all' && 'rotate-180'" />
                </button>
            </li>
        </ul>

        <div class="ml-auto flex items-center gap-2.5">
            <button type="button" x-on:click="$dispatch('open-palette')"
                class="flex h-10 items-center gap-2.5 rounded-[10px] border border-line-strong bg-paper pl-3 pr-2.5 text-sm text-muted hover:border-ink xl:min-w-[220px]">
                <x-stellar.icon d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-4-4" :size="18" />
                <span class="sr-only xl:not-sr-only">Search tools</span>
                <kbd class="ml-auto hidden rounded-[5px] border border-line-strong bg-white px-1.5 py-0.5 font-body text-xs text-muted xl:inline" aria-hidden="true">Ctrl K</kbd>
            </button>
            <a href="{{ route('recent') }}" title="Recent files"
                class="relative hidden h-10 w-10 items-center justify-center rounded-[10px] border border-line-strong bg-white text-ink hover:border-ink lg:flex">
                <x-stellar.icon d="M12 7v5l3 2M21 12a9 9 0 1 1-3-6.7M21 4v4h-4" :size="19" />
                <span class="sr-only">Recent files</span>
                @if (RecentFiles::unseen())
                    <i class="absolute right-[7px] top-[7px] h-2 w-2 rounded-full border-2 border-white bg-accent" aria-hidden="true"></i>
                    <span class="sr-only">(new)</span>
                @endif
            </a>
            @if ($initials)
                <a href="{{ route('profile.show') }}" aria-label="Account" class="hidden h-10 w-10 items-center justify-center rounded-full bg-ink text-sm font-bold text-white no-underline lg:flex">{{ $initials }}</a>
            @else
                <a href="{{ route('login') }}" class="hidden h-10 items-center rounded-full bg-ink px-4 text-sm font-bold text-white no-underline lg:flex">Log in</a>
            @endif
        </div>
    </div>

    {{-- All tools --}}
    <div x-show="menu === 'all'" x-cloak x-transition.opacity.duration.100ms
        class="fixed inset-x-0 top-16 z-[60] border-y border-line bg-white pb-8 pt-7 shadow-[0_18px_40px_rgba(27,29,34,0.12)]">
        <div class="mx-auto max-w-[1320px] px-[18px] md:px-10">
            <div class="grid grid-cols-[repeat(auto-fill,minmax(170px,1fr))] gap-7">
                @foreach ($categories as $category)
                    <div>
                        <h4 class="m-0 mb-2.5 text-[12.5px] font-bold tracking-[0.02em]" style="color: {{ $category['color'] }}">{{ $category['name'] }}</h4>
                        @foreach ($category['tools'] as $tool)
                            <a href="{{ $toolUrl($tool) }}" class="-mx-2 flex items-center gap-2.5 whitespace-nowrap rounded-lg px-2 py-[7px] text-[14.5px] font-medium text-ink no-underline hover:bg-sand">
                                <x-stellar.tile :color="$category['color']" :tint="$category['tint']" :icon="$tool['icon']" :size="28" :icon-size="16" />
                                {{ $tool['name'] }}
                            </a>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <div class="mt-[22px] flex items-center justify-between border-t border-line pt-[18px] text-sm text-muted">
                <span>{{ ToolCatalog::toolCount() }} tools in {{ count($categories) }} categories</span>
                <a href="{{ route('home') }}#all" class="font-semibold text-accent no-underline">Browse all tools on the home page</a>
            </div>
        </div>
    </div>

    {{-- Mobile drawer --}}
    <nav x-show="drawer" x-cloak aria-label="All tools" class="fixed inset-x-0 bottom-0 top-16 z-[55] overflow-auto bg-white px-[18px] pb-10 pt-5 lg:hidden">
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 border-b border-sand py-2.5 font-medium text-ink no-underline">
            <x-stellar.icon d="M3 11l9-7 9 7v9H3z" :size="20" /> Home
        </a>
        <a href="{{ route('recent') }}" class="flex items-center gap-2.5 border-b border-sand py-2.5 font-medium text-ink no-underline">
            <x-stellar.icon d="M12 7v5l3 2M21 12a9 9 0 1 1-3-6.7M21 4v4h-4" :size="20" /> Recent files
        </a>
        @foreach ($categories as $category)
            <h4 class="m-0 mb-2 mt-[22px] text-[13px] text-muted-light">{{ $category['name'] }}</h4>
            @foreach ($category['tools'] as $tool)
                <a href="{{ $toolUrl($tool) }}" class="flex items-center gap-2.5 border-b border-sand py-2.5 font-medium text-ink no-underline">
                    <x-stellar.tile :color="$category['color']" :tint="$category['tint']" :icon="$tool['icon']" :size="28" :icon-size="16" />
                    {{ $tool['name'] }}
                </a>
            @endforeach
        @endforeach
    </nav>
</header>

<x-stellar.palette />
