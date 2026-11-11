<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-white">Edit loan</h2>
                <p class="mt-2 text-sm text-slate-400">{{ $loan->reference_no }}</p>
            </div>
            <a href="{{ route('loans.show', $loan) }}" class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Back</a>
        </div>
    </x-slot>

    <section class="mx-auto max-w-4xl rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
        <form method="POST" action="{{ route('loans.update', $loan) }}" x-data="loanFormPreview({
                principal: @json((float)($loan->principal_amount ?? 0)),
                interestRate: @json((float)($loan->interest_rate ?? 0)),
                termMonths: @json((int)($loan->term_months ?? 1)),
                frequency: @json($loan->frequency ?? 'monthly'),
                penaltyPerMissedDue: @json((float)($loan->penalty_per_missed_due ?? 0)),
                firstDueDate: @json($loan->first_due_date ? $loan->first_due_date->format('Y-m-d') : ''),
                startDate: @json($loan->start_date ? $loan->start_date->format('Y-m-d') : ''),
                remarks: @json($loan->remarks ?? ''),
                notes: @json($loan->notes ?? ''),
            })" x-init="init()" class="space-y-6">
            @method('PUT')
            @include('loans._form', ['loan' => $loan, 'borrowerId' => null])

            <div class="flex flex-wrap justify-end gap-2">
                <a href="{{ route('loans.show', $loan) }}" class="rounded-2xl border border-slate-700 px-5 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Cancel</a>
                <button type="reset" class="rounded-2xl border border-slate-700 px-5 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Reset form</button>
                <button type="submit" :disabled="!isValid" class="rounded-2xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50">Save loan</button>
            </div>
        </form>
    </section>
</x-app-layout>
