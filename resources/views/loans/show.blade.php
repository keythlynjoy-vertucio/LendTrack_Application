<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-white">Loan {{ $loan->reference_no }}</h2>
                <p class="mt-2 text-sm text-slate-400">Borrower <a class="text-blue-300 hover:text-blue-200" href="{{ route('borrowers.show', $loan->borrower) }}">{{ $loan->borrower->last_name }}, {{ $loan->borrower->first_name }}</a></p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if (auth()->user()?->hasPermission('record_payments') || auth()->user()?->isAdmin())
                    @php($status = strtolower($loan->status))
                    @if ($status !== 'closed' && $status !== 'paid')
                        <a href="{{ route('payments.create', ['loan_id' => $loan->id]) }}" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-500">Add payment</a>
                    @endif
                @endif
                @if (auth()->user()?->hasPermission('manage_loans') || auth()->user()?->isAdmin())
                    <a href="{{ route('loans.edit', $loan) }}" class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Edit</a>
                @endif
                @if (auth()->user()->role === 'admin')
                    @php($status = strtolower($loan->status))
                    @if ($status === 'closed')
                        <form method="POST" action="{{ route('loans.unclose', $loan) }}" style="display: inline;">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="rounded-2xl bg-amber-600 px-4 py-3 text-sm font-semibold text-white hover:bg-amber-500">Unclose loan</button>
                        </form>
                    @elseif ($status !== 'paid')
                        <form method="POST" action="{{ route('loans.close', $loan) }}" style="display: inline;" onsubmit="return openCloseLoanModal(event)">
                            @csrf
                            @method('PATCH')
                            <button type="button" onclick="openCloseLoanModal()" class="rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-500">Close loan</button>
                        </form>
                    @endif
                @endif
                @if (auth()->user()?->hasPermission('manage_loans') || auth()->user()?->isAdmin())
                    <form method="POST" action="{{ route('loans.destroy', $loan) }}" onsubmit="return confirm('Delete this loan? This will also delete payments for it.');">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-2xl bg-rose-600 px-4 py-3 text-sm font-semibold text-white hover:bg-rose-500">Delete</button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ session('error') }}</div>
        @endif

        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
            <div class="grid grid-cols-1 gap-5 text-sm md:grid-cols-4">
                <div>
                    <div class="text-slate-500">Principal</div>
                    <div class="mt-1 font-semibold text-white">@currency($loan->principal_amount)</div>
                </div>
                <div>
                    <div class="text-slate-500">Interest rate</div>
                    <div class="mt-1 font-semibold text-white">{{ number_format((float) $loan->interest_rate, 2) }}%</div>
                </div>
                <div>
                    <div class="text-slate-500">Total payable</div>
                    <div class="mt-1 font-semibold text-white">@currency($loan->total_payable)</div>
                </div>
                <div>
                    <div class="text-slate-500">Total paid</div>
                    <div class="mt-1 font-semibold text-white">@currency($loan->total_paid)</div>
                </div>
                <div>
                    <div class="text-slate-500">Outstanding balance</div>
                    <div class="mt-1 font-semibold text-white">@currency($loan->outstanding_balance)</div>
                </div>
                <div>
                    <div class="text-slate-500">Start date</div>
                    <div class="mt-1 font-semibold text-white">@dateYmd($loan->start_date)</div>
                </div>
                <div>
                    <div class="text-slate-500">Due date</div>
                    <div class="mt-1 font-semibold text-white">@dateYmd($loan->due_date)</div>
                </div>
                <div>
                    <div class="text-slate-500">Term</div>
                    <div class="mt-1 font-semibold text-white">{{ $loan->term_months }} month(s)</div>
                </div>
                <div>
                    <div class="text-slate-500">Frequency</div>
                    <div class="mt-1 font-semibold text-white">{{ ucfirst($loan->frequency ?? 'monthly') }}</div>
                </div>
                <div>
                    <div class="text-slate-500">Status</div>
                    @php($loanStatusLabel = $loan->status === 'overdue' ? 'Loan Overdue' : ucfirst(str_replace('_', ' ', $loan->status)))
                    <div class="mt-1 font-semibold text-white">{{ $loanStatusLabel }}</div>
                </div>
                @if ($loan->closed_at)
                    <div>
                        <div class="text-slate-500">Closed At</div>
                        <div class="mt-1 font-semibold text-white">{{ $loan->closed_at->format('M d, Y H:i') }}</div>
                    </div>
                    <div>
                        <div class="text-slate-500">Closed By</div>
                        <div class="mt-1 font-semibold text-white">{{ $loan->closedBy->name ?? 'System' }}</div>
                    </div>
                    <div>
                        <div class="text-slate-500">Close Reason</div>
                        <div class="mt-1 font-semibold text-white">{{ $loan->close_reason_label }}</div>
                    </div>
                @endif
                <div class="md:col-span-4">
                    <div class="text-slate-500">Remarks</div>
                    <div class="mt-1 whitespace-pre-line text-slate-200">{{ $loan->remarks ?? '-' }}</div>
                </div>
                @if ($loan->close_remarks)
                    <div class="md:col-span-4">
                        <div class="text-slate-500">Close Remarks</div>
                        <div class="mt-1 whitespace-pre-line rounded-lg border-l-4 border-emerald-500 bg-slate-800/50 p-3 text-slate-200">{{ $loan->close_remarks }}</div>
                    </div>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/90 shadow-lg shadow-slate-950/40">
            <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
                <h3 class="text-lg font-semibold text-white">Payments</h3>
                <a href="{{ route('payments.index', ['loan_id' => $loan->id]) }}" class="text-sm font-medium text-blue-300 hover:text-blue-200">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-slate-200">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/60 text-xs uppercase tracking-[0.18em] text-slate-500">
                            <th class="px-5 py-4 font-semibold">Payment Date</th>
                            <th class="px-5 py-4 text-right font-semibold">Amount</th>
                            <th class="px-5 py-4 font-semibold">Method</th>
                            <th class="px-5 py-4 font-semibold">Reference</th>
                            <th class="px-5 py-4 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($loan->payments as $payment)
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-5 py-4 text-slate-300 whitespace-nowrap">@dateYmd($payment->paid_at)</td>
                                <td class="px-5 py-4 text-right font-semibold text-white">@currency($payment->amount)</td>
                                <td class="px-5 py-4">{{ \App\Helpers\PaymentMethodHelper::getLabel($payment->method) }}</td>
                                <td class="px-5 py-4 text-slate-300">{{ $payment->reference_no ?? '-' }}</td>
                                <td class="px-5 py-4 text-right"><a href="{{ route('payments.show', $payment) }}" class="text-blue-300 hover:text-blue-200">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-500">No payments yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    
    @if (auth()->user()->role === 'admin')
        @php($status = strtolower($loan->status))
        @if ($status === 'closed')
            @include('loans._unclose_loan_modal')
        @else
            @include('loans._close_loan_modal')
        @endif
    @endif
</x-app-layout>
