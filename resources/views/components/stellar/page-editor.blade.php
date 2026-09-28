{{-- Click-to-place editor for a "placements" option; behaviour in resources/js/tools/page-editor.js. --}}
@props(['name', 'option', 'workspace', 'file', 'count'])

@php
    $large = route('workspace.page', [$workspace, $file['id'], '__PAGE__', 'large']);
    $thumb = route('workspace.page', [$workspace, $file['id'], '__PAGE__', 'thumb']);
    $kinds = $option['kinds'];
    $icons = [
        'text' => 'M5 5h14M12 5v14M9 19h6',
        'rect' => 'M4 5h16v14H4z',
        'highlight' => 'M4 20h16M7 16l9-9 3 3-9 9H7z',
        'note' => 'M5 4h14v11l-5 5H5zM14 20v-5h5',
        'redact' => 'M4 8h16v8H4z',
        'image' => 'M4 5h16v14H4zM4 16l5-5 4 4 3-3 4 4M15 9h.01',
        'signature' => App\Support\ToolCatalog::ICONS['sign'],
        'field-text' => 'M3 7h18v10H3zM7 11v2',
        'field-checkbox' => 'M5 5h14v14H5zM8.5 12l2.5 2.5 5-5',
    ];
    $small = 'flex h-9 items-center gap-1.5 rounded-[10px] border border-line bg-white px-3 text-sm font-medium text-ink hover:border-ink';
@endphp

<section
    wire:ignore
    wire:key="editor-{{ $name }}-{{ $file['id'] }}"
    x-data="pageEditor({ count: {{ $count }}, large: @js($large), kinds: @js($kinds), value: $wire.entangle('options.{{ $name }}') })"
    x-on:keydown.window="if (($event.key === 'Delete' || $event.key === 'Backspace') && selected && !['INPUT', 'TEXTAREA'].includes($event.target.tagName)) remove()"
    class="flex flex-col gap-4"
    aria-label="{{ $option['label'] }}"
>
    <div class="flex flex-col gap-0.5">
        <h2 class="m-0 font-display text-[22px] font-bold">{{ $option['label'] }}</h2>
        <p class="m-0 text-[15px] text-muted">{{ $option['hint'] ?? 'Pick an item, then click the page to place it or drag to draw its size.' }}</p>
    </div>

    <input type="file" accept="image/png,image/jpeg" class="sr-only" x-ref="imageInput" x-on:change="imagePicked($event)" tabindex="-1">

    {{-- Toolbar --}}
    <div role="toolbar" aria-label="Add to page" class="flex flex-wrap gap-2">
        @foreach ($kinds as $kind)
            <button
                type="button"
                x-on:click="choose(@js($kind))"
                x-bind:aria-pressed="tool === @js($kind)"
                x-bind:class="tool === @js($kind) ? 'border-ink bg-ink text-white' : 'border-line-strong bg-white text-ink hover:border-ink'"
                class="flex h-10 items-center gap-2 rounded-full border px-4 text-sm font-medium"
            >
                <x-stellar.icon :d="$icons[$kind]" :size="16" />
                <span x-text="labels[@js($kind)]"></span>
            </button>
        @endforeach
    </div>

    <p class="m-0 text-sm text-muted" x-show="(tool === 'image' || tool === 'signature') && pendingAsset" x-cloak>
        Click the page to place it. Pick <span x-text="labels[tool]"></span> again to use a different one.
    </p>

    {{-- Selected item properties --}}
    <div class="flex min-h-[44px] flex-wrap items-center gap-3 rounded-xl bg-white px-3 py-2" x-show="current" x-cloak>
        <span class="text-sm font-semibold" x-text="current && labels[current.kind]"></span>
        <template x-if="current && ['text', 'note'].includes(current.kind)">
            <input type="text" x-model="current.text" x-on:change="sync()" x-on:input.debounce.400ms="sync()" maxlength="2000" aria-label="Text"
                class="h-9 min-w-0 flex-grow rounded-lg border-line-strong px-3 text-sm focus:border-line-strong focus:ring-accent">
        </template>
        <template x-if="current && current.kind.startsWith('field-')">
            <label class="flex items-center gap-2 text-sm">Field name
                <input type="text" x-model="current.name" x-on:input.debounce.400ms="sync()" maxlength="100" class="h-9 w-40 rounded-lg border-line-strong px-3 text-sm focus:border-line-strong focus:ring-accent">
            </label>
        </template>
        <template x-if="current && current.kind === 'text'">
            <label class="flex items-center gap-2 text-sm">Size
                <input type="number" min="4" max="200" x-model.number="current.size" x-on:change="sync()" class="h-9 w-20 rounded-lg border-line-strong px-2 text-sm focus:border-line-strong focus:ring-accent">
            </label>
        </template>
        <template x-if="current && current.color !== undefined">
            <input type="color" x-model="current.color" x-on:change="sync()" aria-label="Color" class="h-9 w-12 cursor-pointer rounded-lg border border-line-strong bg-white p-0.5">
        </template>
        <button type="button" class="{{ $small }} ml-auto" x-on:click="remove()">
            <x-stellar.icon d="M6 6l12 12M18 6L6 18" :size="14" /> Delete
        </button>
    </div>

    <div class="flex flex-col gap-4 xl:flex-row">
        {{-- Page thumbnails --}}
        <ol class="m-0 flex max-h-[80vh] list-none gap-2 overflow-auto p-0 xl:w-28 xl:shrink-0 xl:flex-col">
            <template x-for="p in count" x-bind:key="p">
                <li class="shrink-0">
                    <button type="button" x-on:click="go(p)" x-bind:aria-current="page === p ? 'page' : null" x-bind:aria-label="'Page ' + p"
                        x-bind:class="page === p ? 'border-ink ring-2 ring-ink' : 'border-line hover:border-ink'"
                        class="relative block w-20 overflow-hidden rounded-lg border bg-white xl:w-full">
                        <img x-bind:src="@js($thumb).replace('__PAGE__', p)" alt="" loading="lazy" class="w-full">
                        <span class="absolute bottom-1 left-1 rounded bg-ink px-1 font-mono text-[10px] text-white" x-text="p"></span>
                        <span x-show="countOn(p)" class="absolute right-1 top-1 h-2.5 w-2.5 rounded-full bg-accent"></span>
                    </button>
                </li>
            </template>
        </ol>

        {{-- The page --}}
        <div class="flex min-w-0 flex-grow flex-col items-center gap-3">
            <div
                x-ref="surface"
                class="relative w-full max-w-[760px] touch-none select-none overflow-hidden rounded-md bg-white shadow-[0_2px_12px_rgba(23,24,28,0.14)]"
                x-bind:class="tool ? 'cursor-crosshair' : ''"
                x-on:pointerdown="startDraw($event)"
                x-on:pointermove="pointerMove($event)"
                x-on:pointerup="pointerUp()"
                x-on:pointercancel="pointerUp()"
            >
                <img x-ref="page" x-bind:src="src(page)" alt="" draggable="false" class="block w-full" x-on:load="loaded($event)">

                <template x-for="item in pageItems" x-bind:key="item._id">
                    <div
                        class="absolute cursor-move"
                        x-bind:style="style(item)"
                        x-on:pointerdown="startMove($event, item)"
                        x-bind:class="selected === item._id ? 'outline outline-2 outline-offset-1 outline-accent' : ''"
                    >
                        <template x-if="item.kind === 'text'">
                            <div class="h-full w-full overflow-hidden whitespace-pre-wrap leading-tight" x-bind:style="`color:${item.color};font-size:${item.size * ($refs.page.clientWidth / 612)}px`" x-text="item.text"></div>
                        </template>
                        <template x-if="item.kind === 'rect'">
                            <div class="h-full w-full border-2" x-bind:style="`border-color:${item.color}`"></div>
                        </template>
                        <template x-if="item.kind === 'highlight'">
                            <div class="h-full w-full opacity-40" x-bind:style="`background:${item.color}`"></div>
                        </template>
                        <template x-if="item.kind === 'note'">
                            <div class="flex h-full w-full items-center justify-center rounded-sm shadow" x-bind:style="`background:${item.color}`" x-bind:title="item.text">
                                <x-stellar.icon :d="$icons['note']" :size="12" />
                            </div>
                        </template>
                        <template x-if="item.kind === 'redact'">
                            <div class="h-full w-full bg-ink/85"></div>
                        </template>
                        <template x-if="item.kind === 'image' || item.kind === 'signature'">
                            <img x-bind:src="previews[item.asset]" alt="" draggable="false" class="h-full w-full object-contain">
                        </template>
                        <template x-if="item.kind.startsWith('field-')">
                            <div class="flex h-full w-full items-center overflow-hidden border border-dashed border-accent bg-accent-soft/60 px-1 font-mono text-[10px] text-accent" x-text="item.kind === 'field-text' ? item.name : ''"></div>
                        </template>
                        <span
                            x-show="selected === item._id"
                            x-on:pointerdown="startResize($event, item)"
                            class="absolute -bottom-1.5 -right-1.5 h-3 w-3 cursor-nwse-resize rounded-sm border-2 border-white bg-accent"
                            aria-hidden="true"
                        ></span>
                    </div>
                </template>

                <div x-show="drawBox" class="pointer-events-none absolute border-2 border-dashed border-accent bg-accent-soft/40" x-bind:style="drawBox"></div>
            </div>

            <div class="flex items-center gap-3">
                <button type="button" class="{{ $small }}" x-on:click="go(page - 1)" x-bind:disabled="page === 1">
                    <x-stellar.icon d="M15 18l-6-6 6-6" :size="14" /> Previous
                </button>
                <span class="font-mono text-sm text-muted">Page <span x-text="page"></span> of {{ $count }}</span>
                <button type="button" class="{{ $small }}" x-on:click="go(page + 1)" x-bind:disabled="page === count">
                    Next <x-stellar.icon d="M9 6l6 6-6 6" :size="14" />
                </button>
            </div>
            <p class="m-0 text-sm text-muted"><span x-text="items.length"></span> item(s) placed</p>
        </div>
    </div>

    @if (in_array('signature', $kinds, true))
        {{-- Signature dialog --}}
        <div x-show="signing" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-ink/50 p-4" x-on:keydown.escape.window="signing = false">
            <div
                role="dialog" aria-modal="true" aria-labelledby="sig-title"
                class="flex w-full max-w-[560px] flex-col gap-5 rounded-[20px] bg-white p-6"
                x-data="signaturePad({ done: (dataUrl) => useAsset(dataUrl) })"
            >
                {{-- Loaded here because the editor only appears after upload, too late for the layout's <head>. --}}
                <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Caveat:wght@500&family=Dancing+Script:wght@500&family=Homemade+Apple&family=Sacramento&display=swap">
                <div class="flex items-center justify-between">
                    <h3 id="sig-title" class="m-0 font-display text-[22px] font-bold">Create your signature</h3>
                    <button type="button" x-on:click="signing = false" aria-label="Close" class="flex h-9 w-9 items-center justify-center rounded-lg border border-line hover:border-ink">
                        <x-stellar.icon d="M6 6l12 12M18 6L6 18" :size="16" />
                    </button>
                </div>

                <div role="tablist" class="flex gap-2">
                    @foreach (['draw' => 'Draw', 'type' => 'Type', 'upload' => 'Upload'] as $tab => $label)
                        <button type="button" role="tab" x-on:click="tab = @js($tab)" x-bind:aria-selected="tab === @js($tab)"
                            x-bind:class="tab === @js($tab) ? 'bg-ink text-white border-ink' : 'bg-white text-ink border-line-strong'"
                            class="h-10 rounded-full border px-4 text-sm font-medium">{{ $label }}</button>
                    @endforeach
                </div>

                <div x-show="tab === 'draw'" class="flex flex-col gap-2">
                    <canvas x-ref="canvas" width="1000" height="300"
                        x-on:pointerdown="down($event)" x-on:pointermove="move($event)" x-on:pointerup="up()" x-on:pointercancel="up()"
                        class="aspect-[10/3] w-full touch-none rounded-xl border border-dashed border-line-dashed bg-paper"></canvas>
                    <div class="flex items-center justify-between">
                        <input type="color" x-model="color" aria-label="Ink color" class="h-9 w-12 cursor-pointer rounded-lg border border-line-strong bg-white p-0.5">
                        <button type="button" class="text-sm text-muted underline" x-on:click="clear()">Clear</button>
                    </div>
                </div>

                <div x-show="tab === 'type'" x-cloak class="flex flex-col gap-3">
                    <input type="text" x-model="typed" maxlength="60" placeholder="Your name" aria-label="Your name"
                        class="h-12 rounded-xl border-line-strong px-3.5 text-[15px] focus:border-line-strong focus:ring-accent">
                    <div class="grid grid-cols-2 gap-2">
                        @foreach (['Caveat', 'Dancing Script', 'Homemade Apple', 'Sacramento'] as $font)
                            <button type="button" x-on:click="font = @js($font)" x-bind:class="font === @js($font) ? 'border-ink' : 'border-line'"
                                class="h-16 overflow-hidden rounded-xl border bg-paper px-3 text-2xl" style="font-family: '{{ $font }}'" x-text="typed || 'Your name'"></button>
                        @endforeach
                    </div>
                </div>

                <div x-show="tab === 'upload'" x-cloak class="flex flex-col gap-2">
                    <input type="file" accept="image/png,image/jpeg" x-on:change="upload($event)" class="text-sm">
                    <span class="text-[13px] text-muted">A PNG with a transparent background looks best.</span>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" class="h-11 rounded-xl border border-line-strong px-4 text-[15px] font-medium" x-on:click="signing = false">Cancel</button>
                    <button type="button" x-show="tab !== 'upload'" class="h-11 rounded-xl bg-accent px-5 text-[15px] font-semibold text-white hover:bg-accent-dark" x-on:click="save()">Use signature</button>
                </div>
            </div>
        </div>
    @endif
</section>

@error("options.{$name}")
    <span class="text-sm text-red-700">{{ $message }}</span>
@enderror
@error('assets')
    <span class="text-sm text-red-700">{{ $message }}</span>
@enderror
