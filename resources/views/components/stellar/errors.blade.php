@props(['messages'])

@if (collect($messages)->isNotEmpty())
    <ul {{ $attributes->merge(['class' => 'm-0 list-none rounded-xl bg-red-50 px-4 py-3 text-sm text-red-800']) }} role="alert">
        @foreach ($messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
