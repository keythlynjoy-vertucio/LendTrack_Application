<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-white">Edit borrower</h2>
                <p class="mt-2 text-sm text-slate-400">{{ $borrower->last_name }}, {{ $borrower->first_name }}</p>
            </div>
            <a href="{{ route('borrowers.show', $borrower) }}" class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Back</a>
        </div>
    </x-slot>

    <section class="mx-auto max-w-4xl rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
        <form method="POST" action="{{ route('borrowers.update', $borrower) }}" enctype="multipart/form-data" x-data="borrowerForm({})" x-init="init()" class="space-y-6">
            @method('PUT')
            @include('borrowers._form', ['borrower' => $borrower])

            <div class="flex justify-end gap-2">
                <a href="{{ route('borrowers.show', $borrower) }}" class="rounded-2xl border border-slate-700 px-5 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Cancel</a>
                <button type="submit" :disabled="!isValid" class="rounded-2xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50">Update borrower</button>
            </div>
        </form>
    </section>
</x-app-layout>
