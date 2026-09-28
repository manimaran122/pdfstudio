<x-stellar-layout title="Recent files">
    <main class="flex-grow">
        <div class="mx-auto max-w-[1320px] px-[18px] pb-16 pt-10 md:px-10">
            <div class="mb-[18px] flex items-baseline justify-between gap-4">
                <h1 class="m-0 text-[30px] font-extrabold tracking-[-0.02em]">Recent files</h1>
                <span class="text-sm text-muted">Files from this session</span>
            </div>
            <x-stellar.recent-table :files="$files" />
        </div>
    </main>

    <x-stellar.footer />
</x-stellar-layout>
