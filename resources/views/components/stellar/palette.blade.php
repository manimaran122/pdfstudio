{{-- Ctrl K tool search; behaviour in resources/js/tools/palette.js. --}}
@php
    $tools = collect(App\Support\ToolCatalog::flat())
        ->map(fn ($tool) => [...\Illuminate\Support\Arr::only($tool, ['slug', 'name', 'desc', 'badge', 'icon', 'url', 'category', 'color', 'tint'])])
        ->values();
@endphp

<div x-data="palette({ tools: @js($tools) })" x-on:open-palette.window="show()">
    <div x-show="open" x-cloak class="fixed inset-0 z-[90] flex items-start justify-center bg-ink/45 px-4 pb-4 pt-[12vh]" x-on:click.self="close()" x-on:keydown.escape.window="close()">
        <div role="dialog" aria-modal="true" aria-label="Search tools" class="w-full max-w-[620px] overflow-hidden rounded-[18px] bg-white shadow-[0_30px_80px_rgba(0,0,0,0.25)]">
            <div class="flex items-center gap-2.5 border-b border-line px-[18px]">
                <x-stellar.icon d="M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14zM20 20l-4-4" :size="20" class="text-muted" />
                <label for="palette-q" class="sr-only">Search tools</label>
                <input id="palette-q" x-ref="input" x-model="q" x-on:input="selected = 0" autocomplete="off"
                    x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)" x-on:keydown.enter.prevent="go()"
                    role="combobox" aria-controls="palette-list" aria-expanded="true"
                    placeholder="Search tools, e.g. word, compress, password"
                    class="h-[58px] flex-1 border-none bg-transparent p-0 text-[17px] outline-none focus:ring-0">
            </div>
            <div id="palette-list" x-ref="list" role="listbox" class="max-h-[52vh] overflow-auto p-2">
                <template x-for="(tool, i) in results" x-bind:key="tool.slug">
                    <a x-bind:href="tool.url" role="option" x-bind:aria-selected="i === selected" x-on:mouseenter="selected = i"
                        class="flex items-center gap-3 rounded-[10px] px-3 py-2.5 text-ink no-underline aria-selected:bg-sand">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg" x-bind:style="`background:${tool.tint}`" aria-hidden="true">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" x-bind:stroke="tool.color"><path x-bind:d="tool.icon"/></svg>
                        </span>
                        <strong class="font-semibold" x-text="tool.name"></strong>
                        <span class="ml-auto text-[13px] text-muted" x-text="tool.category"></span>
                    </a>
                </template>
                <div x-show="! results.length" class="p-6 text-center text-muted">No tools match “<span x-text="q.trim()"></span>”.</div>
            </div>
            <div class="flex gap-4 border-t border-line px-[18px] py-2.5 text-[12.5px] text-muted-light">
                <span>↑ ↓ to move</span><span>Enter to open</span><span>Esc to close</span>
            </div>
        </div>
    </div>
</div>
