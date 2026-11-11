<x-guest-layout>
    <div class="relative grid min-h-[680px] lg:grid-cols-[0.95fr_1.05fr]">
        <section class="relative flex items-center justify-center px-5 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <div class="mb-8">
                    <div class="inline-flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-600 text-lg font-bold text-white">LT</div>
                        <div>
                            <p class="text-xl font-semibold text-white">LendTrack</p>
                            <p class="text-sm text-slate-400">Create your lending workspace account</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-800 bg-slate-950/80 p-7 shadow-2xl shadow-slate-950/50 sm:p-8">
                    <div class="mb-7">
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-emerald-300">Get started</p>
                        <h2 class="mt-3 text-3xl font-bold text-white">Create account</h2>
                        <p class="mt-2 text-sm text-slate-400">New accounts are created as staff users.</p>
                    </div>

                    <form x-data="{ showPassword: false, showConfirm: false }" method="POST" action="{{ route('register') }}" class="space-y-5">
                        @csrf

                        <div>
                            <label for="name" class="mb-2 block text-sm font-medium text-slate-200">Full name</label>
                            <input id="name" class="field-input" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Juan Dela Cruz" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2 text-sm text-rose-300" />
                        </div>

                        <div>
                            <label for="email" class="mb-2 block text-sm font-medium text-slate-200">Email address</label>
                            <input id="email" class="field-input" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="you@example.com" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2 text-sm text-rose-300" />
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <label for="password" class="mb-2 block text-sm font-medium text-slate-200">Password</label>
                                <p class="text-xs text-slate-500">Hidden by default</p>
                            </div>
                            <div class="relative">
                                <input id="password" class="field-input pr-20" :type="showPassword ? 'text' : 'password'" name="password" required autocomplete="new-password" placeholder="Create a password" />
                                <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 mr-2 flex items-center rounded-full px-2.5 text-[10px] font-medium text-slate-300 transition hover:text-white" aria-label="Toggle password visibility">
                                    <span x-text="showPassword ? 'Hide' : 'Show'"></span>
                                </button>
                            </div>
                            <x-input-error :messages="$errors->get('password')" class="mt-2 text-sm text-rose-300" />
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <label for="password_confirmation" class="mb-2 block text-sm font-medium text-slate-200">Confirm password</label>
                                <p class="text-xs text-slate-500">Hidden by default</p>
                            </div>
                            <div class="relative">
                                <input id="password_confirmation" class="field-input pr-20" :type="showConfirm ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password" placeholder="Confirm your password" />
                                <button type="button" @click="showConfirm = !showConfirm" class="absolute inset-y-0 right-0 mr-2 flex items-center rounded-full px-2.5 text-[10px] font-medium text-slate-300 transition hover:text-white" aria-label="Toggle password visibility">
                                    <span x-text="showConfirm ? 'Hide' : 'Show'"></span>
                                </button>
                            </div>
                            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-sm text-rose-300" />
                        </div>

                        <button type="submit" class="btn-animated w-full rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white hover:bg-emerald-500">Create account</button>
                    </form>

                    <p class="mt-6 text-center text-sm text-slate-400">
                        Already have an account?
                        <a href="{{ route('login') }}" class="font-semibold text-blue-300 hover:text-blue-200">Sign in</a>
                    </p>
                </div>
            </div>
        </section>

        <section class="relative hidden min-h-full overflow-hidden bg-slate-950 px-10 py-12 lg:flex lg:flex-col lg:justify-between">
            <div class="absolute inset-0 opacity-80">
                <div class="absolute left-12 top-16 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl"></div>
                <div class="absolute bottom-10 right-10 h-72 w-72 rounded-full bg-blue-600/20 blur-3xl"></div>
            </div>

            <div class="relative max-w-lg">
                <p class="text-sm font-semibold uppercase tracking-[0.24em] text-emerald-300">Staff onboarding</p>
                <h1 class="mt-4 text-5xl font-bold leading-tight text-white">Start recording borrowers, loans, and payments today.</h1>
                <p class="mt-5 text-base leading-7 text-slate-300">Registration gives users staff access. Admin-only actions stay protected inside the system.</p>
            </div>

            <div class="relative space-y-3">
                <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5">
                    <p class="text-sm font-semibold text-white">Built-in protections</p>
                    <p class="mt-1 text-sm text-slate-400">Validation, CSRF protection, and password hashing are handled by Laravel.</p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5">
                    <p class="text-sm font-semibold text-white">Role-aware workflow</p>
                    <p class="mt-1 text-sm text-slate-400">Staff can work with records while destructive actions remain admin-only.</p>
                </div>
            </div>
        </div>
        </section>
    </div>
</x-guest-layout>
