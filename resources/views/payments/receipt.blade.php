<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-white">Payment receipt</h2>
                <p class="mt-2 text-sm text-slate-400">Receipt for payment #{{ $payment->id }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button onclick="window.print()" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-500">Print receipt</button>
                <a href="{{ route('payments.show', $payment) }}" class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Back to payment</a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
        @endif

        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
            <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                <div>
                    <div class="text-slate-500">Payment date</div>
                    <div class="mt-1 font-semibold text-white">@dateYmd($payment->paid_at)</div>
                </div>
                <div>
                    <div class="text-slate-500">Amount</div>
                    <div class="mt-1 font-semibold text-white">@currency($payment->amount)</div>
                </div>
                <div>
                    <div class="text-slate-500">Penalty paid</div>
                    <div class="mt-1 font-semibold text-white">@currency($payment->penalty_paid)</div>
                </div>
                <div>
                    <div class="text-slate-500">Method</div>
                    <div class="mt-1 font-semibold text-white">{{ \App\Helpers\PaymentMethodHelper::getLabel($payment->method) }}</div>
                </div>
                <div>
                    <div class="text-slate-500">Reference</div>
                    <div class="mt-1 font-semibold text-white">{{ $payment->reference_no ?? '-' }}</div>
                </div>
            </div>
            <div class="mt-6 text-slate-500">Remarks</div>
            <div class="mt-2 whitespace-pre-line text-slate-200">{{ $payment->remarks ?? '-' }}</div>
        </section>

        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <div class="text-slate-500">Loan</div>
                    <div class="mt-1 font-semibold text-white">{{ $payment->loan->reference_no }}</div>
                    <div class="mt-2 text-sm text-slate-400">Borrower: <a href="{{ route('borrowers.show', $payment->loan->borrower) }}" class="text-blue-300 hover:text-blue-200">{{ $payment->loan->borrower->last_name }}, {{ $payment->loan->borrower->first_name }}</a></div>
                </div>
                <div>
                    <div class="text-slate-500">Loan balance</div>
                    <div class="mt-1 font-semibold text-white">@currency($payment->loan->total_payable - $payment->loan->total_paid)</div>
                    <div class="mt-2 text-sm text-slate-400">Status: {{ ucfirst($payment->loan->status) }}</div>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
