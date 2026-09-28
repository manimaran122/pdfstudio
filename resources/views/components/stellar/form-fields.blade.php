{{-- Inputs for the uploaded PDF's own form fields ("form" option). Values are keyed by field position. --}}
@props(['name', 'option', 'fields'])

@php
    $input = 'rounded-xl border-line-strong text-[15px] text-ink focus:border-line-strong focus:outline focus:outline-2 focus:outline-offset-2 focus:outline-accent focus:ring-0';
@endphp

<section class="flex flex-col gap-4" aria-label="{{ $option['label'] }}">
    <div class="flex flex-col gap-0.5">
        <h2 class="m-0 font-display text-[22px] font-bold">{{ $option['label'] }}</h2>
        <p class="m-0 text-[15px] text-muted">
            @if ($fields)
                {{ count($fields) }} {{ Str::plural('field', count($fields)) }} found in this PDF.
            @else
                This PDF has no fillable fields. Add fields on the page below, then fill them in with this tool again.
            @endif
        </p>
    </div>

    @if ($fields)
        <div class="grid gap-4 rounded-[18px] border border-line bg-white p-5 md:grid-cols-2">
            @foreach ($fields as $i => $field)
                @php($model = "options.{$name}.{$i}")
                <div class="flex flex-col gap-1.5" wire:key="field-{{ $i }}">
                    @if ($field['type'] === 'checkbox')
                        <label class="flex cursor-pointer items-center gap-3 text-[15px]">
                            <input type="checkbox" wire:model="{{ $model }}" class="h-[18px] w-[18px] rounded border-line-strong text-ink focus:ring-accent">
                            {{ $field['label'] }}
                        </label>
                    @else
                        <label for="field-{{ $i }}" class="text-sm font-semibold">{{ $field['label'] }} <span class="font-mono text-xs font-normal text-muted">p. {{ $field['page'] }}</span></label>
                        @if (in_array($field['type'], ['combobox', 'listbox', 'radiobutton'], true) && $field['options'])
                            <select id="field-{{ $i }}" wire:model="{{ $model }}" class="h-11 {{ $input }}">
                                <option value="">—</option>
                                @foreach ($field['options'] as $choice)
                                    @php($choice = is_array($choice) ? $choice[0] : $choice)
                                    <option value="{{ $choice }}">{{ $choice }}</option>
                                @endforeach
                            </select>
                        @else
                            <input id="field-{{ $i }}" type="text" wire:model="{{ $model }}" class="h-11 px-3.5 {{ $input }}">
                        @endif
                    @endif
                    @error($model) <span class="text-sm text-red-700">{{ $message }}</span> @enderror
                </div>
            @endforeach
        </div>
    @endif
</section>
