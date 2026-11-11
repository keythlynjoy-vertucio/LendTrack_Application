<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-3xl font-semibold text-white">Trashed borrowers</h2>
                <p class="mt-2 text-sm text-slate-400">Restore borrowers that were soft deleted.</p>
            </div>
            <a href="{{ route('borrowers.index') }}" class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800">Back to borrowers</a>
        </div>
    </x-slot>

    <section class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/90 shadow-lg shadow-slate-950/40">
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm text-slate-200">
                <thead>
                    <tr class="border-b border-slate-800 bg-slate-950/60 text-xs uppercase tracking-[0.18em] text-slate-500">
                        <th class="px-5 py-4 font-semibold">Name</th>
                        <th class="px-5 py-4 font-semibold">Deleted at</th>
                        <th class="px-5 py-4 text-right font-semibold">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @forelse ($borrowers as $borrower)
                        <tr class="hover:bg-slate-800/40">
                            <td class="px-5 py-4 font-semibold text-white">{{ $borrower->last_name }}, {{ $borrower->first_name }}</td>
                            <td class="px-5 py-4 text-slate-300">{{ $borrower->deleted_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-5 py-4 text-right">
                                <form method="POST" action="{{ route('borrowers.restore', $borrower) }}">
                                    @csrf
                                    <button class="rounded-2xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-500">Restore</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-5 py-10 text-center text-slate-500">No trashed borrowers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-800 px-5 py-4">{{ $borrowers->links() }}</div>
    </section>
</x-app-layout>
