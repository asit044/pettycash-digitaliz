<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>Petty Cash Digitaliz</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-white font-sans antialiased">
        <div class="relative isolate overflow-hidden bg-brand-950">
            <div class="pointer-events-none absolute -top-40 right-0 -z-10 size-[36rem] rounded-full bg-brand-600/30 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-48 -left-24 -z-10 size-[30rem] rounded-full bg-sky-500/20 blur-3xl"></div>

            <header class="mx-auto flex max-w-6xl items-center justify-between px-5 py-6 sm:px-8">
                <x-application-logo with-text dark />
                @auth
                    <a href="{{ route('dashboard') }}" class="btn bg-white/10 text-white ring-1 ring-white/20 hover:bg-white/20">Buka Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn bg-white/10 text-white ring-1 ring-white/20 hover:bg-white/20">Masuk</a>
                @endauth
            </header>

            <section class="mx-auto max-w-6xl px-5 pt-12 pb-24 sm:px-8 lg:pt-20 lg:pb-32">
                <div class="max-w-2xl">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-brand-100 ring-1 ring-white/15">
                        <x-icon name="lock-closed" class="size-3.5" />
                        Portal internal Digitaliz
                    </span>
                    <h1 class="mt-6 text-4xl leading-[1.1] font-extrabold tracking-tight text-white sm:text-5xl">
                        Pengajuan kas kecil &amp; reimbursement, <span class="text-brand-300">tanpa bolak-balik chat.</span>
                    </h1>
                    <p class="mt-5 text-lg text-brand-100/80">
                        Ajukan dari satu form, berkas tersimpan otomatis ke Google Drive, status bisa dipantau sendiri,
                        dan rekap siap diekspor ke CSV &amp; PDF.
                    </p>
                    <div class="mt-9 flex flex-wrap gap-3">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn-primary px-6 py-3">
                                Buka Dashboard <x-icon name="arrow-right" class="size-4" />
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn-primary px-6 py-3">
                                Masuk ke aplikasi <x-icon name="arrow-right" class="size-4" />
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="btn bg-white/10 px-6 py-3 text-white ring-1 ring-white/20 hover:bg-white/20">Daftar sebagai pengaju</a>
                            @endif
                        @endauth
                    </div>
                </div>
            </section>
        </div>

        <section class="mx-auto -mt-14 max-w-6xl px-5 pb-20 sm:px-8">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['document-plus', 'brand', '1. Ajukan', 'Isi nominal & keperluan, lampirkan invoice atau bukti transfer.'],
                    ['clipboard-check', 'amber', '2. Validasi Admin', 'Admin memeriksa, memberi kode anggaran, lalu menyetujui.'],
                    ['banknotes', 'sky', '3. Pencairan Finance', 'Finance mentransfer dan mengunggah bukti transfer resmi.'],
                    ['check-circle', 'emerald', '4. Selesai', 'Notifikasi WhatsApp terkirim, data masuk arsip & laporan.'],
                ] as [$icon, $tone, $title, $text])
                    <div class="card p-6">
                        <span @class([
                            'grid size-11 place-items-center rounded-xl',
                            'bg-brand-50 text-brand-600' => $tone === 'brand',
                            'bg-amber-50 text-amber-600' => $tone === 'amber',
                            'bg-sky-50 text-sky-600' => $tone === 'sky',
                            'bg-emerald-50 text-emerald-600' => $tone === 'emerald',
                        ])>
                            <x-icon :name="$icon" />
                        </span>
                        <h3 class="mt-4 font-bold text-slate-900">{{ $title }}</h3>
                        <p class="mt-1.5 text-sm text-slate-500">{{ $text }}</p>
                    </div>
                @endforeach
            </div>

            <p class="mt-12 text-center text-xs text-slate-400">© {{ now()->year }} Digitaliz · Hanya untuk penggunaan internal.</p>
        </section>
    </body>
</html>
