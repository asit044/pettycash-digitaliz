<?php

use App\Contracts\Drive;
use App\Models\BudgetCode;
use App\Models\PettyCashRequest;
use App\Models\Setting;
use App\Models\WaLog;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Pengaturan')] class extends Component
{
    public string $signerName = '';

    public string $signerTitle = '';

    public string $companyName = '';

    public string $newCode = '';

    public string $newDescription = '';

    public function mount(): void
    {
        $this->signerName = Setting::get('signer_name', '');
        $this->signerTitle = Setting::get('signer_title', '');
        $this->companyName = Setting::get('company_name', '');
    }

    #[Computed]
    public function budgetCodes()
    {
        return BudgetCode::query()->orderBy('code')->get();
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function usage(): array
    {
        return PettyCashRequest::query()
            ->whereNotNull('budget_code')
            ->selectRaw('budget_code, COUNT(*) as total')
            ->groupBy('budget_code')
            ->pluck('total', 'budget_code')
            ->all();
    }

    #[Computed]
    public function integrations(): array
    {
        return [
            'whatsapp' => filled(config('services.fonnte.token')),
            'drive' => app(Drive::class)->isConfigured(),
        ];
    }

    #[Computed]
    public function recentWaLogs()
    {
        return WaLog::query()->latest('id')->limit(8)->get();
    }

    public function saveGeneral(): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $this->validate([
            'signerName' => ['required', 'string', 'max:255'],
            'signerTitle' => ['required', 'string', 'max:255'],
            'companyName' => ['required', 'string', 'max:255'],
        ], attributes: [
            'signerName' => 'nama penanda tangan',
            'signerTitle' => 'jabatan penanda tangan',
            'companyName' => 'nama perusahaan',
        ]);

        Setting::set('signer_name', $this->signerName);
        Setting::set('signer_title', $this->signerTitle);
        Setting::set('company_name', $this->companyName);

        session()->flash('status', 'Pengaturan umum disimpan.');
    }

    public function addBudgetCode(): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $this->newCode = strtoupper(trim($this->newCode));

        $this->validate([
            'newCode' => ['required', 'string', 'max:50', 'unique:budget_codes,code'],
            'newDescription' => ['required', 'string', 'max:255'],
        ], attributes: ['newCode' => 'kode', 'newDescription' => 'uraian']);

        BudgetCode::create([
            'code' => $this->newCode,
            'description' => $this->newDescription,
        ]);

        $this->reset('newCode', 'newDescription');

        unset($this->budgetCodes);

        session()->flash('status', 'Kode anggaran ditambahkan.');
    }

    public function toggleBudgetCode(int $id): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $code = BudgetCode::findOrFail($id);
        $code->update(['is_active' => ! $code->is_active]);

        unset($this->budgetCodes);
    }

    public function deleteBudgetCode(int $id): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $code = BudgetCode::findOrFail($id);

        // Historical requests reference the code by value; keep it for the
        // audit trail and steer the admin towards deactivating instead.
        if (PettyCashRequest::query()->where('budget_code', $code->code)->exists()) {
            $this->addError('budgetCodes', 'Kode '.$code->code.' sudah dipakai pada pengajuan. Nonaktifkan saja agar riwayat tetap utuh.');

            return;
        }

        $code->delete();

        unset($this->budgetCodes);
    }
}; ?>

<div>
    <x-slot name="header">
        <x-page-header title="Pengaturan" description="Kelola penanda tangan laporan, kode anggaran, dan pantau integrasi." />
    </x-slot>

    <div class="space-y-6">
        @if (session('status'))
            <x-alert type="success" dismissible>{{ session('status') }}</x-alert>
        @endif

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                {{-- Signer --}}
                <form wire:submit="saveGeneral" class="card divide-y divide-slate-100">
                    <div class="p-5 sm:p-6">
                        <h2 class="font-semibold text-slate-900">Laporan &amp; Penanda Tangan</h2>
                        <p class="text-sm text-slate-500">Dipakai otomatis pada blok "Mengetahui" di laporan PDF.</p>

                        <div class="mt-5 grid gap-5 sm:grid-cols-2">
                            <div>
                                <x-input-label for="signerName" value="Nama penanda tangan" required />
                                <x-text-input wire:model="signerName" id="signerName" class="mt-1.5" />
                                <x-input-error :messages="$errors->get('signerName')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-input-label for="signerTitle" value="Jabatan penanda tangan" required />
                                <x-text-input wire:model="signerTitle" id="signerTitle" class="mt-1.5" />
                                <x-input-error :messages="$errors->get('signerTitle')" class="mt-1.5" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="companyName" value="Nama perusahaan" required />
                                <x-text-input wire:model="companyName" id="companyName" class="mt-1.5" />
                                <p class="mt-1.5 text-xs text-slate-500">Tampil di kop laporan dan menjadi nama folder induk di Google Drive.</p>
                                <x-input-error :messages="$errors->get('companyName')" class="mt-1.5" />
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-3 bg-slate-50/60 px-5 py-4 sm:px-6">
                        <p class="text-xs text-slate-500">
                            Pratinjau: <span class="font-semibold text-slate-700">Mengetahui: {{ $signerName ?: '…' }} ({{ $signerTitle ?: '…' }})</span>
                        </p>
                        <x-primary-button wire:loading.attr="disabled">Simpan</x-primary-button>
                    </div>
                </form>

                {{-- Budget codes --}}
                <div class="card">
                    <div class="p-5 sm:p-6">
                        <h2 class="font-semibold text-slate-900">Kode Anggaran</h2>
                        <p class="text-sm text-slate-500">Hanya kode aktif yang dapat dipilih Admin saat menyetujui pengajuan.</p>

                        <form wire:submit="addBudgetCode" class="mt-5 grid gap-3 sm:grid-cols-[11rem_1fr_auto]">
                            <div>
                                <x-text-input wire:model="newCode" placeholder="Kode, cth. OPR-002" class="font-mono uppercase" aria-label="Kode anggaran baru" />
                                <x-input-error :messages="$errors->get('newCode')" class="mt-1.5" />
                            </div>
                            <div>
                                <x-text-input wire:model="newDescription" placeholder="Uraian, cth. Konsumsi rapat" aria-label="Uraian anggaran baru" />
                                <x-input-error :messages="$errors->get('newDescription')" class="mt-1.5" />
                            </div>
                            <x-primary-button class="self-start"><x-icon name="plus" class="size-4" /> Tambah</x-primary-button>
                        </form>

                        <x-input-error :messages="$errors->get('budgetCodes')" class="mt-3" />
                    </div>

                    <ul class="divide-y divide-slate-100 border-t border-slate-100">
                        @forelse ($this->budgetCodes as $code)
                            <li wire:key="bc-{{ $code->id }}" class="flex items-center gap-4 px-5 py-3.5 sm:px-6">
                                <span @class([
                                    'rounded-lg px-2.5 py-1 font-mono text-sm font-semibold ring-1',
                                    'bg-white text-slate-900 ring-slate-200' => $code->is_active,
                                    'bg-slate-50 text-slate-400 line-through ring-slate-100' => ! $code->is_active,
                                ])>{{ $code->code }}</span>
                                <div class="min-w-0 flex-1">
                                    <p @class(['truncate text-sm', 'text-slate-700' => $code->is_active, 'text-slate-400' => ! $code->is_active])>{{ $code->description }}</p>
                                    <p class="text-xs text-slate-400">Dipakai {{ $this->usage[$code->code] ?? 0 }} pengajuan</p>
                                </div>
                                <button type="button" wire:click="toggleBudgetCode({{ $code->id }})"
                                        role="switch" aria-checked="{{ $code->is_active ? 'true' : 'false' }}"
                                        title="{{ $code->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
                                        @class([
                                            'relative inline-flex h-6 w-11 shrink-0 rounded-full transition',
                                            'bg-emerald-500' => $code->is_active,
                                            'bg-slate-300' => ! $code->is_active,
                                        ])>
                                    <span @class(['absolute top-0.5 size-5 rounded-full bg-white shadow transition-all', 'left-5.5' => $code->is_active, 'left-0.5' => ! $code->is_active])></span>
                                    <span class="sr-only">{{ $code->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</span>
                                </button>
                                <button type="button" wire:click="deleteBudgetCode({{ $code->id }})" wire:confirm="Hapus kode anggaran {{ $code->code }}?"
                                        class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600" title="Hapus">
                                    <x-icon name="trash" class="size-4" />
                                    <span class="sr-only">Hapus</span>
                                </button>
                            </li>
                        @empty
                            <li><x-empty-state icon="tag" title="Belum ada kode anggaran" description="Tambahkan kode pertama di atas." class="py-10" /></li>
                        @endforelse
                    </ul>
                </div>
            </div>

            {{-- Integrations --}}
            <aside class="grid gap-6 md:grid-cols-2 xl:grid-cols-1 xl:content-start">
                <div class="card p-5">
                    <h2 class="font-semibold text-slate-900">Integrasi</h2>
                    <ul class="mt-4 space-y-3">
                        @foreach ([
                            ['whatsapp', 'chat', 'WhatsApp Gateway (Fonnte)', 'FONNTE_TOKEN'],
                            ['drive', 'folder', 'Google Drive', 'GOOGLE_SERVICE_ACCOUNT_JSON'],
                        ] as [$key, $icon, $label, $env])
                            @php($ok = $this->integrations[$key])
                            <li class="flex items-start gap-3 rounded-xl bg-slate-50 p-3">
                                <span @class(['grid size-9 shrink-0 place-items-center rounded-lg', 'bg-emerald-100 text-emerald-600' => $ok, 'bg-slate-200 text-slate-500' => ! $ok])>
                                    <x-icon :name="$icon" class="size-4.5" />
                                </span>
                                <div class="min-w-0 text-sm">
                                    <p class="font-semibold text-slate-800">{{ $label }}</p>
                                    @if ($ok)
                                        <p class="text-xs font-medium text-emerald-600">Terhubung</p>
                                    @else
                                        <p class="text-xs text-slate-500">Belum dikonfigurasi. Isi <code class="rounded bg-white px-1 font-mono">{{ $env }}</code> di file .env.</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    <p class="mt-3 text-xs text-slate-500">Tanpa integrasi, alur tetap berjalan: berkas disimpan lokal dan notifikasi dicatat di log.</p>
                </div>

                <div class="card p-5">
                    <h2 class="font-semibold text-slate-900">Log Notifikasi WhatsApp</h2>
                    <p class="text-xs text-slate-500">8 pengiriman terakhir</p>
                    @if ($this->recentWaLogs->isEmpty())
                        <p class="mt-4 text-sm text-slate-500">Belum ada notifikasi.</p>
                    @else
                        <ul class="mt-4 space-y-2.5">
                            @foreach ($this->recentWaLogs as $log)
                                <li class="flex items-center gap-2 text-sm">
                                    <span @class([
                                        'size-2 shrink-0 rounded-full',
                                        'bg-emerald-500' => $log->status === 'sent',
                                        'bg-rose-500' => $log->status === 'failed',
                                        'bg-slate-300' => ! in_array($log->status, ['sent', 'failed']),
                                    ])></span>
                                    <span class="min-w-0 flex-1 truncate text-slate-700">
                                        {{ ucfirst((string) $log->recipient_role) }} · {{ str_replace('_', ' ', $log->event) }}
                                    </span>
                                    <span class="shrink-0 text-xs text-slate-400" title="{{ $log->error }}">
                                        {{ ['sent' => 'terkirim', 'failed' => 'gagal', 'skipped' => 'dilewati'][$log->status] ?? $log->status }}
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </aside>
        </div>
    </div>
</div>
