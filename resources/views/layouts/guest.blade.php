<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="auth-shell">
        <div class="min-h-screen flex items-center justify-center px-4 py-10">
            <div class="w-full max-w-6xl auth-card overflow-hidden rounded-[2rem]">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
