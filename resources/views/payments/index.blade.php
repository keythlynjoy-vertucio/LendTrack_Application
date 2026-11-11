<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-white">Payments</h2>
                <p class="mt-2 text-sm text-slate-400">Review collections by loan, borrower, date, and payment method.</p>
            </div>

            @if(auth()->user()?->hasPermission('record_payments') || auth()->user()?->isAdmin())
                <a href="{{ route('payments.create') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 hover:bg-blue-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v16m8-8H4"/></svg>
                    New payment
                </a>
            @endif
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-lg shadow-slate-950/40">
            <form method="GET" class="grid grid-cols-1 gap-4 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-slate-200">Loan</label>
                    <select name="loan_id" class="field-input">
                        <option value="">All loans</option>
                        @foreach ($loans as $loan)
                            <option value="{{ $loan->id }}" @selected((string) $loanId === (string) $loan->id)>
                                {{ $loan->reference_no }} - {{ $loan->borrower->last_name }}, {{ $loan->borrower->first_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-200">From</label>
                    <input type="date" name="from" value="{{ $from }}" class="field-input" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-200">To</label>
                    <input type="date" name="to" value="{{ $to }}" class="field-input" />
                </div>

                <div class="flex items-end gap-2">
                    <button class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-500">Apply</button>
                    <a href="{{ route('payments.index') }}" class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Reset</a>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/90 shadow-lg shadow-slate-950/40">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-slate-200">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/60 text-xs uppercase tracking-[0.18em] text-slate-500">
                            <th class="px-5 py-4 font-semibold">Payment Date</th>
                            <th class="px-5 py-4 font-semibold">Loan</th>
                            <th class="px-5 py-4 font-semibold">Borrower</th>
                            <th class="px-5 py-4 text-right font-semibold">Amount</th>
                            <th class="px-5 py-4 text-right font-semibold">Penalty</th>
                            <th class="px-5 py-4 font-semibold">Method</th>
                            <th class="px-5 py-4 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($payments as $payment)
                            @php($loan = $payment->loan)
                            @php($borrower = $loan?->borrower)
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-5 py-4 text-slate-300 whitespace-nowrap">@dateYmd($payment->paid_at)</td>
                                <td class="px-5 py-4 font-semibold text-white whitespace-nowrap">{{ $loan?->reference_no ?? 'Loan unavailable' }}</td>
                                <td class="px-5 py-4">
                                    @if ($borrower)
                                        {{ $borrower->last_name }}, {{ $borrower->first_name }}
                                    @else
                                        <span class="text-slate-500">Borrower unavailable</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4 text-right font-semibold text-white">@currency($payment->amount)</td>
                                <td class="px-5 py-4 text-right font-semibold text-white">@currency($payment->penalty_paid)</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full bg-blue-500/10 px-3 py-1 text-xs font-semibold uppercase text-blue-300 ring-1 ring-blue-500/30">{{ \App\Helpers\PaymentMethodHelper::getLabel($payment->method) }}</span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('payments.show', $payment) }}" class="inline-flex rounded-2xl bg-slate-800 px-3 py-2 text-xs font-semibold text-blue-300 hover:bg-slate-700">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-slate-500">No payments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-800 px-5 py-4">
                {{ $payments->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
