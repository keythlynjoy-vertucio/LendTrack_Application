<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-white">New payment</h2>
                <p class="mt-2 text-sm text-slate-400">Record a collection against an active loan.</p>
            </div>
            <a href="{{ route('payments.index') }}" class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Back</a>
        </div>
    </x-slot>

    <section class="mx-auto max-w-4xl rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
        @php
            $initialLoanId = old('loan_id') ?? $loanId ?? '';
            $initialDate = old('paid_at') ?? now()->format('Y-m-d');
            $initialAmount = old('amount') ?? '';
            $initialMethod = old('method') ?? 'cash';
            $initialReference = old('reference_no') ?? '';
            $initialPenalty = old('penalty_paid') ?? '0.00';
            
            $loansData = $loans->map(function ($loan) {
                return [
                    'id' => $loan->id,
                    'reference_no' => $loan->reference_no,
                    'borrower' => $loan->borrower->last_name . ', ' . $loan->borrower->first_name,
                    'total_payable' => (float) $loan->total_payable,
                    'total_paid' => (float) $loan->total_paid,
                    'status' => $loan->status,
                    'due_date' => optional($loan->due_date)->format('Y-m-d'),
                    'outstanding' => round((float) $loan->total_payable - (float) $loan->total_paid, 2),
                    'frequency' => $loan->frequency ?? 'monthly',
                    'term_months' => (int) $loan->term_months,
                ];
            });
        @endphp
        @php
            $initialData = json_encode([
                'loans' => $loansData,
                'initialLoanId' => $initialLoanId,
                'initialDate' => $initialDate,
                'initialAmount' => $initialAmount,
                'initialMethod' => $initialMethod,
                'initialReference' => $initialReference,
                'initialPenalty' => $initialPenalty,
            ]);
        @endphp
        <form method="POST" action="{{ route('payments.store') }}" x-data="paymentPenaltyForm({{ $initialData }})" x-init="init()" class="space-y-6">
            @include('payments._form', ['payment' => new \App\Models\Payment()])

            <div class="flex justify-end">
                <button type="submit" :disabled="!isValid" class="rounded-2xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50">Save payment</button>
            </div>
        </form>
    </section>
</x-app-layout>
