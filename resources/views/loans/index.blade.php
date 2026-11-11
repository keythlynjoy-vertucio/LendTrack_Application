<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-white">Loans</h2>
                <p class="mt-2 text-sm text-slate-400">Monitor loan references, balances, due dates, and status.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if (auth()->user()?->isAdmin())
                    <a href="{{ route('loans.trashed') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Deleted Loans</a>
                @endif
                @if (auth()->user()?->hasPermission('create_loans') || auth()->user()?->isAdmin())
                    <a href="{{ route('loans.create') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 hover:bg-blue-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v16m8-8H4"/></svg>
                        New loan
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-lg shadow-slate-950/40">
            <form method="GET" class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-medium text-slate-200">Search</label>
                    <input name="q" value="{{ $q }}" placeholder="Reference, borrower, or phone" class="field-input" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-200">Status</label>
                    <select name="status" class="field-input">
                        <option value="">All statuses</option>
                        @foreach (['pending','active','paid','overdue','closed','cancelled'] as $s)
                            <option value="{{ $s }}" @selected($status === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-500">Apply</button>
                    <a href="{{ route('loans.index') }}" class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Reset</a>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/90 shadow-lg shadow-slate-950/40">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-slate-200">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/60 text-xs uppercase tracking-[0.18em] text-slate-500">
                            <th class="px-5 py-4 font-semibold">Reference</th>
                            <th class="px-5 py-4 font-semibold">Borrower</th>
                            <th class="px-5 py-4 font-semibold">Due date</th>
                            <th class="px-5 py-4 text-right font-semibold">Payable</th>
                            <th class="px-5 py-4 text-right font-semibold">Paid</th>
                            <th class="px-5 py-4 font-semibold">Status</th>
                            <th class="px-5 py-4 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($loans as $loan)
                            @php($badge = match($loan->status) {
                                'paid' => 'bg-emerald-500/10 text-emerald-300 ring-emerald-500/30',
                                'overdue' => 'bg-rose-500/10 text-rose-300 ring-rose-500/30',
                                'active' => 'bg-amber-500/10 text-amber-300 ring-amber-500/30',
                                'pending' => 'bg-blue-500/10 text-blue-300 ring-blue-500/30',
                                'closed' => 'bg-slate-700/50 text-slate-300 ring-slate-600',
                                'cancelled' => 'bg-slate-800 text-slate-300 ring-slate-700',
                                default => 'bg-slate-800 text-slate-300 ring-slate-700',
                            })
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-5 py-4 font-semibold text-white whitespace-nowrap">{{ $loan->reference_no }}</td>
                                <td class="px-5 py-4">{{ $loan->borrower->last_name }}, {{ $loan->borrower->first_name }}</td>
                                <td class="px-5 py-4 text-slate-300 whitespace-nowrap">@dateYmd($loan->due_date)</td>
                                <td class="px-5 py-4 text-right text-white">@currency($loan->total_payable)</td>
                                <td class="px-5 py-4 text-right text-slate-300">@currency($loan->total_paid)</td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $badge }}">
                                        {{ $loan->status === 'overdue' ? 'Loan Overdue' : ucfirst($loan->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('loans.show', $loan) }}" class="inline-flex rounded-2xl bg-slate-800 px-3 py-2 text-xs font-semibold text-blue-300 hover:bg-slate-700">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-10 text-center text-slate-500">No loans found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-800 px-5 py-4">
                {{ $loans->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
