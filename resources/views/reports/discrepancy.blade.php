<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-white">Discrepancy Report</h2>
                <p class="mt-2 text-sm text-slate-400">Expected vs Actual Payment Discrepancy</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.discrepancy.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="rounded-2xl border border-slate-700 bg-slate-900/80 px-4 py-3 text-sm font-medium text-slate-100 hover:bg-slate-800">Export CSV</a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-lg shadow-slate-950/40">
            <form method="GET" class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-200">Borrower</label>
                    <select name="borrower_id" id="borrower_id" class="field-input">
                        <option value="">-- Select borrower --</option>
                        @foreach($borrowers as $b)
                            <option value="{{ $b->id }}" @if(isset($selectedBorrower) && $selectedBorrower->id == $b->id) selected @endif>{{ $b->first_name }} {{ $b->last_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-200">Loan</label>
                    <select name="loan_id" id="loan_id" class="field-input">
                        <option value="">-- Select loan --</option>
                        @foreach($loans as $l)
                            <option value="{{ $l->id }}" @if(isset($selectedLoan) && $selectedLoan->id == $l->id) selected @endif>{{ $l->reference_no }} - @currency($l->total_payable)</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-200">As of date</label>
                    <input type="date" name="as_of" value="{{ optional($asOf)->toDateString() ?? now()->toDateString() }}" class="field-input" />
                </div>

                <div class="md:col-span-3 flex items-end gap-2">
                    <button type="submit" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-500">Apply filters</button>
                    <a href="{{ route('reports.discrepancy') }}" class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Reset</a>
                </div>
            </form>
        </section>

        @if(!isset($selectedLoan))
            <section class="rounded-3xl border border-slate-800 bg-slate-950/50 p-6 text-sm text-slate-500">Please select a borrower and loan to view the discrepancy report.</section>
        @else
            <section class="grid gap-4 md:grid-cols-4">
                <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                    <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Total Payable</p>
                    <p class="mt-4 text-2xl font-semibold text-white">@currency($summary['total_payable'] ?? 0)</p>
                </div>
                <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                    <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Expected Paid By Date</p>
                    <p class="mt-4 text-2xl font-semibold text-white">@currency($summary['expected_paid_by'] ?? 0)</p>
                </div>
                <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                    <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Actual Paid By Date</p>
                    <p class="mt-4 text-2xl font-semibold text-white">@currency($summary['actual_paid_by'] ?? 0)</p>
                </div>
                <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                    <p class="text-sm uppercase tracking-[0.24em] text-slate-500">Current overdue / discrepancy</p>
                    <p class="mt-4 text-2xl font-semibold text-white">@currency($summary['discrepancy'] ?? 0)</p>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
                <p class="text-sm text-slate-400">{{ $message ?? '' }}</p>
            </section>

            <section class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/90 shadow-lg shadow-slate-950/40">
                <div class="border-b border-slate-800 px-5 py-4">
                    <h3 class="text-lg font-semibold text-white">Discrepancy details</h3>
                    <p class="mt-1 text-sm text-slate-400">Shows expected due dates and matched payments up to the selected date.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm text-slate-200">
                        <thead>
                            <tr class="border-b border-slate-800 bg-slate-950/60 text-xs uppercase tracking-[0.18em] text-slate-500">
                                <th class="px-5 py-4 font-semibold">Due Date</th>
                                <th class="px-5 py-4 font-semibold">Included in Computation</th>
                                <th class="px-5 py-4 font-semibold">Actual Payment Date</th>
                                <th class="px-5 py-4 text-right font-semibold">Expected Due</th>
                                <th class="px-5 py-4 text-right font-semibold">Actual Payment</th>
                                <th class="px-5 py-4 text-right font-semibold">Difference</th>
                                <th class="px-5 py-4 text-right font-semibold">Running Difference</th>
                                <th class="px-5 py-4 font-semibold">Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            @forelse($rows as $r)
                                <tr class="hover:bg-slate-800/40">
                                    <td class="px-5 py-4 text-slate-300">{{ $r['due_date'] }}</td>
                                    <td class="px-5 py-4 text-slate-300">{{ $r['included_status'] }}</td>
                                    <td class="px-5 py-4 text-slate-300">{{ $r['actual_payment_date'] }}</td>
                                    <td class="px-5 py-4 text-right font-semibold text-white">@currency($r['expected_due'])</td>
                                    <td class="px-5 py-4 text-right font-semibold text-white">@currency($r['actual_payment'])</td>
                                    <td class="px-5 py-4 text-right text-slate-300">@currency($r['difference'])</td>
                                    <td class="px-5 py-4 text-right text-slate-300">@currency($r['running_difference'])</td>
                                    <td class="px-5 py-4 text-slate-300">{{ $r['remarks'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-10 text-center text-slate-500">No schedule or payments available for this loan.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>

    @pushOnce('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const borrowerSelect = document.getElementById('borrower_id');
            const loanSelect = document.getElementById('loan_id');

            borrowerSelect.addEventListener('change', async function () {
                const borrowerId = this.value;

                // Clear loans if no borrower selected
                if (!borrowerId) {
                    loanSelect.innerHTML = '<option value="">-- Select loan --</option>';
                    return;
                }

                try {
                    const response = await fetch(`/reports/discrepancy/loans?borrower_id=${borrowerId}`);
                    if (!response.ok) throw new Error('Failed to load loans');

                    const loans = await response.json();

                    // Rebuild loan dropdown
                    loanSelect.innerHTML = '<option value="">-- Select loan --</option>';
                    loans.forEach(loan => {
                        const option = document.createElement('option');
                        option.value = loan.id;
                        option.textContent = loan.label;
                        loanSelect.appendChild(option);
                    });
                } catch (error) {
                    console.error('Error loading loans:', error);
                    loanSelect.innerHTML = '<option value="">Error loading loans</option>';
                }
            });
        });
    </script>
    @endPushOnce
</x-app-layout>
