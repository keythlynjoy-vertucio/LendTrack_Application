<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-white">Borrowers</h2>
                <p class="mt-2 text-sm text-slate-400">Manage borrower profiles, contact details, and account status.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if (auth()->user()?->isAdmin())
                    <a href="{{ route('borrowers.trashed') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Deleted Borrowers</a>
                @endif
                @if (auth()->user()?->hasPermission('manage_borrowers') || auth()->user()?->isAdmin())
                    <a href="{{ route('borrowers.create') }}" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/20 hover:bg-blue-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 4v16m8-8H4"/></svg>
                        New borrower
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
                    <input name="q" value="{{ $q }}" placeholder="Name, phone, or email" class="field-input" />
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-200">Status</label>
                    <select name="status" class="field-input">
                        <option value="all" @selected($status === 'all')>All statuses</option>
                        <option value="active" @selected($status === 'active')>Active</option>
                        <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-500">Apply</button>
                    <a href="{{ route('borrowers.index') }}" class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Reset</a>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/90 shadow-lg shadow-slate-950/40">
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm text-slate-200">
                    <thead>
                        <tr class="border-b border-slate-800 bg-slate-950/60 text-xs uppercase tracking-[0.18em] text-slate-500">
                            <th class="px-5 py-4 font-semibold">Name</th>
                            <th class="px-5 py-4 font-semibold">Contact</th>
                            <th class="px-5 py-4 font-semibold">Status</th>
                            <th class="px-5 py-4 text-right font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @forelse ($borrowers as $borrower)
                            <tr class="hover:bg-slate-800/40">
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        @if($borrower->photo_url)
                                            <img src="{{ $borrower->photo_url }}" alt="{{ $borrower->first_name }} {{ $borrower->last_name }}" class="h-10 w-10 rounded-xl object-cover ring-1 ring-slate-700" />
                                        @else
                                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-800 ring-1 ring-slate-700">
                                                <span class="text-sm font-semibold uppercase tracking-[0.06em] text-slate-100">{{ $borrower->initials }}</span>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-semibold text-white">{{ $borrower->last_name }}, {{ $borrower->first_name }}</div>
                                            <div class="mt-1 text-xs text-slate-500">Borrower #{{ $borrower->id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <div>{{ $borrower->phone ?? '-' }}</div>
                                    <div class="mt-1 text-slate-500">{{ $borrower->email ?? '-' }}</div>
                                </td>
                                <td class="px-5 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $borrower->status === 'active' ? 'bg-emerald-500/10 text-emerald-300 ring-1 ring-emerald-500/30' : 'bg-slate-800 text-slate-300 ring-1 ring-slate-700' }}">
                                        {{ ucfirst($borrower->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-4 text-right">
                                    <a href="{{ route('borrowers.show', $borrower) }}" class="inline-flex rounded-2xl bg-slate-800 px-3 py-2 text-xs font-semibold text-blue-300 hover:bg-slate-700">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-slate-500">No borrowers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-800 px-5 py-4">
                {{ $borrowers->links() }}
            </div>
        </section>
    </div>
</x-app-layout>
