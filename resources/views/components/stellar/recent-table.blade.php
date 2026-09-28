{{-- Files created in this session (App\Support\RecentFiles). --}}
@props(['files'])

@php
    $tools = collect(App\Support\ToolCatalog::flat())->keyBy('slug');
@endphp

<div class="overflow-hidden rounded-[18px] border border-line bg-white">
    @if ($files)
        <div class="overflow-x-auto">
            <table class="w-full border-collapse text-[14.5px]">
                <thead>
                    <tr class="bg-[#FBFBFA] text-left text-[12.5px] font-bold text-muted-light">
                        <th class="border-b border-line px-5 py-3 font-bold">File</th>
                        <th class="border-b border-line px-5 py-3 font-bold">Tool</th>
                        <th class="border-b border-line px-5 py-3 font-bold">Size</th>
                        <th class="border-b border-line px-5 py-3 font-bold">Time</th>
                        <th class="border-b border-line px-5 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($files as $file)
                        @php($tool = $tools[$file['tool']] ?? null)
                        <tr class="border-b border-sand last:border-b-0">
                            <td class="px-5 py-3.5">
                                <span class="flex items-center gap-2.5 font-semibold">
                                    @if ($tool)
                                        <x-stellar.tile :color="$tool['color']" :tint="$tool['tint']" :icon="$tool['icon']" :size="28" :icon-size="15" />
                                    @endif
                                    <span class="break-all">{{ $file['name'] }}</span>
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3.5">{{ $tool['name'] ?? $file['tool'] }}</td>
                            <td class="whitespace-nowrap px-5 py-3.5">{{ App\Support\PdfWorkspace::formatBytes($file['bytes']) }}</td>
                            <td class="whitespace-nowrap px-5 py-3.5">
                                <time datetime="{{ date(DATE_ATOM, $file['at']) }}">{{ \Illuminate\Support\Carbon::createFromTimestamp($file['at'])->timezone(config('app.timezone'))->format('H:i') }}</time>
                            </td>
                            <td class="px-5 py-3.5 text-right">
                                <a href="{{ route('recent.download', $file['id']) }}" class="inline-flex h-[42px] items-center gap-2 rounded-[10px] border border-line-strong bg-white px-[15px] text-[14.5px] font-semibold text-ink no-underline hover:border-ink">Download</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="px-5 py-7 text-center text-muted">Files you create will appear here until they’re removed after {{ App\Support\PdfWorkspace::retention() }}.</div>
    @endif
</div>
