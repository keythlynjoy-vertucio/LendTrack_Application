@props(['title' => 'No results found', 'message' => 'Try adjusting your filters or add a new record.', 'icon' => null])
<div class="grid place-items-center gap-4 p-10 text-center text-slate-400">
    <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-slate-950/70 text-3xl text-slate-300">
        {{ $icon ?? '—' }}
    </div>
    <div>
        <p class="text-lg font-semibold text-white">{{ $title }}</p>
        <p class="mt-2 text-sm">{{ $message }}</p>
    </div>
</div>
