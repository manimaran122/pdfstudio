@props(['d', 'size' => 22, 'stroke' => 'currentColor', 'width' => 1.8])

<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="{{ $stroke }}" stroke-width="{{ $width }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}><path d="{{ $d }}"/></svg>
