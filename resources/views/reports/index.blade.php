<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-white">Reports</h2>
                <p class="mt-2 text-sm text-slate-400">Generate collection reports, inspect trends, and export payment data.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="rounded-2xl border border-slate-700 bg-slate-900/80 px-4 py-3 text-sm font-medium text-slate-100 hover:bg-slate-800">Export CSV</a>
                <a href="{{ route('reports.export', array_merge(request()->query(), ['format' => 'excel'])) }}" class="rounded-2xl border border-slate-700 bg-slate-900/80 px-4 py-3 text-sm font-medium text-slate-100 hover:bg-slate-800">Export Excel</a>
                <a href="{{ route('reports.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 hover:bg-blue-500">Export PDF</a>
            </div>
        </div>
    </x-slot>

    <style>
        /* Kept empty: removed Detailed insights tab styles */
    </style>

    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-lg shadow-slate-950/40">
            <form method="GET" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-200">From</label>
                    <input type="date" name="from" value="{{ $from }}" class="field-input" />
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-200">To</label>
                    <input type="date" name="to" value="{{ $to }}" class="field-input" />
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-500">Apply filters</button>
                    <a href="{{ route('reports.index') }}" class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Reset</a>
                </div>
            </form>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Loan Statuses</p>
                <p class="mt-4 text-3xl font-semibold text-white">{{ array_sum($statusCounts) }}</p>
                <p class="mt-2 text-sm text-slate-400">Loans grouped by status</p>
            </div>
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Period</p>
                <p class="mt-4 text-xl font-semibold text-white">{{ $from ?? 'Any' }} to {{ $to ?? 'Any' }}</p>
                <p class="mt-2 text-sm text-slate-400">Active report filter</p>
            </div>
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Payments</p>
                <p class="mt-4 text-3xl font-semibold text-white">{{ $payments->total() }}</p>
                <p class="mt-2 text-sm text-slate-400">Matching report records</p>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-2">
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Status breakdown</h3>
                        <p class="mt-1 text-sm text-slate-400">Current portfolio mix</p>
                    </div>
                    <p class="text-2xl font-semibold text-white">{{ array_sum($statusCounts) }}</p>
                </div>
                <div class="mt-5 grid items-start gap-4 lg:grid-cols-[132px_1fr]">
                    @if(array_sum($statusCounts) > 0)
                        <div class="h-32">
                            <canvas id="statusChart" height="128"></canvas>
                        </div>
                        <div class="space-y-2.5">
                            @php($statusTotal = max(array_sum($statusCounts), 1))
                            @foreach ($statusCounts as $status => $total)
                                @php($percent = round(($total / $statusTotal) * 100))
                                <div>
                                    <div class="flex items-center justify-between gap-3 text-sm">
                                        @php($statusLabel = $status === 'overdue' ? 'Loan Overdue' : ucfirst($status))
                                <span class="font-medium text-slate-200">{{ $statusLabel }}</span>
                                        <span class="text-slate-400">{{ $total }} loans</span>
                                    </div>
                                    <div class="mt-2 h-2 rounded-full bg-slate-800">
                                        <div class="h-2 rounded-full bg-blue-500" style="width: {{ $percent }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="col-span-2 flex h-40 items-center justify-center rounded-2xl border border-slate-800 bg-slate-950/50">
                            <p class="text-sm text-slate-500">No loan status data available.</p>
                        </div>
                    @endif
                </div>
            </div>
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Collections trend</h3>
                        <p class="mt-1 text-sm text-slate-400">Monthly payment totals</p>
                    </div>
                    <p class="text-2xl font-semibold text-white">{{ count($monthlyCollections) }}</p>
                </div>
                <div class="mt-5 grid items-start gap-4 lg:grid-cols-[180px_1fr]">
                    @if(count($monthlyCollections) > 0)
                        <div class="h-32">
                            <canvas id="monthlyChart" height="128"></canvas>
                        </div>
                        <div class="space-y-2.5">
                            @php($maxMonthlyCollection = max(array_values($monthlyCollections ?: [1])))
                            @foreach ($monthlyCollections as $month => $total)
                                @php($percent = $maxMonthlyCollection > 0 ? round(($total / $maxMonthlyCollection) * 100) : 0)
                                <div class="grid grid-cols-[5.5rem_1fr_7rem] items-center gap-3 text-sm">
                                    <span class="text-slate-400">{{ $month }}</span>
                                    <div class="h-2 rounded-full bg-slate-800">
                                        <div class="h-2 rounded-full bg-emerald-500" style="width: {{ $percent }}%"></div>
                                    </div>
                                    <span class="text-right font-medium text-slate-200">@currency($total)</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="col-span-2 flex h-40 items-center justify-center rounded-2xl border border-slate-800 bg-slate-950/50">
                            <p class="text-sm text-slate-500">No collection data available for this period.</p>
                        </div>
                    @endif
                </div>
            </div>
        </section>

        <!-- Detailed insights section removed as requested. -->

        <section class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/90 shadow-lg shadow-slate-950/40">
            <div class="border-b border-slate-800 px-5 py-4">
                <h3 class="text-lg font-semibold text-white">Payments</h3>
                <p class="mt-1 text-sm text-slate-400">Showing {{ $payments->firstItem() ?? 0 }} to {{ $payments->lastItem() ?? 0 }} of {{ $payments->total() }} matching records.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-slate-200">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/60 text-xs uppercase tracking-[0.18em] text-slate-500">
                            <th class="px-5 py-4 font-semibold">Payment Date</th>
                            <th class="px-5 py-4 font-semibold">Loan</th>
                            <th class="px-5 py-4 text-right font-semibold">Amount</th>
                            @if($hasPenaltyColumn)
                                <th class="px-5 py-4 text-right font-semibold">Penalty</th>
                            @endif
                            <th class="px-5 py-4 font-semibold">Method</th>
                            <th class="px-5 py-4 font-semibold">User</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($payments as $payment)
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-5 py-4 text-slate-300">@dateYmd($payment->paid_at)</td>
                                <td class="px-5 py-4 font-semibold text-white">{{ $payment->loan->reference_no }}</td>
                                  <td class="px-5 py-4 text-right font-semibold text-white">@currency($payment->amount)</td>
                                @if($hasPenaltyColumn)
                                     <td class="px-5 py-4 text-right text-amber-300">@currency($payment->penalty_paid ?? 0)</td>
                                @endif
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full bg-blue-500/10 px-3 py-1 text-xs font-semibold uppercase text-blue-300 ring-1 ring-blue-500/30">{{ $payment->method }}</span>
                                </td>
                                <td class="px-5 py-4 text-slate-300">{{ $payment->user?->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $hasPenaltyColumn ? 6 : 5 }}" class="px-5 py-10 text-center text-slate-500">No payments matched your filters.</td>
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

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            // Initialize Charts
            const initializeCharts = () => {
                // Map status names to colors based on the statusOrder in LoanMetricsService
                const statusColorMap = {
                    'active': '#60A5FA',      // Blue
                    'paid': '#34D399',        // Green
                    'overdue': '#FBBF24',     // Amber
                    'closed': '#64748B',      // Slate
                    'cancelled': '#F87171',   // Red
                    'pending': '#A78BFA',     // Purple
                };
                
                const statusLabels = {{ json_encode(array_keys($statusCounts)) }};
                const statusValues = {{ json_encode(array_values($statusCounts)) }};
                const statusColors = statusLabels.map(label => statusColorMap[label] || '#94a3b8');

                const statusData = {
                    labels: statusLabels,
                    datasets: [{
                        data: statusValues,
                        backgroundColor: statusColors,
                    }]
                };

                const monthlyData = {
                    labels: {{ json_encode(array_keys($monthlyCollections)) }},
                    datasets: [{
                        label: 'Collections',
                        data: {{ json_encode(array_values($monthlyCollections)) }},
                        borderColor: '#3B82F6',
                        backgroundColor: 'rgba(59, 130, 246, 0.15)',
                        fill: true,
                        tension: 0.3,
                    }]
                };

                if (window.Chart) {
                    if (statusData.datasets[0].data.length > 0 && document.getElementById('statusChart')) {
                        new Chart(document.getElementById('statusChart'), { type: 'doughnut', data: statusData, options: { plugins: { legend: { position: 'bottom', labels: { color: '#94a3b8' } } } } });
                    }
                    if (monthlyData.datasets[0].data.length > 0 && document.getElementById('monthlyChart')) {
                        new Chart(document.getElementById('monthlyChart'), { type: 'line', data: monthlyData, options: { scales: { y: { beginAtZero: true, ticks: { color: '#94a3b8' } }, x: { ticks: { color: '#94a3b8' } } } } });
                    }
                }
            };

            // Wait for DOM to be ready, then initialize charts only
            function initializeReports() {
                initializeCharts();
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initializeReports);
            } else {
                initializeReports();
            }
        </script>
    @endpush
</x-app-layout>
