@php
    $message = session('status') ?: session('error');
    $type = session('error') ? 'error' : 'success';
@endphp
@if ($message)
    <div class="fixed inset-x-0 top-4 z-50 flex justify-center px-4" x-data="{ open: true }" x-show="open" x-init="setTimeout(() => open = false, 4000)" @click.away="open = false" x-transition>
        <div class="w-full max-w-3xl rounded-3xl border border-slate-800 bg-slate-950/95 p-4 shadow-2xl shadow-slate-950/50 text-sm text-slate-200 backdrop-blur-xl">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-3">
                    <span class="mt-1 inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-{{ $type === 'error' ? 'rose' : 'emerald' }}-500/10 text-{{ $type === 'error' ? 'rose' : 'emerald' }}-300">{{ $type === 'error' ? '!' : '✓' }}</span>
                    <div>
                        <p class="font-semibold text-white">{{ $type === 'error' ? 'Error' : 'Success' }}</p>
                        <p class="mt-1 text-slate-400">{{ $message }}</p>
                    </div>
                </div>
                <button type="button" class="self-start rounded-2xl border border-slate-800 bg-slate-900 px-3 py-2 text-xs text-slate-300 transition hover:bg-slate-800" @click="open = false">Dismiss</button>
            </div>
        </div>
    </div>
@endif
