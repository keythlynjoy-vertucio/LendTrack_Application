<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center rounded-2xl border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-300 hover:bg-slate-800 disabled:opacity-50']) }}>
    {{ $slot }}
</button>
