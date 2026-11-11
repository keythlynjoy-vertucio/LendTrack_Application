<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-white">Payment #{{ $payment->id }}</h2>
                <p class="mt-2 text-sm text-slate-400">Loan <a class="text-blue-300 hover:text-blue-200" href="{{ route('loans.show', $payment->loan) }}">{{ $payment->loan->reference_no }}</a></p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('payments.receipt', $payment) }}" class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Receipt</a>
                @if (auth()->user()?->hasPermission('manage_payments') || auth()->user()?->isAdmin())
                    <a href="{{ route('payments.edit', $payment) }}" class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Edit</a>
                @endif
                @if (auth()->user()?->hasPermission('manage_payments') || auth()->user()?->isAdmin())
                    <form method="POST" action="{{ route('payments.destroy', $payment) }}" onsubmit="return confirm('Delete this payment?');">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-2xl bg-rose-600 px-4 py-3 text-sm font-semibold text-white hover:bg-rose-500">Delete</button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-5">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
        @endif

        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
            <div class="grid grid-cols-1 gap-5 text-sm md:grid-cols-3">
                <div>
                    <div class="text-slate-500">Payment Date</div>
                    <div class="mt-1 font-semibold text-white">@dateYmd($payment->paid_at)</div>
                </div>
                <div>
                    <div class="text-slate-500">Amount</div>
                    <div class="mt-1 font-semibold text-white">@currency($payment->amount)</div>
                </div>
                <div>
                    <div class="text-slate-500">Penalty Paid</div>
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
                <div class="md:col-span-3">
                    <div class="text-slate-500">Remarks</div>
                    <div class="mt-1 whitespace-pre-line text-slate-200">{{ $payment->remarks ?? '-' }}</div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
            <div class="text-sm text-slate-500">Borrower</div>
            <a class="mt-1 inline-flex font-semibold text-blue-300 hover:text-blue-200" href="{{ route('borrowers.show', $payment->loan->borrower) }}">
                {{ $payment->loan->borrower->last_name }}, {{ $payment->loan->borrower->first_name }}
            </a>
            <div class="mt-4 grid grid-cols-1 gap-4 text-sm md:grid-cols-3">
                <div><span class="text-slate-500">Total payable</span><div class="font-semibold text-white">@currency($payment->loan->total_payable)</div></div>
                <div><span class="text-slate-500">Total paid</span><div class="font-semibold text-white">@currency($payment->loan->total_paid)</div></div>
                <div><span class="text-slate-500">Status</span><div class="font-semibold text-white">{{ ucfirst($payment->loan->status) }}</div></div>
            </div>
        </section>
    </div>
</x-app-layout>
