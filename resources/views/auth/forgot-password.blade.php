<x-guest-layout>
    <div class="relative grid min-h-[680px] lg:grid-cols-[1.05fr_0.95fr]">
        <section class="relative hidden min-h-full overflow-hidden bg-slate-950 px-10 py-12 lg:flex lg:flex-col lg:justify-between">
            <div class="absolute inset-0 opacity-80">
                <div class="absolute left-10 top-12 h-56 w-56 rounded-full bg-blue-600/20 blur-3xl"></div>
                <div class="absolute bottom-10 right-8 h-72 w-72 rounded-full bg-slate-800/20 blur-3xl"></div>
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
                    <p class="text-sm font-semibold uppercase tracking-[0.24em] text-blue-300">Account recovery</p>
                    <h1 class="mt-4 text-5xl font-bold leading-tight text-white">Reset your password</h1>
                    <p class="mt-5 text-base leading-7 text-slate-300">Don't worry! We'll send you a secure link to create a new password and get back to managing your lending operations.</p>
                </div>
            </div>

            <div class="relative grid grid-cols-3 gap-3">
                <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
                    <p class="text-2xl font-semibold text-white">1</p>
                    <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">Enter email</p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
                    <p class="text-2xl font-semibold text-white">2</p>
                    <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">Check inbox</p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4">
                    <p class="text-2xl font-semibold text-white">3</p>
                    <p class="mt-1 text-xs uppercase tracking-[0.18em] text-slate-500">New password</p>
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
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-blue-300">Password recovery</p>
                        <h2 class="mt-3 text-3xl font-bold text-white">Reset password</h2>
                        <p class="mt-2 text-sm text-slate-400">Enter your email address and we'll send you a secure link to create a new password.</p>
                    </div>

                    <x-auth-session-status class="mb-4" :status="session('status')" />

                    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-slate-200">Email address</label>
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="field-input" placeholder="admin@lendtrack.test" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2 text-sm text-rose-300" />
                        </div>

                        <button type="submit" class="btn-animated w-full rounded-2xl bg-gradient-to-r from-blue-600 to-cyan-500 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-500/10 transition hover:from-blue-500 hover:to-cyan-400">Send Reset Link</button>
                    </form>

                    <p class="mt-6 text-center text-sm text-slate-400">
                        Remembered your password?
                        <a href="{{ route('login') }}" class="font-semibold text-blue-300 hover:text-blue-200">Sign in</a>
                    </p>
                </div>
            </div>
        </section>
    </div>
</x-guest-layout>
