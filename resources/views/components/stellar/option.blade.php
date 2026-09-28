{{-- One tool option in the options panel (types documented in App\Support\PdfTools). --}}
@props(['name', 'option'])

@php
    $model = "options.{$name}";
    $id = 'opt-'.$name;
    $input = 'rounded-[10px] border-line-strong bg-white text-[15px] text-ink focus:border-line-strong focus:ring-0';
@endphp

@switch($option['type'])
    @case('choice')
        <fieldset class="m-0 flex flex-col gap-2 border-none p-0">
            <legend class="mb-[7px] text-[13.5px] font-bold">{{ $option['label'] }}</legend>
            @foreach ($option['choices'] as $value => [$label, $hint])
                <label class="flex cursor-pointer items-start gap-[11px] rounded-[10px] border border-line-strong px-[13px] py-3 text-[14.5px] has-[:checked]:border-ink has-[:checked]:bg-[#FBFBFA]">
                    <input type="radio" name="{{ $name }}" value="{{ $value }}" wire:model="{{ $model }}" class="mt-[3px] h-[17px] w-[17px] border-line-strong text-ink focus:ring-focus">
                    <span class="flex flex-col gap-0.5">
                        <span class="font-medium">{{ $label }}</span>
                        @if ($hint)
                            <span class="text-[12.5px] text-muted">{{ $hint }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </fieldset>
        @break

    @case('toggle')
        <label class="flex cursor-pointer items-start gap-[11px] text-[14.5px]">
            <input type="checkbox" wire:model="{{ $model }}" class="mt-[3px] h-[17px] w-[17px] rounded border-line-strong text-ink focus:ring-focus">
            <span class="flex flex-col gap-0.5">
                <span class="font-medium">{{ $option['label'] }}</span>
                @isset($option['hint'])
                    <span class="text-[12.5px] text-muted">{{ $option['hint'] }}</span>
                @endisset
            </span>
        </label>
        @break

    @case('image')
        <div class="flex flex-col gap-2" x-data="imageOption({ value: $wire.entangle('{{ $model }}') })">
            <span class="text-[13.5px] font-bold">{{ $option['label'] }}</span>
            <input type="file" accept="image/png,image/jpeg" class="sr-only" x-ref="file" x-on:change="picked($event)" tabindex="-1">
            <div class="flex items-center gap-3">
                <template x-if="preview">
                    <img x-bind:src="preview" alt="" class="h-14 w-14 rounded-lg border border-line bg-paper object-contain">
                </template>
                <button type="button" x-on:click="$refs.file.click()" class="inline-flex h-[42px] items-center gap-2 rounded-[10px] border border-line-strong bg-white px-[15px] text-[14.5px] font-semibold text-ink hover:border-ink">
                    <span x-text="busy ? 'Uploading…' : (value ? 'Replace image' : 'Choose image')"></span>
                </button>
                <button type="button" x-show="value" x-on:click="clear()" class="text-sm text-muted underline">Remove</button>
            </div>
            @isset($option['hint'])
                <span class="text-[12.5px] text-muted">{{ $option['hint'] }}</span>
            @endisset
        </div>
        @break

    @default
        <div class="flex flex-col gap-[7px]">
            <label for="{{ $id }}" class="flex items-baseline justify-between gap-2 text-[13.5px] font-bold">
                {{ $option['label'] }}
                @if ($option['type'] === 'range')
                    <span class="font-mono text-xs font-medium text-muted" x-text="$wire.options['{{ $name }}'] + '{{ $option['unit'] ?? '' }}'"></span>
                @endif
            </label>

            @switch($option['type'])
                @case('select')
                    <select id="{{ $id }}" wire:model="{{ $model }}" class="h-[46px] {{ $input }}">
                        @foreach ($option['choices'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @break
                @case('textarea')
                    <textarea id="{{ $id }}" wire:model="{{ $model }}" rows="{{ $option['rows'] ?? 4 }}" maxlength="{{ $option['max'] ?? 2000 }}" placeholder="{{ $option['placeholder'] ?? '' }}" class="px-[13px] py-3 {{ $input }}"></textarea>
                    @break
                @case('range')
                    <input id="{{ $id }}" type="range" wire:model="{{ $model }}" min="{{ $option['min'] ?? 0 }}" max="{{ $option['max'] ?? 100 }}" step="{{ $option['step'] ?? 1 }}" class="accent-ink">
                    @break
                @case('color')
                    <input id="{{ $id }}" type="color" wire:model="{{ $model }}" class="h-11 w-20 cursor-pointer rounded-xl border border-line-strong bg-white p-1">
                    @break
                @case('number')
                    <div class="flex items-center gap-2">
                        <input id="{{ $id }}" type="number" wire:model="{{ $model }}" min="{{ $option['min'] ?? 0 }}" max="{{ $option['max'] ?? 10000 }}" step="{{ $option['step'] ?? 1 }}" class="h-[46px] w-32 px-[13px] {{ $input }}">
                        @isset($option['unit'])
                            <span class="text-sm text-muted">{{ $option['unit'] }}</span>
                        @endisset
                    </div>
                    @break
                @default
                    <input
                        id="{{ $id }}"
                        type="{{ $option['type'] === 'password' ? 'password' : 'text' }}"
                        wire:model="{{ $model }}"
                        maxlength="{{ $option['max'] ?? 200 }}"
                        placeholder="{{ $option['placeholder'] ?? '' }}"
                        @if ($option['type'] === 'password') autocomplete="new-password" @endif
                        class="h-[46px] px-[13px] {{ $input }}"
                    >
            @endswitch

            @isset($option['hint'])
                <span class="text-[12.5px] text-muted">{{ $option['hint'] }}</span>
            @endisset
        </div>
@endswitch

@error($model)
    <span class="mt-1 block text-[12.5px] font-semibold text-accent">{{ $message }}</span>
@enderror
