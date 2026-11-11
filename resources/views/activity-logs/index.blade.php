<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-3xl font-semibold text-white">Activity logs</h2>
            <p class="mt-2 text-sm text-slate-400">Audit trail for borrower, loan, and payment actions.</p>
        </div>
    </x-slot>

    <section class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/90 shadow-lg shadow-slate-950/40">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm text-slate-200">
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-950/60 text-xs uppercase tracking-[0.18em] text-slate-500">
                        <th class="px-5 py-4 font-semibold">User</th>
                        <th class="px-5 py-4 font-semibold">Role</th>
                        <th class="px-5 py-4 font-semibold">Action</th>
                        <th class="px-5 py-4 font-semibold">Subject</th>
                        <th class="px-5 py-4 font-semibold">Description</th>
                        <th class="px-5 py-4 font-semibold">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($logs as $log)
                        <tr class="hover:bg-slate-800/40">
                            <td class="px-5 py-4 font-semibold text-white">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="px-5 py-4 text-slate-300">{{ ucfirst($log->user?->role ?? 'system') }}</td>
                            <td class="px-5 py-4">{{ ucfirst($log->action) }}</td>
                            <td class="px-5 py-4 text-slate-300">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</td>
                            <td class="max-w-2xl truncate px-5 py-4 text-slate-300">{{ $log->description }}</td>
                            <td class="px-5 py-4 text-slate-400">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center text-slate-500">No activity recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-800 px-5 py-4">{{ $logs->links() }}</div>
    </section>
</x-app-layout>
