@props(['title', 'subtitle' => null])
<div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        <h1 class="text-3xl font-semibold text-white">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-2 text-sm text-slate-400">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="flex flex-wrap items-center gap-2">
        {{ $slot }}
    </div>
</div>
