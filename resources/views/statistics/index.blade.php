<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-white">Analytics</h2>
                <p class="mt-2 text-sm text-slate-400">Visual overview of portfolio, collections, cash flow, and borrower performance.</p>
                <p class="mt-2 text-xs text-slate-500">Data as of {{ $dataAsOf }}</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-white">Analytics Period</h3>
                    <p class="mt-1 text-sm text-slate-400">Apply a period filter to keep cards and charts aligned with actual system data.</p>
                </div>
                <form method="GET" action="{{ route('statistics.index') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label class="sr-only" for="period">Period</label>
                    <select id="period" name="period" class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-100 outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-500/20">
                        <option value="all" @if($period === 'all') selected @endif>All time</option>
                        <option value="this_month" @if($period === 'this_month') selected @endif>This month</option>
                        <option value="last_month" @if($period === 'last_month') selected @endif>Last month</option>
                        <option value="last_3_months" @if($period === 'last_3_months') selected @endif>Last 3 months</option>
                        <option value="this_year" @if($period === 'this_year') selected @endif>This year</option>
                    </select>
                    <button type="submit" class="rounded-2xl bg-sky-600 px-5 py-3 text-sm font-semibold text-white hover:bg-sky-500">Apply</button>
                    <a href="{{ route('statistics.index') }}" class="rounded-2xl border border-slate-700 bg-slate-950 px-5 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Reset</a>
                </form>
            </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Total loans</p>
                <p class="mt-4 text-3xl font-semibold text-white">{{ number_format($totalLoans) }}</p>
                <p class="mt-2 text-sm text-slate-400">Total loans recorded in the system.</p>
            </div>
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Total released</p>
                <p class="mt-4 text-3xl font-semibold text-white">@currency($totalReleased)</p>
                <p class="mt-2 text-sm text-slate-400">Principal amount disbursed to borrowers.</p>
            </div>
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Total collections</p>
                <p class="mt-4 text-3xl font-semibold text-white">@currency($totalCollections)</p>
                <p class="mt-2 text-sm text-slate-400">Total payment receipts recorded.</p>
            </div>
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Total receivable</p>
                <p class="mt-4 text-3xl font-semibold text-white">@currency($totalReceivable)</p>
                <p class="mt-2 text-sm text-slate-400">Outstanding balance still due.</p>
            </div>
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Active loans</p>
                <p class="mt-4 text-3xl font-semibold text-white">{{ number_format(($statusCounts['active'] ?? 0) + ($statusCounts['overdue'] ?? 0)) }}</p>
                <p class="mt-2 text-sm text-slate-400">Loans currently active or overdue.</p>
            </div>
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Overdue amount</p>
                <p class="mt-4 text-3xl font-semibold text-white">@currency($overdueAmount)</p>
                <p class="mt-2 text-sm text-slate-400">Total amount past due across loans.</p>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Portfolio Money Overview</h3>
                        <p class="mt-1 text-sm text-slate-400">Principal, interest, payable, paid, balance, and cash.</p>
                    </div>
                </div>
                <div class="mt-6 min-h-[320px]">
                    @if(array_sum($moneyOverviewValues) > 0)
                        <canvas id="moneyOverviewChart" height="320"></canvas>
                    @else
                        <div class="flex h-full items-center justify-center rounded-3xl border border-slate-800 bg-slate-950/50">
                            <p class="text-sm text-slate-500">No portfolio overview data available.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Loan Status Mix</h3>
                        <p class="mt-1 text-sm text-slate-400">Breakdown of loan statuses in the portfolio.</p>
                    </div>
                    <span class="rounded-full bg-slate-800 px-3 py-1 text-xs uppercase tracking-[0.18em] text-slate-400">{{ array_sum($statusCounts) }} loans</span>
                </div>
                <div class="mt-6 min-h-[320px]">
                    @if(array_sum($statusCounts) > 0)
                        <canvas id="statusChart" height="320"></canvas>
                    @else
                        <div class="flex h-full items-center justify-center rounded-3xl border border-slate-800 bg-slate-950/50">
                            <p class="text-sm text-slate-500">No loan status data available.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Borrower Status Mix</h3>
                        <p class="mt-1 text-sm text-slate-400">Active/inactive borrower composition.</p>
                    </div>
                    <span class="rounded-full bg-slate-800 px-3 py-1 text-xs uppercase tracking-[0.18em] text-slate-400">{{ number_format(array_sum($borrowerStatusCounts)) }} borrowers</span>
                </div>
                <div class="mt-6 min-h-[320px]">
                    @if(array_sum($borrowerStatusCounts) > 0)
                        <canvas id="borrowerStatusChart" height="320"></canvas>
                    @else
                        <div class="flex h-full items-center justify-center rounded-3xl border border-slate-800 bg-slate-950/50">
                            <p class="text-sm text-slate-500">No borrower status data available yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Monthly Collections</h3>
                        <p class="mt-1 text-sm text-slate-400">Payment inflows for the selected period.</p>
                        <p class="mt-2 text-xs text-slate-500">{{ $trendLabel }}.</p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-semibold text-white">@currency($monthlyCollectionsTotal)</p>
                        <p class="text-sm text-slate-500">Total collected</p>
                    </div>
                </div>
                <div class="mt-6 min-h-[320px]">
                    @if(count($monthlyCollections) > 0)
                        <canvas id="monthlyCollectionsChart" height="320"></canvas>
                    @else
                        <div class="flex h-full items-center justify-center rounded-3xl border border-slate-800 bg-slate-950/50">
                            <p class="text-sm text-slate-500">No collection trend data available.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Monthly Loan Releases</h3>
                        <p class="mt-1 text-sm text-slate-400">Released principal by period.</p>
                        <p class="mt-2 text-xs text-slate-500">{{ $trendLabel }}.</p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-semibold text-white">@currency($monthlyReleasesTotal)</p>
                        <p class="text-sm text-slate-500">Total released</p>
                    </div>
                </div>
                <div class="mt-6 min-h-[320px]">
                    @if(count($monthlyReleases) > 0)
                        <canvas id="monthlyReleasesChart" height="320"></canvas>
                    @else
                        <div class="flex h-full items-center justify-center rounded-3xl border border-slate-800 bg-slate-950/50">
                            <p class="text-sm text-slate-500">No release activity available.</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Cash Flow Comparison</h3>
                        <p class="mt-1 text-sm text-slate-400">Collections versus releases over time.</p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-semibold text-white">@currency(array_sum($inflows))</p>
                        <p class="text-sm text-slate-500">Total inflows</p>
                    </div>
                </div>
                <div class="mt-6 min-h-[320px]">
                    @if(count($allMonths) > 0 && (array_sum($inflows) > 0 || array_sum($outflows) > 0))
                        <canvas id="cashFlowChart" height="320"></canvas>
                    @else
                        <div class="flex h-full items-center justify-center rounded-3xl border border-slate-800 bg-slate-950/50">
                            <p class="text-sm text-slate-500">No cash flow trend data available.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Upcoming Cutoff Buckets</h3>
                        <p class="mt-1 text-sm text-slate-400">Balance due in each upcoming window.</p>
                    </div>
                    <div class="text-right">
                        <p class="text-2xl font-semibold text-white">@currency(array_sum($cutoffBuckets))</p>
                        <p class="text-sm text-slate-500">Total collectible</p>
                    </div>
                </div>
                <div class="mt-6 min-h-[320px]">
                    @if(array_sum($cutoffBuckets) > 0)
                        <canvas id="cutoffChart" height="320"></canvas>
                    @else
                        <div class="flex h-full items-center justify-center rounded-3xl border border-slate-800 bg-slate-950/50">
                            <p class="text-sm text-slate-500">No cutoff bucket data available.</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-3">
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Collections by Method</h3>
                        <p class="mt-1 text-sm text-slate-400">Payment methods recorded in the system.</p>
                    </div>
                    <span class="rounded-full bg-slate-800 px-3 py-1 text-xs uppercase tracking-[0.18em] text-slate-400">@currency(array_sum(array_column($paymentMethods, 'total')))</span>
                </div>
                <div class="mt-6 min-h-[320px]">
                    @if(count($paymentMethods) > 0)
                        <canvas id="methodChart" height="320"></canvas>
                    @else
                        <div class="flex h-full items-center justify-center rounded-3xl border border-slate-800 bg-slate-950/50">
                            <p class="text-sm text-slate-500">No payment method insights available.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Loan Releases by Frequency</h3>
                        <p class="mt-1 text-sm text-slate-400">How often loans are scheduled.</p>
                    </div>
                    <span class="rounded-full bg-slate-800 px-3 py-1 text-xs uppercase tracking-[0.18em] text-slate-400">{{ number_format(array_sum($frequencyBuckets)) }} loans</span>
                </div>
                <div class="mt-6 min-h-[320px]">
                    @if(array_sum($frequencyBuckets) > 0)
                        <canvas id="frequencyChart" height="320"></canvas>
                    @else
                        <div class="flex h-full items-center justify-center rounded-3xl border border-slate-800 bg-slate-950/50">
                            <p class="text-sm text-slate-500">No frequency distribution available.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Payment Size Distribution</h3>
                        <p class="mt-1 text-sm text-slate-400">Payment counts by amount range.</p>
                    </div>
                    <span class="rounded-full bg-slate-800 px-3 py-1 text-xs uppercase tracking-[0.18em] text-slate-400">{{ number_format(array_sum($paymentSizeBuckets)) }} payments</span>
                </div>
                <div class="mt-6 min-h-[320px]">
                    @if(array_sum($paymentSizeBuckets) > 0)
                        <canvas id="paymentSizeChart" height="320"></canvas>
                    @else
                        <div class="flex h-full items-center justify-center rounded-3xl border border-slate-800 bg-slate-950/50">
                            <p class="text-sm text-slate-500">No payment size data available.</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-2">
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Top Borrowers by Receivable</h3>
                        <p class="mt-1 text-sm text-slate-400">Outstanding receivable balance per borrower.</p>
                    </div>
                    <span class="rounded-full bg-slate-800 px-3 py-1 text-xs uppercase tracking-[0.18em] text-slate-400">Top {{ count($topBorrowers) }}</span>
                </div>
                <div class="mt-6 min-h-[320px]">
                    @if(count($topBorrowers) > 0)
                        <canvas id="topBorrowersChart" height="320"></canvas>
                    @else
                        <div class="flex h-full items-center justify-center rounded-3xl border border-slate-800 bg-slate-950/50">
                            <p class="text-sm text-slate-500">No borrower receivable data yet.</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Top Borrowers List</h3>
                        <p class="mt-1 text-sm text-slate-400">Highest balances owed today.</p>
                    </div>
                    <span class="rounded-full bg-slate-800 px-3 py-1 text-xs uppercase tracking-[0.18em] text-slate-400">{{ count($topBorrowers) }}</span>
                </div>
                <div class="mt-6 space-y-3">
                    @forelse($topBorrowers as $borrower)
                        <div class="rounded-3xl border border-slate-800 bg-slate-950/50 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-medium text-slate-200">{{ $borrower['borrower'] }}</p>
                                <p class="text-sm font-semibold text-white">@currency($borrower['remaining'])</p>
                            </div>
                        </div>
                    @empty
                        <div class="flex h-40 items-center justify-center rounded-3xl border border-slate-800 bg-slate-950/50">
                            <p class="text-sm text-slate-500">No borrower receivable data available.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-lg font-semibold text-white">Upcoming Cutoff / Due Collections</h3>
                    <p class="mt-1 text-sm text-slate-400">Next due schedule items from active loan payment schedules.</p>
                </div>
                <p class="text-sm text-slate-400">{{ $cutoffLabel ?? 'Cutoff' }}: @if ($cutoffDate) @dateYmd($cutoffDate) @else N/A @endif</p>
            </div>
            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-3xl border border-slate-800 bg-slate-950/50 p-4">
                    <p class="text-sm text-slate-400">Collectible this month</p>
                    <p class="mt-3 text-2xl font-semibold text-white">@currency($upcomingSummary['total_outstanding'] ?? 0)</p>
                </div>
                <div class="rounded-3xl border border-slate-800 bg-slate-950/50 p-4">
                    <p class="text-sm text-slate-400">Overdue included in range</p>
                    <p class="mt-3 text-2xl font-semibold text-white">@currency($upcomingSummary['overdue'] ?? 0)</p>
                </div>
                <div class="rounded-3xl border border-slate-800 bg-slate-950/50 p-4">
                    <p class="text-sm text-slate-400">Due today</p>
                    <p class="mt-3 text-2xl font-semibold text-white">@currency($upcomingSummary['due_today'] ?? 0)</p>
                </div>
                <div class="rounded-3xl border border-slate-800 bg-slate-950/50 p-4">
                    <p class="text-sm text-slate-400">Schedule count</p>
                    <p class="mt-3 text-2xl font-semibold text-white">{{ $upcomingSummary['due_this_week_count'] ?? 0 }}</p>
                </div>
            </div>

            @if(count($upcomingItems) > 0)
                <div class="mt-6 overflow-x-auto rounded-3xl border border-slate-800 bg-slate-950/50">
                    @php
                        $badgeClasses = [
                            'overdue' => 'bg-red-500/15 text-red-300',
                            'due today' => 'bg-amber-500/15 text-amber-300',
                            'upcoming' => 'bg-sky-500/15 text-sky-300',
                            'future' => 'bg-slate-700 text-slate-300',
                            'pending' => 'bg-slate-700 text-slate-300',
                        ];
                    @endphp
                    <table class="min-w-full text-left text-sm text-slate-200">
                        <thead>
                            <tr class="border-b border-slate-800 bg-slate-900/80 text-xs uppercase tracking-[0.18em] text-slate-500">
                                <th class="px-5 py-4 font-semibold">Status</th>
                                <th class="px-5 py-4 font-semibold">Due Date</th>
                                <th class="px-5 py-4 font-semibold">Borrower</th>
                                <th class="px-5 py-4 font-semibold">Loan Reference</th>
                                <th class="px-5 py-4 text-right font-semibold">Scheduled Due</th>
                                <th class="px-5 py-4 text-right font-semibold">Paid to Schedule</th>
                                <th class="px-5 py-4 text-right font-semibold">Collectible</th>
                                <th class="px-5 py-4 text-right font-semibold">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @foreach($upcomingItems as $item)
                                <tr class="hover:bg-slate-900/50">
                                    @php
                                        $statusKey = strtolower(trim($item['status'] ?? ''));
                                        $statusClass = $badgeClasses[$statusKey] ?? 'bg-slate-700 text-slate-300';
                                    @endphp
                                    <td class="px-5 py-4">
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] {{ $statusClass }}">
                                            {{ $item['status'] ?? '-' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-slate-300">@dateYmd($item['due_date'])</td>
                                    <td class="px-5 py-4 text-white">{{ $item['borrower_name'] ?? '-' }}</td>
                                    <td class="px-5 py-4 text-slate-300">{{ $item['loan_reference'] ?? '-' }}</td>
                                    <td class="px-5 py-4 text-right text-white">@currency($item['installment_amount'] ?? 0)</td>
                                    <td class="px-5 py-4 text-right text-slate-300">@currency($item['amount_paid'] ?? 0)</td>
                                    <td class="px-5 py-4 text-right font-semibold text-white">@currency($item['installment_balance'] ?? 0)</td>
                                    <td class="px-5 py-4 text-right">
                                        @if(!empty($item['loan_id']))
                                            <a href="{{ route('loans.show', $item['loan_id']) }}" class="inline-flex rounded-2xl bg-slate-800 px-3 py-2 text-xs font-semibold text-blue-300 hover:bg-slate-700">View</a>
                                        @else
                                            <span class="text-slate-500">N/A</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="mt-6 flex h-40 items-center justify-center rounded-2xl border border-slate-800 bg-slate-950/50">
                    <p class="text-sm text-slate-500">No upcoming or overdue collections found for this period.</p>
                </div>
            @endif
        </section>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            (function() {
                const statusLabels = @json(array_keys($statusCounts));
                const statusValues = @json(array_values($statusCounts));
                const monthlyCollectionLabels = @json(array_keys($monthlyCollections));
                const monthlyCollectionsValues = @json(array_values($monthlyCollections));
                const moneyOverviewLabels = @json($moneyOverviewLabels);
                const moneyOverviewValues = @json($moneyOverviewValues);
                const monthlyReleaseLabels = @json(array_keys($monthlyReleases));
                const monthlyReleasesValues = @json(array_values($monthlyReleases));
                const monthlyLabels = @json($allMonths);
                const inflowValues = @json($inflows);
                const outflowValues = @json($outflows);
                const borrowerStatusLabels = @json(array_keys($borrowerStatusCounts));
                const borrowerStatusValues = @json(array_values($borrowerStatusCounts));
                const topBorrowers = @json($topBorrowers);
                const topBorrowerLabels = topBorrowers.map(b => b.borrower);
                const topBorrowerValues = topBorrowers.map(b => b.remaining ?? 0);
                const paymentMethods = @json($paymentMethods ?? []);
                const methodLabels = paymentMethods.map(m => m.method);
                const methodTotals = paymentMethods.map(m => m.total);
                const paymentSizeLabels = @json(array_keys($paymentSizeBuckets));
                const paymentSizeValues = @json(array_values($paymentSizeBuckets));
                const frequencyLabels = @json(array_keys($frequencyBuckets));
                const frequencyValues = @json(array_values($frequencyBuckets));
                const cutoffLabels = @json(array_keys($cutoffBuckets));
                const cutoffValues = @json(array_values($cutoffBuckets));

                const formatPhp = (value) => {
                    const number = Number(value) || 0;
                    return '₱' + number.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                };

                const xAxisTicks = {
                    color: '#94a3b8',
                    autoSkip: true,
                    maxRotation: 45,
                    minRotation: 0,
                    maxTicksLimit: 12,
                };

                const moneyTooltipOptions = {
                    plugins: {
                        tooltip: { callbacks: { label: (ctx) => formatPhp(ctx.parsed?.y ?? ctx.raw) } },
                        legend: { labels: { color: '#94a3b8' } },
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { color: '#94a3b8', callback: (v) => formatPhp(v) } },
                        x: { ticks: xAxisTicks },
                    },
                    responsive: true,
                    maintainAspectRatio: false,
                };

                const pieChartOptions = {
                    plugins: {
                        tooltip: { callbacks: { label: (ctx) => formatPhp(ctx.parsed?.y ?? ctx.raw) } },
                        legend: { position: 'bottom', labels: { color: '#94a3b8' } },
                    },
                    scales: {
                        x: { display: false },
                        y: { display: false },
                    },
                    responsive: true,
                    maintainAspectRatio: false,
                };

                const commonBarOptions = {
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { color: '#94a3b8' } },
                        x: { ticks: xAxisTicks },
                    },
                    responsive: true,
                    maintainAspectRatio: false,
                };

                const initCharts = function() {
                    if (typeof Chart === 'undefined') return;

                    // Map status names to colors
                    const statusColorMap = {
                        'active': '#60A5FA',      // Blue
                        'paid': '#34D399',        // Green
                        'overdue': '#FBBF24',     // Amber
                        'closed': '#64748B',      // Slate
                        'cancelled': '#F87171',   // Red
                        'pending': '#A78BFA',     // Purple
                    };
                    const statusColors = statusLabels.map(label => statusColorMap[label] || '#94a3b8');

                    if (statusValues.length > 0) {
                        const el = document.getElementById('statusChart');
                        if (el) new Chart(el, {
                            type: 'doughnut',
                            data: { labels: statusLabels, datasets: [{ data: statusValues, backgroundColor: statusColors }] },
                            options: pieChartOptions,
                        });
                    }

                    if (monthlyCollectionLabels.length > 0) {
                        const el = document.getElementById('monthlyCollectionsChart');
                        if (el) new Chart(el, {
                            type: 'line',
                            data: { labels: monthlyCollectionLabels, datasets: [{ label: 'Collections', data: monthlyCollectionsValues, borderColor: '#10B981', backgroundColor: 'rgba(16,185,129,0.12)', fill: true, tension: 0.3 }] },
                            options: moneyTooltipOptions,
                        });
                    }

                    if (monthlyReleaseLabels.length > 0) {
                        const el = document.getElementById('monthlyReleasesChart');
                        if (el) new Chart(el, {
                            type: 'bar',
                            data: { labels: monthlyReleaseLabels, datasets: [{ label: 'Releases', data: monthlyReleasesValues, backgroundColor: '#3B82F6' }] },
                            options: moneyTooltipOptions,
                        });
                    }

                    if (moneyOverviewLabels.length > 0) {
                        const el = document.getElementById('moneyOverviewChart');
                        if (el) new Chart(el, {
                            type: 'bar',
                            data: { labels: moneyOverviewLabels, datasets: [{ label: 'Amount', data: moneyOverviewValues, backgroundColor: ['#60A5FA', '#34D399', '#FBBF24', '#10B981', '#F87171', '#A78BFA'] }] },
                            options: moneyTooltipOptions,
                        });
                    }

                    if (borrowerStatusLabels.length > 0) {
                        const el = document.getElementById('borrowerStatusChart');
                        if (el) new Chart(el, {
                            type: 'doughnut',
                            data: { labels: borrowerStatusLabels, datasets: [{ data: borrowerStatusValues, backgroundColor: ['#38BDF8', '#34D399', '#FBBF24', '#F87171', '#A78BFA'] }] },
                            options: pieChartOptions,
                        });
                    }

                    if (monthlyLabels.length > 0 && (inflowValues.some(v => v > 0) || outflowValues.some(v => v > 0))) {
                        const el = document.getElementById('cashFlowChart');
                        if (el) new Chart(el, {
                            type: 'line',
                            data: { labels: monthlyLabels, datasets: [{ label: 'Inflows', data: inflowValues, borderColor: '#10B981', backgroundColor: 'rgba(16,185,129,0.12)', fill: true, tension: 0.3 }, { label: 'Outflows', data: outflowValues, borderColor: '#EF4444', backgroundColor: 'rgba(239,68,68,0.12)', fill: true, tension: 0.3 }] },
                            options: moneyTooltipOptions,
                        });
                    }

                    if (topBorrowerLabels.length > 0) {
                        const el = document.getElementById('topBorrowersChart');
                        if (el) new Chart(el, {
                            type: 'bar',
                            data: { labels: topBorrowerLabels, datasets: [{ label: 'Outstanding balance', data: topBorrowerValues, backgroundColor: '#8B5CF6' }] },
                            options: Object.assign({ plugins: { tooltip: { callbacks: { label: (ctx) => formatPhp(ctx.parsed?.y ?? ctx.raw) } }, legend: { display: false } } }, commonBarOptions),
                        });
                    }

                    if (methodLabels.length > 0) {
                        const el = document.getElementById('methodChart');
                        if (el) new Chart(el, {
                            type: 'doughnut',
                            data: { labels: methodLabels, datasets: [{ data: methodTotals, backgroundColor: ['#60A5FA', '#34D399', '#FBBF24', '#F87171', '#A78BFA'] }] },
                            options: pieChartOptions,
                        });
                    }

                    if (paymentSizeLabels.length > 0) {
                        const el = document.getElementById('paymentSizeChart');
                        if (el) new Chart(el, {
                            type: 'bar',
                            data: { labels: paymentSizeLabels, datasets: [{ label: 'Payment count', data: paymentSizeValues, backgroundColor: '#F59E0B' }] },
                            options: commonBarOptions,
                        });
                    }

                    if (frequencyLabels.length > 0) {
                        const el = document.getElementById('frequencyChart');
                        if (el) new Chart(el, {
                            type: 'bar',
                            data: { labels: frequencyLabels, datasets: [{ label: 'Loan count', data: frequencyValues, backgroundColor: '#22D3EE' }] },
                            options: commonBarOptions,
                        });
                    }

                    if (cutoffLabels.length > 0 && cutoffValues.some(v => v > 0)) {
                        const el = document.getElementById('cutoffChart');
                        if (el) new Chart(el, {
                            type: 'bar',
                            data: { labels: cutoffLabels, datasets: [{ label: 'Expected collections', data: cutoffValues, backgroundColor: '#F59E0B' }] },
                            options: moneyTooltipOptions,
                        });
                    }
                };

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initCharts);
                } else {
                    initCharts();
                }
            })();
        </script>
    @endpush
</x-app-layout>
