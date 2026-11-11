@props(['title' => 'Something went wrong', 'message' => 'Please try again or contact an administrator.', 'retryUrl' => null])
<div class="grid place-items-center gap-4 p-10 text-center text-slate-400">
    <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-rose-500/10 text-3xl text-rose-300">!</div>
    <div>
        <p class="text-lg font-semibold text-white">{{ $title }}</p>
        <p class="mt-2 text-sm">{{ $message }}</p>
        @if ($retryUrl)
            <a href="{{ $retryUrl }}" class="mt-4 inline-flex rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-500">Retry</a>
        @endif
    </div>
</div>
