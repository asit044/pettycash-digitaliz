<?php

use App\Enums\RequestFileType;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Ajukan Pengajuan')] class extends Component
{
    use WithFileUploads;

    #[Validate('required|numeric|min:1|max_digits:15', as: 'nominal')]
    public string $nominal = '';

    #[Validate('required|string|max:2000', as: 'keperluan')]
    public string $description = '';

    #[Validate('nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp', as: 'invoice / struk')]
    public $invoice;

    #[Validate('nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp', as: 'bukti transfer')]
    public $proofTransfer;

    public function submit(): void
    {
        abort_unless(auth()->user()->can('create-requests'), 403);

        $this->validate();

        $uploads = [];

        if ($this->invoice) {
            $uploads[] = ['type' => RequestFileType::Invoice->value, 'file' => $this->invoice];
        }

        if ($this->proofTransfer) {
            $uploads[] = ['type' => RequestFileType::ProofTransfer->value, 'file' => $this->proofTransfer];
        }

        $request = app(\App\Services\PettyCashService::class)
            ->submit(auth()->user(), $this->description, $this->nominal, $uploads);

        session()->flash('status', 'Pengajuan '.$request->request_number.' berhasil dikirim. Admin sudah menerima notifikasi.');

        $this->redirect(route('requests.show', $request), navigate: true);
    }

    #[Computed]
    public function budgetHiddenNotice(): string
    {
        return 'Kode anggaran ditentukan oleh Admin — Anda tidak perlu memilihnya.';
    }
}; ?>

<div>
    <x-slot name="header">
        <x-page-header title="Ajukan Pengajuan" description="Isi data reimbursement atau kas kecil, lalu kirim untuk divalidasi Admin."
                       :back="route('requests.index')" />
    </x-slot>

    <div class="grid gap-6 xl:grid-cols-3">
        <form wire:submit="submit" class="card divide-y divide-slate-100 xl:col-span-2">
            <div class="space-y-5 p-5 sm:p-6">
                <div>
                    <h2 class="font-semibold text-slate-900">Detail pengajuan</h2>
                    <p class="text-sm text-slate-500">Jelaskan kebutuhan Anda sejelas mungkin.</p>
                </div>

                <div>
                    <x-input-label for="nominal" value="Nominal" required />
                    <div class="relative mt-1.5"
                         x-data="{
                             raw: $wire.entangle('nominal'),
                             get display() { return this.raw ? new Intl.NumberFormat('id-ID').format(this.raw) : '' },
                             set display(v) { this.raw = (v || '').replace(/\D/g, '').replace(/^0+/, '') },
                         }">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-sm font-semibold text-slate-500">Rp</span>
                        <input id="nominal" type="text" inputmode="numeric" autocomplete="off" placeholder="0"
                               x-model="display" class="field py-3 pl-12 text-lg font-semibold tracking-tight" />
                    </div>
                    <x-input-error :messages="$errors->get('nominal')" class="mt-1.5" />
                </div>

                <div x-data="{ count: $wire.description.length }">
                    <div class="flex items-center justify-between">
                        <x-input-label for="description" value="Keperluan" required />
                        <span class="text-xs text-slate-400"><span x-text="count">0</span>/2000</span>
                    </div>
                    <textarea wire:model="description" id="description" rows="4" maxlength="2000"
                              x-on:input="count = $event.target.value.length"
                              class="field mt-1.5"
                              placeholder="Contoh: Transport meeting dengan klien PT ABC di Jakarta Selatan, 7 Oktober 2026."></textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1.5" />
                </div>
            </div>

            <div class="space-y-5 p-5 sm:p-6">
                <div>
                    <h2 class="font-semibold text-slate-900">Dokumen pendukung</h2>
                    <p class="text-sm text-slate-500">Opsional, tapi sangat membantu Admin memvalidasi lebih cepat.</p>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-file-input model="invoice" label="Invoice / Struk" />
                    <x-file-input model="proofTransfer" label="Bukti Transfer Awal" />
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 bg-slate-50/60 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                <p class="flex items-center gap-2 text-xs text-slate-500">
                    <x-icon name="lock-closed" class="size-4 text-slate-400" />
                    {{ $this->budgetHiddenNotice }}
                </p>
                <div class="flex gap-3">
                    <a href="{{ route('requests.index') }}" wire:navigate class="btn-secondary flex-1 sm:flex-none">Batal</a>
                    <button type="submit" class="btn-primary flex-1 sm:flex-none" wire:loading.attr="disabled" wire:target="submit,invoice,proofTransfer">
                        <span wire:loading.remove wire:target="submit">Kirim Pengajuan</span>
                        <span wire:loading wire:target="submit">Mengirim…</span>
                    </button>
                </div>
            </div>
        </form>

        <aside class="grid gap-6 md:grid-cols-2 xl:grid-cols-1 xl:content-start">
            <div class="card p-5">
                <h2 class="font-semibold text-slate-900">Apa yang terjadi setelah dikirim?</h2>
                <ol class="mt-4 space-y-4">
                    @foreach ([
                        ['clipboard-check', 'Validasi Admin', 'Admin menerima notifikasi WhatsApp, memeriksa, lalu menyetujui, meminta revisi, atau menolak.'],
                        ['banknotes', 'Pencairan Finance', 'Setelah disetujui, Finance mentransfer dana dan mengunggah bukti transfer resmi.'],
                        ['chat', 'Notifikasi ke Anda', 'Anda mendapat WhatsApp saat diminta revisi, ditolak, atau dana sudah cair.'],
                    ] as $i => [$icon, $title, $text])
                        <li class="flex gap-3">
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                                <x-icon :name="$icon" class="size-4" />
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-slate-800">{{ $title }}</p>
                                <p class="mt-0.5 text-sm text-slate-500">{{ $text }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            @if (blank(auth()->user()->phone))
                <x-alert type="warning" title="Nomor WhatsApp belum diisi">
                    Anda tidak akan menerima notifikasi status.
                    <a href="{{ route('profile') }}" wire:navigate class="font-semibold underline underline-offset-2">Lengkapi profil</a>.
                </x-alert>
            @endif
        </aside>
    </div>
</div>
