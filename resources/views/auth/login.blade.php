<x-guest-layout>
    <div class="relative grid min-h-[680px] lg:grid-cols-[1.05fr_0.95fr]">
        <section class="relative hidden min-h-full overflow-hidden bg-slate-950 px-10 py-12 lg:flex lg:flex-col lg:justify-between">
            <div class="absolute inset-0 opacity-80">
                <div class="absolute left-10 top-12 h-56 w-56 rounded-full bg-blue-600/20 blur-3xl"></div>
                <div class="absolute bottom-10 right-8 h-72 w-72 rounded-full bg-emerald-500/10 blur-3xl"></div>
            </div>

            <div class="relative">
                <div class="inline-flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-lg font-bold text-white">LT</div>
                    <div>
                        <p class="text-xl font-semibold text-white">LendTrack</p>
                        <p class="text-sm text-slate-400">Lending operations workspace</p>
                    </div>
                </div>

                <div class="mt-16 max-w-lg">
                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-blue-300">Portfolio control</p>
                    <h1 class="mt-4 text-5xl font-bold leading-tight text-white">Track borrowers, loans, and collections with less friction.</h1>
                    <p class="mt-5 text-base leading-7 text-slate-300">Built for small lending teams that need clean records, payment visibility, reports, and daily monitoring in one place.</p>
                </div>
            </div>

            <div class="relative grid grid-cols-3 gap-3">
                <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
                    <p class="text-2xl font-semibold text-white">3</p>
                    <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">Core modules</p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
                    <p class="text-2xl font-semibold text-white">PDF</p>
                    <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">Reports</p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
                    <p class="text-2xl font-semibold text-white">RBAC</p>
                    <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">Access</p>
                </div>
            </div>
        </section>

        <section class="relative flex items-center justify-center px-5 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <div class="mb-8 lg:hidden">
                    <div class="inline-flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-lg font-bold text-white">LT</div>
                        <div>
                            <p class="text-xl font-semibold text-white">LendTrack</p>
                            <p class="text-sm text-slate-400">Lending operations workspace</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-800 bg-slate-950/80 p-7 shadow-2xl shadow-slate-950/50 sm:p-8">
                    <div class="mb-7">
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-300">Welcome back</p>
                        <h2 class="mt-3 text-3xl font-bold text-white">Sign in</h2>
                        <p class="mt-2 text-sm text-slate-400">Use your staff or admin account to continue.</p>
                    </div>

                    <x-auth-session-status class="mb-4" :status="session('status')" />

                    <form x-data="{ showPassword: false }" method="POST" action="{{ route('login') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-slate-200">Email address</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="field-input" placeholder="Enter your email" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2 text-sm text-rose-300" />
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <label for="password" class="mb-2 block text-sm font-medium text-slate-200">Password</label>
                                <p class="text-xs text-slate-500">Hidden by default</p>
                            </div>
                            <div class="relative">
                                <input id="password" name="password" type="password" :type="showPassword ? 'text' : 'password'" required autocomplete="current-password" class="field-input pr-20" placeholder="Enter your password" />
                                <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 mr-2 flex items-center rounded-full px-2.5 text-[10px] font-medium text-slate-300 transition hover:text-white" aria-label="Toggle password visibility">
                                    <span x-text="showPassword ? 'Hide' : 'Show'"></span>
                                </button>
                            </div>
                            <x-input-error :messages="$errors->get('password')" class="mt-2 text-sm text-rose-300" />
                            {{-- Demo note removed from UI to avoid exposing credentials in production. --}}
                        </div>

                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <label class="inline-flex items-center gap-2 text-slate-300">
                                <input id="remember_me" name="remember" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-800 text-blue-500 focus:ring-blue-500" />
                                <span class="text-sm">Remember me</span>
                            </label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-sm font-medium text-blue-300 hover:text-blue-200">Forgot password?</a>
                            @endif
                        </div>

                        <button type="submit" class="btn-animated w-full rounded-2xl bg-gradient-to-r from-blue-600 to-cyan-500 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/10 transition hover:from-blue-500 hover:to-cyan-400">Sign in</button>
                    </form>

                    @if (Route::has('register'))
                        <p class="mt-6 text-center text-sm text-slate-400">
                            New to LendTrack?
                            <a href="{{ route('register') }}" class="font-semibold text-blue-300 hover:text-blue-200">Create an account</a>
                        </p>
                    @endif
                </div>
            </div>
        </div>
        </section>
    </div>

    <script>
        // Fallback toggle for password visibility when Alpine isn't available.
        document.addEventListener('DOMContentLoaded', function () {
            var pwInput = document.getElementById('password');
            var btn = document.querySelector('button[aria-label="Toggle password visibility"]');
            if (!pwInput || !btn) return;

            btn.addEventListener('click', function (e) {
                // If Alpine is present, let it update the state but keep text in sync.
                if (typeof Alpine !== 'undefined') {
                    setTimeout(function () {
                        var isText = pwInput.type === 'text';
                        var span = btn.querySelector('span');
                        if (span) span.textContent = isText ? 'Hide' : 'Show';
                    }, 1);
                    return;
                }

                var isPassword = pwInput.type === 'password';
                pwInput.type = isPassword ? 'text' : 'password';
                var span = btn.querySelector('span');
                if (span) span.textContent = isPassword ? 'Hide' : 'Show';
            });
        });
    </script>
</x-guest-layout>
