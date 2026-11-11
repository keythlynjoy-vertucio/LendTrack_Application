@props(['open' => false, 'title' => 'Confirm action', 'description' => 'This action cannot be undone.'])
<div x-data="{ open: @entangle($attributes->wire('model')).defer }" x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 px-4 py-6" style="display: none;">
    <div class="mx-auto w-full max-w-md rounded-3xl border border-slate-800 bg-slate-900/95 p-6 shadow-2xl shadow-black/40">
        <div class="space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-xl font-semibold text-white">{{ $title }}</h3>
                    <p class="mt-2 text-sm text-slate-400">{{ $description }}</p>
                </div>
                <button type="button" @click="open = false" class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-800 text-slate-300 hover:bg-slate-700">×</button>
            </div>
            <div class="flex justify-end gap-3">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
