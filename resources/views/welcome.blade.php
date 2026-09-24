<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,600&display=swap" rel="stylesheet" />

        <!-- Styles -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased font-sans">
        <div class="bg-gray-50 text-black/50">
            <div class="relative min-h-screen flex flex-col items-center justify-center">
                <div class="relative w-full max-w-2xl px-6 text-center">
                    <header class="flex flex-col items-center gap-4">
                        <div class="text-5xl">💰</div>
                        <h1 class="text-3xl font-semibold text-gray-900">Sistem Petty Cash &amp; Reimbursement</h1>
                        <p class="text-gray-600 max-w-lg">
                            Portal internal Digitaliz untuk pengajuan kas kecil dan reimbursement —
                            pengajuan, validasi Admin, pemrosesan Finance, hingga laporan dalam satu tempat.
                        </p>
                    </header>

                    <main class="mt-10">
                        @auth
                            <a href="{{ route('dashboard') }}"
                               class="inline-flex items-center rounded-md bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                Buka Dashboard
                            </a>
                        @else
                            <div class="flex items-center justify-center gap-4">
                                <a href="{{ route('login') }}"
                                   class="inline-flex items-center rounded-md bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                    Masuk
                                </a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}"
                                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-6 py-3 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50">
                                        Daftar
                                    </a>
                                @endif
                            </div>
                        @endauth
                    </main>
                </div>
            </div>
        </div>
    </body>
</html>