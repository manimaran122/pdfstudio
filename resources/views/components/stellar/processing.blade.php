{{-- Full-screen "in progress" state while a Livewire action runs. --}}
@props(['target', 'title', 'note' => 'This page updates when it’s done.'])

<div wire:loading.flex wire:target="{{ $target }}" class="fixed inset-x-0 bottom-0 top-16 z-40 items-start justify-center bg-paper px-[18px]" role="status" aria-live="polite">
    <div class="mx-auto flex max-w-[680px] flex-col items-center gap-7 py-[72px] text-center">
        <div>
            <h1 class="m-0 text-[clamp(30px,4vw,42px)] font-extrabold tracking-[-0.02em]">{{ $title }}</h1>
            <p class="m-0 mt-1.5 text-[16.5px] text-muted">{{ $note }}</p>
        </div>
        <div class="h-2 w-[min(460px,80vw)] overflow-hidden rounded bg-line" aria-hidden="true">
            <div class="stellar-progress h-full w-2/5 rounded bg-accent"></div>
        </div>
    </div>
</div>
