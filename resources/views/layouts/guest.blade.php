<!DOCTYPE html>
<html lang="id" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' · ' : '' }}Petty Cash Digitaliz</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="h-full bg-white font-sans antialiased">
        <div class="flex min-h-full">
            {{-- Brand panel --}}
            <div class="relative hidden w-[46%] overflow-hidden bg-brand-950 lg:flex lg:flex-col lg:justify-between lg:p-12">
                <div class="pointer-events-none absolute -top-32 -right-32 size-[28rem] rounded-full bg-brand-600/30 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-40 -left-24 size-[26rem] rounded-full bg-sky-500/20 blur-3xl"></div>

                <a href="/" class="relative" wire:navigate>
                    <x-application-logo with-text dark />
                </a>

                <div class="relative">
                    <h2 class="text-3xl leading-tight font-bold tracking-tight text-white">
                        Satu tempat untuk semua<br>pengajuan kas kecil &amp; reimbursement.
                    </h2>
                    <p class="mt-3 max-w-md text-brand-200">
                        Ajukan, pantau status, dan selesaikan pencairan tanpa bolak-balik chat.
                    </p>

                    <ol class="mt-10 space-y-4">
                        @foreach ([
                            ['document-plus', 'Pengaju mengisi form & melampirkan berkas'],
                            ['clipboard-check', 'Admin memvalidasi & memberi kode anggaran'],
                            ['banknotes', 'Finance mencairkan & mengunggah bukti transfer'],
                            ['check-circle', 'Pengaju mendapat notifikasi WhatsApp otomatis'],
                        ] as [$icon, $text])
                            <li class="flex items-center gap-3 text-sm text-brand-100">
                                <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-white/10 text-white ring-1 ring-white/15">
                                    <x-icon :name="$icon" class="size-4.5" />
                                </span>
                                {{ $text }}
                            </li>
                        @endforeach
                    </ol>
                </div>

                <p class="relative text-xs text-brand-300/70">© {{ now()->year }} Digitaliz · Portal internal, khusus karyawan.</p>
            </div>

            {{-- Form panel --}}
            <div class="flex flex-1 flex-col justify-center px-5 py-12 sm:px-10">
                <div class="mx-auto w-full max-w-sm">
                    <a href="/" class="mb-10 inline-block lg:hidden" wire:navigate>
                        <x-application-logo with-text />
                    </a>
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
