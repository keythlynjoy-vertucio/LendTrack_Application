<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-white">{{ $borrower->last_name }}, {{ $borrower->first_name }}</h2>
                <p class="mt-2 text-sm text-slate-400">Borrower #{{ $borrower->id }}</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if (auth()->user()?->hasPermission('create_loans') || auth()->user()?->isAdmin())
                    <a href="{{ route('loans.create', ['borrower_id' => $borrower->id]) }}" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-500">Create loan</a>
                @endif
                @if (auth()->user()?->hasPermission('manage_borrowers') || auth()->user()?->isAdmin())
                    <a href="{{ route('borrowers.edit', $borrower) }}" class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Edit</a>
                    <form method="POST" action="{{ route('borrowers.destroy', $borrower) }}" onsubmit="return confirm('Delete this borrower? This will also delete their loans and payments.');">
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

        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
                @if($borrower->photo_url)
                    <img src="{{ $borrower->photo_url }}" alt="{{ $borrower->first_name }} {{ $borrower->last_name }}" class="h-32 w-32 rounded-3xl object-cover ring-1 ring-slate-700" />
                @else
                    <div class="flex h-32 w-32 items-center justify-center rounded-3xl bg-slate-800 ring-1 ring-slate-700">
                        <span class="text-3xl font-semibold uppercase tracking-[0.12em] text-slate-100">{{ $borrower->initials }}</span>
                    </div>
                @endif
                <div class="grid flex-1 grid-cols-1 gap-5 text-sm md:grid-cols-3">
                    @foreach ([
                        'Phone' => $borrower->phone ?? '-',
                        'Email' => $borrower->email ?? '-',
                        'Status' => ucfirst($borrower->status),
                        'Address' => $borrower->address ?? '-',
                        'Notes' => $borrower->notes ?? '-',
                    ] as $label => $value)
                        <div class="{{ in_array($label, ['Address', 'Notes'], true) ? 'md:col-span-3' : '' }}">
                            <div class="text-slate-500">{{ $label }}</div>
                            <div class="mt-1 whitespace-pre-line font-semibold text-white">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-3xl border border-slate-800 bg-slate-950/50 p-4">
                    <div class="text-sm text-slate-500">Loans</div>
                    <div class="mt-2 text-3xl font-semibold text-white">{{ $loanCount ?? 0 }}</div>
                </div>
                <div class="rounded-3xl border border-slate-800 bg-slate-950/50 p-4">
                    <div class="text-sm text-slate-500">Active</div>
                    <div class="mt-2 text-3xl font-semibold text-white">{{ $activeLoans ?? 0 }}</div>
                </div>
                <div class="rounded-3xl border border-slate-800 bg-slate-950/50 p-4">
                    <div class="text-sm text-slate-500">Paid</div>
                    <div class="mt-2 text-3xl font-semibold text-white">{{ $paidLoans ?? 0 }}</div>
                </div>
                <div class="rounded-3xl border border-slate-800 bg-slate-950/50 p-4">
                    <div class="text-sm text-slate-500">Overdue</div>
                    <div class="mt-2 text-3xl font-semibold text-white">{{ $overdueLoans ?? 0 }}</div>
                </div>
                <div class="rounded-3xl border border-slate-800 bg-slate-950/50 p-4">
                    <div class="text-sm text-slate-500">Outstanding</div>
                    <div class="mt-2 text-3xl font-semibold text-white">@currency($outstandingBalance ?? 0)</div>
                </div>
            </div>
        </section>

        @if (isset($recentPayments) && $recentPayments->isNotEmpty())
            <section class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/90 shadow-lg shadow-slate-950/40">
                <div class="border-b border-slate-800 px-5 py-4">
                    <h3 class="text-lg font-semibold text-white">Recent payments</h3>
                    <p class="mt-1 text-sm text-slate-400">Latest collections across this borrower's loans.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm text-slate-200">
                        <thead>
                            <tr class="border-b border-slate-800 bg-slate-950/60 text-xs uppercase tracking-[0.18em] text-slate-500">
                                <th class="px-5 py-4 font-semibold">Date</th>
                                <th class="px-5 py-4 font-semibold">Loan</th>
                                <th class="px-5 py-4 text-right font-semibold">Amount</th>
                                <th class="px-5 py-4 font-semibold">Method</th>
                                <th class="px-5 py-4 font-semibold">Reference</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach ($recentPayments as $payment)
                                <tr class="hover:bg-slate-800/40">
                                    <td class="px-5 py-4 whitespace-nowrap">@dateYmd($payment->paid_at)</td>
                                    <td class="px-5 py-4 text-blue-300 whitespace-nowrap"><a href="{{ route('loans.show', $payment->loan) }}">{{ $payment->loan->reference_no }}</a></td>
                                    <td class="px-5 py-4 text-right font-semibold text-white">@currency($payment->amount)</td>
                                    <td class="px-5 py-4">{{ \App\Helpers\PaymentMethodHelper::getLabel($payment->method) }}</td>
                                    <td class="px-5 py-4 text-slate-300">{{ $payment->reference_no ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/90 shadow-lg shadow-slate-950/40">
            <div class="border-b border-slate-800 px-5 py-4">
                <h3 class="text-lg font-semibold text-white">Loans</h3>
                <p class="mt-1 text-sm text-slate-400">Loan history for this borrower.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-slate-200">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/60 text-xs uppercase tracking-[0.18em] text-slate-500">
                            <th class="px-5 py-4 font-semibold">Reference</th>
                            <th class="px-5 py-4 font-semibold">Dates</th>
                            <th class="px-5 py-4 text-right font-semibold">Payable</th>
                            <th class="px-5 py-4 text-right font-semibold">Paid</th>
                            <th class="px-5 py-4 font-semibold">Status</th>
                            <th class="px-5 py-4 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($borrower->loans as $loan)
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-5 py-4 font-semibold text-white">{{ $loan->reference_no }}</td>
                                <td class="px-5 py-4 text-slate-300 whitespace-nowrap">Start: @dateYmd($loan->start_date)<br>Due: @dateYmd($loan->due_date)</td>
                                <td class="px-5 py-4 text-right text-white">@currency($loan->total_payable)</td>
                                <td class="px-5 py-4 text-right text-slate-300">@currency($loan->total_paid)</td>
                                <td class="px-5 py-4">{{ $loan->status === 'overdue' ? 'Loan Overdue' : ucfirst($loan->status) }}</td>
                                <td class="px-5 py-4 text-right"><a href="{{ route('loans.show', $loan) }}" class="text-blue-300 hover:text-blue-200">View</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-10 text-center text-slate-500">No loans yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
