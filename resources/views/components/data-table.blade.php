@props(['class' => ''])
<div {{ $attributes->merge(['class' => 'overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/90 shadow-lg shadow-slate-950/40 ' . $class]) }}>
    <div class="overflow-x-auto">
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="border-t border-slate-800 px-5 py-4">
            {{ $footer }}
        </div>
    @endisset
</div>
