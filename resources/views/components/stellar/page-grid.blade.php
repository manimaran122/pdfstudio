{{-- Page grid for a "pages" option; behaviour in resources/js/tools/page-grid.js. --}}
@props(['name', 'option', 'workspace', 'file', 'count'])

@php
    $thumb = route('workspace.page', [$workspace, $file['id'], '__PAGE__', 'thumb']);
    $mode = $option['mode'];
    $button = 'flex h-9 items-center rounded-[10px] border border-line bg-white px-3 text-sm font-medium text-ink hover:border-ink';
    $icon = 'flex h-8 w-8 items-center justify-center rounded-lg border border-line bg-white hover:border-ink disabled:opacity-40';
@endphp

<section
    wire:ignore
    wire:key="grid-{{ $name }}-{{ $file['id'] }}"
    x-data="pageGrid({ mode: @js($mode), count: {{ $count }}, thumb: @js($thumb), value: $wire.entangle('options.{{ $name }}') })"
    class="flex flex-col gap-4"
    aria-label="{{ $option['label'] }}"
>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-col gap-0.5">
            <h2 class="m-0 font-display text-[22px] font-bold">{{ $option['label'] }}</h2>
            @isset($option['hint'])
                <p class="m-0 text-[15px] text-muted">{{ $option['hint'] }}</p>
            @endisset
        </div>

        <div class="flex flex-wrap gap-2">
            @if ($mode === 'select')
                <button type="button" class="{{ $button }}" x-on:click="pick('all')">All</button>
                <button type="button" class="{{ $button }}" x-on:click="pick('none')">None</button>
                <button type="button" class="{{ $button }}" x-on:click="pick('odd')">Odd</button>
                <button type="button" class="{{ $button }}" x-on:click="pick('even')">Even</button>
            @elseif ($mode === 'rotate')
                <button type="button" class="{{ $button }} gap-1.5" x-on:click="turnAll(-90)">
                    <x-stellar.icon d="M4 12a8 8 0 1 0 2.3-5.7M4 4v5h5" :size="16" /> All left
                </button>
                <button type="button" class="{{ $button }} gap-1.5" x-on:click="turnAll(90)">
                    <x-stellar.icon :d="App\Support\ToolCatalog::ICONS['rotate']" :size="16" /> All right
                </button>
            @else
                <button type="button" class="{{ $button }}" x-on:click="reset()">Reset</button>
            @endif
        </div>
    </div>

    @if ($mode === 'select')
        <label class="flex flex-col gap-1.5 text-sm font-semibold">
            Pages
            <input type="text" x-model.lazy="rangeText" placeholder="e.g. 1-3, 7, 10-12"
                class="h-11 max-w-md rounded-xl border-line-strong px-3.5 text-[15px] font-normal focus:border-line-strong focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-accent focus:ring-0">
            <span class="text-[13px] font-normal text-muted"><span x-text="value.length"></span> of {{ $count }} selected · shift-click to select a range</span>
        </label>
    @endif

    <ol class="m-0 grid list-none grid-cols-2 gap-4 p-0 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
        @if ($mode === 'organize')
            <template x-for="(item, index) in items" x-bind:key="item.key">
                <li
                    draggable="true"
                    x-on:dragstart="dragging = index; $event.dataTransfer.effectAllowed = 'move'"
                    x-on:dragend="dragging = over = null"
                    x-on:dragover.prevent="over = index"
                    x-on:drop.prevent="drop(index)"
                    x-bind:class="{ 'opacity-40': dragging === index, 'ring-2 ring-accent': over === index && dragging !== index }"
                    class="flex cursor-grab flex-col gap-2 rounded-2xl border border-line bg-white p-2.5 active:cursor-grabbing"
                >
                    <div class="relative flex aspect-[3/4] items-center justify-center overflow-hidden rounded-lg bg-sand">
                        <img x-bind:src="src(item.page)" alt="" loading="lazy" draggable="false"
                            class="max-h-full max-w-full shadow-[0_2px_8px_rgba(23,24,28,0.12)] transition-transform"
                            x-bind:style="`transform: rotate(${item.rotation}deg) scale(${item.rotation % 180 ? 0.75 : 1})`">
                        <span class="absolute left-2 top-2 flex h-6 min-w-6 items-center justify-center rounded-full bg-ink px-1.5 font-mono text-xs text-white" x-text="index + 1"></span>
                    </div>
                    <div class="flex items-center justify-between gap-1">
                        <span class="font-mono text-xs text-muted" x-text="'p. ' + item.page"></span>
                        <div class="flex gap-1">
                            <button type="button" class="{{ $icon }}" x-on:click="move(index, -1)" x-bind:disabled="index === 0" x-bind:aria-label="'Move page ' + (index + 1) + ' earlier'">
                                <x-stellar.icon d="M15 18l-6-6 6-6" :size="14" />
                            </button>
                            <button type="button" class="{{ $icon }}" x-on:click="move(index, 1)" x-bind:disabled="index === items.length - 1" x-bind:aria-label="'Move page ' + (index + 1) + ' later'">
                                <x-stellar.icon d="M9 6l6 6-6 6" :size="14" />
                            </button>
                            <button type="button" class="{{ $icon }}" x-on:click="rotateItem(index)" x-bind:aria-label="'Rotate page ' + (index + 1)">
                                <x-stellar.icon :d="App\Support\ToolCatalog::ICONS['rotate']" :size="14" />
                            </button>
                            <button type="button" class="{{ $icon }}" x-on:click="duplicate(index)" x-bind:aria-label="'Duplicate page ' + (index + 1)">
                                <x-stellar.icon d="M8 8h12v12H8zM4 16V4h12" :size="14" />
                            </button>
                            <button type="button" class="{{ $icon }}" x-on:click="discard(index)" x-bind:disabled="items.length === 1" x-bind:aria-label="'Delete page ' + (index + 1)">
                                <x-stellar.icon d="M6 6l12 12M18 6L6 18" :size="14" />
                            </button>
                        </div>
                    </div>
                </li>
            </template>
        @else
            <template x-for="page in pages()" x-bind:key="page">
                <li class="flex flex-col gap-2">
                    <button
                        type="button"
                        @if ($mode === 'select')
                            x-on:click="toggle(page, $event)"
                            x-bind:aria-pressed="selected(page)"
                            x-bind:class="selected(page) ? 'border-ink ring-2 ring-ink' : 'border-line hover:border-ink'"
                        @else
                            x-on:click="turn(page, 90)"
                            class="border-line hover:border-ink"
                        @endif
                        x-bind:aria-label="'Page ' + page"
                        class="relative flex aspect-[3/4] items-center justify-center overflow-hidden rounded-2xl border bg-sand p-2.5"
                    >
                        <img x-bind:src="src(page)" alt="" loading="lazy"
                            class="max-h-full max-w-full shadow-[0_2px_8px_rgba(23,24,28,0.12)] transition-transform"
                            @if ($mode === 'rotate') x-bind:style="`transform: rotate(${rotation(page)}deg) scale(${rotation(page) % 180 ? 0.75 : 1})`" @endif>
                        @if ($mode === 'select')
                            <span x-show="selected(page)" class="absolute right-2 top-2 flex h-7 w-7 items-center justify-center rounded-full bg-ink text-white">
                                <x-stellar.icon d="M5 12.5l4.5 4.5L19 7.5" :size="16" :width="2.4" />
                            </span>
                        @endif
                    </button>
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-xs text-muted" x-text="'Page ' + page"></span>
                        @if ($mode === 'rotate')
                            <div class="flex items-center gap-1">
                                <span class="font-mono text-xs text-muted" x-show="rotation(page)" x-text="rotation(page) + '°'"></span>
                                <button type="button" class="{{ $icon }}" x-on:click="turn(page, -90)" x-bind:aria-label="'Rotate page ' + page + ' left'">
                                    <x-stellar.icon d="M4 12a8 8 0 1 0 2.3-5.7M4 4v5h5" :size="14" />
                                </button>
                                <button type="button" class="{{ $icon }}" x-on:click="turn(page, 90)" x-bind:aria-label="'Rotate page ' + page + ' right'">
                                    <x-stellar.icon :d="App\Support\ToolCatalog::ICONS['rotate']" :size="14" />
                                </button>
                            </div>
                        @endif
                    </div>
                </li>
            </template>
        @endif
    </ol>
</section>

@error("options.{$name}")
    <span class="text-sm text-red-700">{{ $message }}</span>
@enderror
