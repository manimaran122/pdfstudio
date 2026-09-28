{{-- Drop target for the component's hidden #tool-files input (wire:model="uploads"). --}}
@props(['title', 'hint', 'button', 'limits', 'messages' => []])

<div
    x-data="{ over: false }"
    x-on:dragover.prevent="over = true"
    x-on:dragleave.prevent="over = false"
    x-on:drop.prevent="over = false; if ($event.dataTransfer.files.length) $wire.uploadMultiple('uploads', Array.from($event.dataTransfer.files))"
    x-bind:class="over ? 'border-accent bg-[#FFF8F7]' : 'border-line-strong bg-white'"
    class="flex min-h-[420px] flex-col items-center justify-center gap-3.5 rounded-[22px] border-2 border-dashed p-8 text-center transition-colors"
>
    <div class="flex h-[76px] w-[76px] items-center justify-center rounded-full bg-sand">
        <x-stellar.icon d="M12 16V5M7 10l5-5 5 5M5 19h14" :size="34" />
    </div>
    <strong class="text-[22px]">{{ $title }}</strong>
    <span class="text-muted">{{ $hint }}</span>
    <button type="button" x-on:click="document.getElementById('tool-files').click()" class="inline-flex h-12 items-center justify-center gap-[9px] rounded-xl bg-accent px-[22px] text-[15.5px] font-bold text-white hover:bg-accent-dark">
        <span wire:loading.remove wire:target="uploads">{{ $button }}</span>
        <span wire:loading wire:target="uploads">Uploading…</span>
    </button>
    <small class="text-[13px] text-muted-light">{{ $limits }}</small>

    <x-stellar.errors :messages="$messages" />
</div>
