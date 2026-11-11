@props(['class' => ''])
<div {{ $attributes->merge(['class' => 'rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-lg shadow-slate-950/40 ' . $class]) }}>
    {{ $slot }}
</div>
