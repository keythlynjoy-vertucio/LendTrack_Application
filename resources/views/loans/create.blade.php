<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-white">New loan</h2>
                <p class="mt-2 text-sm text-slate-400">Create a loan agreement and calculate its payable amount.</p>
            </div>
            <a href="{{ route('loans.index') }}" class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Back</a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl">
        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
            <form method="POST" action="{{ route('loans.store') }}" novalidate x-data="loanFormPreview({
                    principal: @json(old('principal_amount', optional($loan ?? null)->principal_amount ?? '0.00')),
                    interestRate: @json(old('interest_rate', optional($loan ?? null)->interest_rate ?? '0.00')),
                    termMonths: @json(old('term_months', optional($loan ?? null)->term_months ?? '1')),
                    frequency: @json(old('frequency', optional($loan ?? null)->frequency ?? 'monthly')),
                    penaltyPerMissedDue: @json(old('penalty_per_missed_due', optional($loan ?? null)->penalty_per_missed_due ?? '0.00')),
                    firstDueDate: @json(old('first_due_date', optional($loan ?? null)->first_due_date?->format('Y-m-d'))),
                    startDate: @json(old('start_date', optional($loan ?? null)->start_date?->format('Y-m-d'))),
                })" x-init="init()" class="space-y-5">
                @include('loans._form', ['loan' => null, 'borrowerId' => $borrowerId ?? null])

                <div class="flex flex-wrap justify-end gap-2">
                    <button type="reset" class="rounded-2xl border border-slate-700 px-5 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Clear form</button>
                    <button type="submit" :disabled="!isValid" class="rounded-2xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50">Save loan</button>
                </div>
            </form>
        </section>
    </div>
</x-app-layout>
