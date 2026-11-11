<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-3xl font-semibold text-white">Profile</h2>
            <p class="mt-2 text-sm text-slate-400">Manage your account information, password, and session access.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-5">
        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
            @include('profile.partials.update-profile-information-form')
        </section>

        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-lg shadow-slate-950/40">
            @include('profile.partials.update-password-form')
        </section>

        <section class="rounded-3xl border border-rose-500/20 bg-rose-950/20 p-6 shadow-lg shadow-slate-950/40">
            @include('profile.partials.delete-user-form')
        </section>
    </div>
</x-app-layout>
