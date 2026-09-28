{{-- Tinted square with a tool icon. $icon is an SVG path; sizes are in px. --}}
@props(['color', 'tint', 'icon', 'size' => 40, 'iconSize' => 20])

<span {{ $attributes->merge(['class' => 'flex shrink-0 items-center justify-center']) }} style="width: {{ $size }}px; height: {{ $size }}px; border-radius: {{ round($size / 4) }}px; background: {{ $tint }}" aria-hidden="true">
    <x-stellar.icon :d="$icon" :size="$iconSize" :stroke="$color" />
</span>
