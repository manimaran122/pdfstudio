{{-- Tool page header band: tile, breadcrumb, name, description and progress steps (0 add, 1 settings, 2 download). --}}
@props(['tool', 'category', 'step' => 0])

@php
    $steps = ['Add files', 'Settings', 'Download'];
@endphp

<div class="border-b border-line bg-white">
    <div class="mx-auto flex max-w-[1320px] flex-wrap items-center gap-[18px] px-[18px] py-[22px] md:px-10">
        <x-stellar.tile :color="$category['color']" :tint="$category['tint']" :icon="$tool['icon']" :size="52" :icon-size="26" class="!rounded-[14px]" />
        <div class="min-w-0">
            <nav aria-label="Breadcrumb" class="mb-1 flex flex-wrap gap-[7px] text-[13.5px] text-muted">
                <a href="{{ route('home') }}" class="text-muted">Home</a>
                <span aria-hidden="true">/</span>
                <span>{{ $category['name'] }}</span>
            </nav>
            <h1 class="m-0 text-[30px] font-extrabold tracking-[-0.02em]">{{ $tool['name'] }}</h1>
            <p class="m-0 mt-0.5 text-muted">{{ $tool['desc'] }}</p>
        </div>

        <ol aria-label="Progress" class="m-0 flex w-full list-none items-center gap-2 p-0 text-[13.5px] font-semibold text-muted-light md:ml-auto md:w-auto">
            @foreach ($steps as $i => $label)
                @if ($i)
                    <li class="h-[1.5px] w-[26px] bg-line-strong" aria-hidden="true"></li>
                @endif
                <li @class(['flex items-center gap-2', 'text-ink' => $i <= $step]) @if ($i === $step) aria-current="step" @endif>
                    <b @class([
                        'flex h-[26px] w-[26px] items-center justify-center rounded-full border-[1.5px] text-[12.5px]',
                        'border-accent-soft bg-accent-soft text-accent' => $i < $step,
                        'border-ink bg-ink text-white' => $i === $step,
                        'border-line-strong' => $i > $step,
                    ])>{{ $i < $step ? '✓' : $i + 1 }}</b>
                    {{ $label }}
                </li>
            @endforeach
        </ol>
    </div>
</div>
