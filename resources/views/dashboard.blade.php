<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @if (auth()->user()->isRequester())
                    <x-dashboard-card
                        title="Buat Pengajuan"
                        description="Ajukan reimbursement / kas kecil baru."
                        :href="route('requests.create')"
                        cta="Ajukan sekarang"
                    />
                    <x-dashboard-card
                        title="Pengajuan Saya"
                        description="Pantau status dan riwayat pengajuan Anda."
                        :href="route('requests.index')"
                        cta="Lihat pengajuan"
                    />
                @endif

                @if (auth()->user()->isAdmin())
                    <x-dashboard-card
                        title="Validasi Pengajuan"
                        description="Review pengajuan masuk, isi kode anggaran, approve / revisi / tolak."
                        :href="route('admin.index')"
                        cta="Buka antrean"
                    />
                    <x-dashboard-card
                        title="Laporan"
                        description="Ekspor rekap dalam CSV atau PDF."
                        :href="route('reports.index')"
                        cta="Buka laporan"
                    />
                    <x-dashboard-card
                        title="Pengaturan"
                        description="Kelola kode anggaran & penandatangan laporan."
                        :href="route('settings.index')"
                        cta="Buka pengaturan"
                    />
                @endif

                @if (auth()->user()->isFinance())
                    <x-dashboard-card
                        title="Pencairan"
                        description="Proses pengajuan yang disetujui, unggah bukti transfer resmi."
                        :href="route('finance.index')"
                        cta="Buka pencairan"
                    />
                    <x-dashboard-card
                        title="Laporan"
                        description="Ekspor rekap dalam CSV atau PDF."
                        :href="route('reports.index')"
                        cta="Buka laporan"
                    />
                @endif

                @if (auth()->user()->isHead())
                    <x-dashboard-card
                        title="Laporan & Ringkasan"
                        description="Lihat ringkasan pengajuan dan unduh laporan formal."
                        :href="route('reports.index')"
                        cta="Buka laporan"
                    />
                @endif
            </div>
        </div>
    </div>
</x-app-layout>