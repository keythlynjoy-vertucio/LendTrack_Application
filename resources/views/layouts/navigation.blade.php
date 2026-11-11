<aside class="hidden h-screen shrink-0 lg:sticky lg:top-0 lg:flex lg:w-72 xl:w-80 flex-col bg-slate-950 border-r border-slate-800 text-slate-200">
        <div class="px-6 py-8">
            <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-3 rounded-3xl bg-slate-900/90 px-4 py-4 shadow-lg shadow-slate-950/40 border border-slate-800">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-lg font-bold text-white">LT</div>
                <div>
                    <p class="text-lg font-semibold">LendTrack</p>
                    <p class="text-xs text-slate-500">Local Lending Manager</p>
                </div>
            </a>
        </div>

        <nav class="flex-1 px-4">
            <ul class="space-y-1">
                <li>
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-slate-900/80 @if(request()->routeIs('dashboard')) bg-slate-900/80 text-white @else text-slate-300 @endif">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M13 5v6h6"/></svg>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="{{ route('payments.index') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-slate-900/80 @if(request()->routeIs('payments.*')) bg-slate-900/80 text-white @else text-slate-300 @endif">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-10v10"/></svg>
                        Payments
                    </a>
                </li>
                <li>
                    <a href="{{ route('borrowers.index') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-slate-900/80 @if(request()->routeIs('borrowers.*')) bg-slate-900/80 text-white @else text-slate-300 @endif">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 16.121A3 3 0 006.5 21h11a3 3 0 001.379-4.879l-5.5-5.5a3 3 0 00-4.242 0l-5.5 5.5z"/></svg>
                        Borrowers
                    </a>
                </li>
                <li>
                    <a href="{{ route('loans.index') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-slate-900/80 @if(request()->routeIs('loans.*')) bg-slate-900/80 text-white @else text-slate-300 @endif">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Loans
                    </a>
                </li>
                @if (Auth::user()?->isAdmin())
                    <li>
                        <a href="{{ route('reports.index') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-slate-900/80 @if(request()->routeIs('reports.*')) bg-slate-900/80 text-white @else text-slate-300 @endif">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 17v-6a2 2 0 012-2h2a2 2 0 012 2v6"/><path d="M17 17V5a2 2 0 00-2-2H9a2 2 0 00-2 2v12"/></svg>
                            Reports
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('statistics.index') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-slate-900/80 @if(request()->routeIs('statistics.*')) bg-slate-900/80 text-white @else text-slate-300 @endif">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l3-3 2 2 5-5"/></svg>
                            Analytics
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('reports.discrepancy') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-slate-900/80 @if(request()->routeIs('reports.discrepancy')) bg-slate-900/80 text-white @else text-slate-300 @endif">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 10h4v11H3zM10 3h11v18H10z"/></svg>
                            Discrepancy Report
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('activity-logs.index') }}" class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-slate-900/80 @if(request()->routeIs('activity-logs.*')) bg-slate-900/80 text-white @else text-slate-300 @endif">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 2"/><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1021 12"/></svg>
                            Activity logs
                        </a>
                    </li>
                @endif
            </ul>
        </nav>

        <div class="px-4 py-6 border-t border-slate-800">
            <div class="rounded-3xl bg-slate-900/90 border border-slate-800 p-4">
                <div class="flex items-center gap-3">
                    <div class="h-12 w-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center font-bold">{{ strtoupper(substr(Auth::user()->name, 0, 2)) }}</div>
                    <div>
                        <p class="text-sm font-semibold text-white">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-slate-500">{{ Auth::user()->email }}</p>
                        <p class="mt-1 text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-slate-500">{{ Auth::user()->role }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-4">
                    @csrf
                    <button type="submit" class="w-full rounded-2xl bg-slate-800 px-4 py-3 text-sm font-semibold text-blue-300 hover:bg-slate-700">Logout</button>
                </form>
            </div>
        </div>
</aside>

<div x-data="{ open: false }" class="lg:hidden bg-slate-950 border-b border-slate-800 px-4 py-4">
    <div class="flex items-center justify-between gap-4">
        <a href="{{ route('dashboard') }}" class="inline-flex min-w-0 items-center gap-3 text-white">
            <div class="h-10 w-10 shrink-0 rounded-2xl bg-blue-600 flex items-center justify-center text-base font-bold">LT</div>
            <div class="min-w-0">
                <p class="truncate text-lg font-semibold">LendTrack</p>
                <p class="truncate text-xs text-slate-400">Local Lending Manager</p>
            </div>
        </a>
        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ route('notifications.index') }}" class="relative inline-flex h-10 w-10 items-center justify-center rounded-2xl text-slate-300 hover:bg-slate-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 10-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
                @php $unreadCount = auth()->user()->unreadNotifications()->count(); @endphp
                @if ($unreadCount > 0)
                    <span class="notifications-unread-count-badge absolute top-0 right-0 inline-flex items-center justify-center h-5 w-5 text-xs font-semibold text-white bg-red-500 rounded-full">{{ $unreadCount }}</span>
                @endif
            </a>
            <a href="{{ route('profile.edit') }}" class="hidden max-w-32 truncate rounded-2xl px-3 py-2 text-sm text-slate-300 hover:bg-slate-800 sm:inline-flex">{{ Auth::user()->name }}</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="rounded-2xl px-3 py-2 text-sm text-slate-300 hover:bg-slate-800">Logout</button>
            </form>
            <button type="button" @click="open = !open" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-800 bg-slate-900 text-slate-300 hover:bg-slate-800">
                <span class="sr-only">Toggle navigation</span>
                <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
                <svg x-show="open" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <div x-show="open" x-transition class="mt-4 space-y-4 rounded-3xl border border-slate-800 bg-slate-950/95 p-4">
        <nav class="space-y-1">
            <a href="{{ route('dashboard') }}" class="block rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-slate-900/80 @if(request()->routeIs('dashboard')) bg-slate-900/80 text-white @else text-slate-300 @endif">Dashboard</a>
            <a href="{{ route('payments.index') }}" class="block rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-slate-900/80 @if(request()->routeIs('payments.*')) bg-slate-900/80 text-white @else text-slate-300 @endif">Payments</a>
            <a href="{{ route('borrowers.index') }}" class="block rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-slate-900/80 @if(request()->routeIs('borrowers.*')) bg-slate-900/80 text-white @else text-slate-300 @endif">Borrowers</a>
            <a href="{{ route('loans.index') }}" class="block rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-slate-900/80 @if(request()->routeIs('loans.*')) bg-slate-900/80 text-white @else text-slate-300 @endif">Loans</a>
            @if (Auth::user()?->isAdmin())
                <a href="{{ route('reports.index') }}" class="block rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-slate-900/80 @if(request()->routeIs('reports.*')) bg-slate-900/80 text-white @else text-slate-300 @endif">Reports</a>
                <a href="{{ route('statistics.index') }}" class="block rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-slate-900/80 @if(request()->routeIs('statistics.*')) bg-slate-900/80 text-white @else text-slate-300 @endif">Analytics</a>
                <a href="{{ route('reports.discrepancy') }}" class="block rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-slate-900/80 @if(request()->routeIs('reports.discrepancy')) bg-slate-900/80 text-white @else text-slate-300 @endif">Discrepancy Report</a>
                <a href="{{ route('activity-logs.index') }}" class="block rounded-2xl px-4 py-3 text-sm font-medium transition hover:bg-slate-900/80 @if(request()->routeIs('activity-logs.*')) bg-slate-900/80 text-white @else text-slate-300 @endif">Activity logs</a>
            @endif
        </nav>

    </div>
</div>
