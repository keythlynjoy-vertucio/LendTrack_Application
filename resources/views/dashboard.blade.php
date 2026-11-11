<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-white">Dashboard</h2>
                <p class="mt-2 text-sm text-slate-400">Track capital, collections, due loans, and cash flow in one place.</p>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                @if (auth()->user()?->isAdmin())
                    <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-slate-700 bg-slate-900/80 px-4 py-3 text-sm font-medium text-slate-100 hover:bg-slate-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6v6l4 2"/><path d="M21 6.1A9 9 0 1111.1 3"/></svg>
                        View reports
                    </a>
                    <a href="{{ route('borrowers.create') }}" class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 hover:bg-blue-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v16m8-8H4"/></svg>
                        New borrower
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Main KPI cards: 4 columns desktop, 2 tablet, 1 mobile (compact size to match alert cards) -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-sm">
                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Released Capital</p>
                <p class="mt-2 text-2xl font-semibold text-white">@currency($totalReleased ?? 0)</p>
                <p class="mt-1 text-xs text-slate-400">Principal disbursed</p>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-sm">
                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Total Collections</p>
                <p class="mt-2 text-2xl font-semibold text-white">@currency($collectionBreakdown['total'] ?? $totalCollected ?? 0)</p>
                <p class="mt-1 text-xs text-slate-400">Regular + penalty collected</p>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-sm">
                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Active Loans</p>
                <p class="mt-2 text-2xl font-semibold text-white">{{ $activeLoansCount }}</p>
                <p class="mt-1 text-xs text-slate-400">Open loan agreements</p>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-sm">
                <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Outstanding Balance</p>
                <p class="mt-2 text-2xl font-semibold text-white">@currency($totalOutstanding ?? 0)</p>
                <p class="mt-1 text-xs text-slate-400">Uncollected balance</p>
            </div>
        </div>

        <!-- Alert strip: smaller cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Overdue Amount</p>
                        <p class="mt-2 text-lg font-semibold text-white">@currency($upcomingCardMetrics['overdue'] ?? 0)</p>
                        <p class="mt-1 text-xs text-slate-400">Installments past due</p>
                    </div>
                    <div class="shrink-0 inline-flex items-center rounded-full bg-red-600/10 px-3 py-1 text-xs font-semibold text-red-300">Overdue</div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Due Today</p>
                        <p class="mt-2 text-lg font-semibold text-white">@currency($upcomingCardMetrics['due_today'] ?? 0)</p>
                        <p class="mt-1 text-xs text-slate-400">Installments due today</p>
                    </div>
                    <div class="shrink-0 inline-flex items-center rounded-full bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-300">Today</div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Due This Week</p>
                        <p class="mt-2 text-lg font-semibold text-white">@currency($upcomingCardMetrics['due_this_week'] ?? 0)</p>
                        <p class="mt-1 text-xs text-slate-400">Due in next 7 days</p>
                    </div>
                    <div class="shrink-0 inline-flex items-center rounded-full bg-blue-500/10 px-3 py-1 text-xs font-semibold text-blue-300">This week</div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs uppercase tracking-[0.18em] text-slate-400">Upcoming Amount</p>
                        <p class="mt-2 text-lg font-semibold text-white">@currency($upcomingCardMetrics['upcoming'] ?? 0)</p>
                        <p class="mt-1 text-xs text-slate-400">Future unpaid installments</p>
                    </div>
                    <div class="shrink-0 inline-flex items-center rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-300">Upcoming</div>
                </div>
            </div>
        </div>

        <!-- Upcoming collections: current month unpaid or partially paid schedules (limit 5) -->
        <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-4 shadow-sm">
            <div class="flex items-start justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-white">Upcoming Collections</h3>
                    <p class="mt-0.5 text-sm text-slate-400">Unpaid or partially paid installments due this month.</p>
                </div>
                <div class="flex items-center gap-2">
                    @if(route('reports.discrepancy'))
                        <a href="{{ route('reports.discrepancy') }}" class="rounded-2xl border border-slate-700 px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">See all</a>
                    @endif
                </div>
            </div>
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-left text-sm text-slate-200">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-500">
                            <th class="py-3 px-4">Due Date</th>
                            <th class="py-3 px-4">Borrower</th>
                            <th class="py-3 px-4">Loan Reference</th>
                            <th class="py-3 px-4 text-right">Installment Balance</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($upcomingCollections as $item)
                            <tr class="hover:bg-slate-800/30">
                                <td class="py-3 px-4 text-slate-300 whitespace-nowrap">{{ $item['due_date'] }}</td>
                                <td class="py-3 px-4 text-slate-100">{{ $item['borrower_name'] }}</td>
                                <td class="py-3 px-4 text-slate-300 whitespace-nowrap">{{ $item['loan_reference'] }}</td>
                                <td class="py-3 px-4 text-right text-white">@currency($item['installment_balance'])</td>
                                <td class="py-3 px-4">
                                    @if($item['status'] === 'Installment Overdue')
                                        <span class="inline-flex items-center rounded-full bg-red-600/10 px-3 py-1 text-xs font-semibold text-red-300">Installment Overdue</span>
                                    @elseif($item['status'] === 'Due Today')
                                        <span class="inline-flex items-center rounded-full bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-300">Due Today</span>
                                    @elseif($item['status'] === 'Partially Paid')
                                        <span class="inline-flex items-center rounded-full bg-slate-800/40 px-3 py-1 text-xs font-semibold text-slate-200">Partially Paid</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-300">Upcoming</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right">
                                    @if($item['action_url'] ?? null)
                                        <a class="rounded-2xl bg-slate-800 px-3 py-1 text-xs text-blue-300 hover:bg-slate-700" href="{{ $item['action_url'] }}">View</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 px-4 text-center text-slate-500">No upcoming collections for this month.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-4 shadow-lg shadow-slate-950/40">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-white">Loan status distribution</h3>
                        <p class="mt-0.5 text-sm text-slate-400">Current portfolio mix</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-emerald-500/10 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-emerald-300 ring-1 ring-emerald-500/20">Live</span>
                </div>
                <div class="mt-4 space-y-2">
                    @php($statusTotal = max(array_sum($statusCounts ?? []), 1))
                    @forelse ($statusCounts ?? [] as $status => $total)
                        @php($percent = round(($total / $statusTotal) * 100))
                        <div class="rounded-2xl border border-slate-800 bg-slate-950/40 px-3 py-2 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                @php($statusLabel = $status === 'overdue' ? 'Loan Overdue' : ucfirst($status))
                                <span class="font-medium text-slate-200">{{ $statusLabel }}</span>
                                <span class="text-slate-400">{{ $total }} {{ \Illuminate\Support\Str::plural('loan', $total) }}</span>
                            </div>
                            <div class="mt-2 h-2 rounded-full bg-slate-800">
                                <div class="h-2 rounded-full bg-blue-500" style="width: {{ $percent }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-slate-800 bg-slate-950/40 px-3 py-2 text-sm text-slate-500">
                            No loan status data available.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-4 shadow-lg shadow-slate-950/40">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-semibold text-white">Monthly collections</h3>
                        <p class="mt-0.5 text-sm text-slate-400">Latest cash flow</p>
                    </div>
                    <span class="shrink-0 rounded-full bg-blue-500/10 px-2.5 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-blue-300 ring-1 ring-blue-500/20">Insights</span>
                </div>
                @php($dashboardMonthlyItems = array_slice($monthlyCollections ?? [], -6, 6, true))
                @php($maxDashboardMonthly = max(array_values($dashboardMonthlyItems ?: [1])))
                <div class="mt-4 grid min-h-32 items-end gap-2 rounded-2xl border border-slate-800 bg-slate-950/40 px-3 py-3" style="grid-template-columns: repeat({{ max(count($dashboardMonthlyItems), 1) }}, minmax(0, 1fr));">
                    @forelse ($dashboardMonthlyItems as $month => $total)
                        @php($percent = $maxDashboardMonthly > 0 ? max(round(((float) $total / $maxDashboardMonthly) * 100), 4) : 4)
                        <div class="flex min-w-0 flex-col justify-end gap-2">
                            <div class="flex h-20 items-end">
                                <div class="w-full rounded-t-lg bg-emerald-500" style="height: {{ $percent }}%"></div>
                            </div>
                            <div class="min-w-0 text-center">
                                @php($monthLabel = \Illuminate\Support\Carbon::parse($month . '-01')->format('M'))
                                <p class="truncate text-xs text-slate-400">{{ $monthLabel }}</p>
                                <p class="truncate text-xs font-semibold text-slate-100">@currency($total)</p>
                            </div>
                        </div>
                    @empty
                        <div class="text-sm text-slate-500">
                            No collection data available yet.
                        </div>
                    @endforelse
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/40 p-4 text-sm">
                        <div class="text-slate-500 uppercase tracking-[0.24em]">Regular payments</div>
                        <div class="mt-3 text-2xl font-semibold text-white">@currency($collectionBreakdown['regular'] ?? 0)</div>
                    </div>
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/40 p-4 text-sm">
                        <div class="text-slate-500 uppercase tracking-[0.24em]">Penalty paid</div>
                        <div class="mt-3 text-2xl font-semibold text-white">@currency($collectionBreakdown['penalty'] ?? 0)</div>
                    </div>
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/40 p-4 text-sm">
                        <div class="text-slate-500 uppercase tracking-[0.24em]">Total cash</div>
                        <div class="mt-3 text-2xl font-semibold text-white">@currency($collectionBreakdown['total'] ?? 0)</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-white">Recent payments</h3>
                    <p class="mt-1 text-sm text-slate-400">Latest collections and borrower updates</p>
                </div>
                <a href="{{ route('payments.index') }}" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-slate-200 hover:bg-slate-700">See all</a>
            </div>
            <div class="mt-6 overflow-x-auto">
                <table class="min-w-full text-left text-sm text-slate-200">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-500">
                            <th class="py-3 px-4">Payment Date</th>
                            <th class="py-3 px-4">Borrower</th>
                            <th class="py-3 px-4">Loan</th>
                            <th class="py-3 px-4 text-right">Amount</th>
                            <th class="py-3 px-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($recentPayments as $payment)
                            @php($loan = $payment->loan)
                            @php($borrower = $loan?->borrower)
                            <tr>
                                <td class="py-4 px-4 text-slate-300">@dateYmd($payment->paid_at)</td>
                                <td class="py-4 px-4 text-slate-100">
                                    @if ($borrower)
                                        {{ $borrower->last_name }}, {{ $borrower->first_name }}
                                    @else
                                        <span class="text-slate-500">Borrower unavailable</span>
                                    @endif
                                </td>
                                <td class="py-4 px-4 text-slate-300 whitespace-nowrap">{{ $loan?->reference_no ?? 'Loan unavailable' }}</td>
                                <td class="py-4 px-4 text-right text-white">@currency($payment->amount)</td>
                                <td class="py-4 px-4 text-right">
                                    <a class="rounded-2xl bg-slate-800 px-3 py-2 text-xs text-blue-300 hover:bg-slate-700" href="{{ route('payments.show', $payment) }}">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 px-4 text-center text-slate-500">No payments yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</x-app-layout>
