<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-3xl font-semibold text-white">Notifications</h2>
            <p class="mt-2 text-sm text-slate-400">Recent alerts and activity messages.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-4">
        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-lg shadow-slate-950/40">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-white">Notifications</h2>
                    <p class="text-sm text-slate-400">Recent alerts and activity messages.</p>
                </div>
            </div>
            @if (session('status'))
                <div class="mt-4 rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm text-slate-200">
                    {{ session('status') }}
                </div>
            @endif
            <div class="mt-5 space-y-3">
                @forelse ($notifications as $notification)
                    <div class="notification-card rounded-2xl border {{ $notification->read_at ? 'border-slate-800 bg-slate-950/40' : 'border-blue-500/30 bg-blue-500/10' }} p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-sm font-semibold text-white">{{ $notification->data['message'] ?? 'Notification' }}</div>
                                <div class="mt-1 text-sm text-slate-500">{{ $notification->created_at->diffForHumans() }}</div>
                            </div>
                            @unless($notification->read_at)
                                <form class="notification-mark-read-form" method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                    @csrf
                                    <button type="submit" class="notification-mark-read-button rounded-2xl bg-blue-600 px-4 py-2 text-xs font-semibold text-white hover:bg-blue-500">Mark read</button>
                                </form>
                            @endunless
                        </div>
                        <div class="mt-3 text-sm text-slate-300">
                            Loan: {{ $notification->data['loan_id'] ?? '-' }}<br>
                            Amount: {{ isset($notification->data['amount']) ? number_format($notification->data['amount'], 2) : '-' }}
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-8 text-center text-sm text-slate-500">
                        No notifications yet.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 px-5 py-4 shadow-lg shadow-slate-950/40">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    {{ $notifications->links() }}
                </div>
                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.markAllRead') }}" class="inline-flex">
                        @csrf
                        <button type="submit" class="rounded-2xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-50">Mark all as read</button>
                    </form>
                @endif
            </div>
        </section>
    </div>

</x-app-layout>
