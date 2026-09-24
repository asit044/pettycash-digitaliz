<?php

use App\Models\BudgetCode;
use App\Models\Setting;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
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

    public function saveGeneral(): void
    {
        $this->validate([
            'signerName' => ['required', 'string', 'max:255'],
            'signerTitle' => ['required', 'string', 'max:255'],
            'companyName' => ['required', 'string', 'max:255'],
        ]);

        Setting::set('signer_name', $this->signerName);
        Setting::set('signer_title', $this->signerTitle);
        Setting::set('company_name', $this->companyName);

        session()->flash('status', 'Pengaturan umum disimpan.');
    }

    public function addBudgetCode(): void
    {
        $this->validate([
            'newCode' => ['required', 'string', 'max:50', 'unique:budget_codes,code'],
            'newDescription' => ['required', 'string', 'max:255'],
        ]);

        BudgetCode::create([
            'code' => strtoupper($this->newCode),
            'description' => $this->newDescription,
        ]);

        $this->reset('newCode', 'newDescription');

        session()->flash('status', 'Kode anggaran ditambahkan.');
    }

    public function toggleBudgetCode(int $id): void
    {
        $code = BudgetCode::findOrFail($id);
        $code->update(['is_active' => ! $code->is_active]);

        unset($this->budgetCodes);
    }

    public function deleteBudgetCode(int $id): void
    {
        $code = BudgetCode::findOrFail($id);
        $code->delete();

        unset($this->budgetCodes);
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengaturan</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="p-4 rounded-lg bg-green-50 border border-green-200 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <form wire:submit="saveGeneral" class="p-6 space-y-4">
                    <h3 class="font-semibold text-gray-900">Laporan &amp; Penandatangan</h3>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Nama Penandatangan" />
                            <x-text-input wire:model="signerName" class="mt-1 block w-full" />
                        </div>
                        <div>
                            <x-input-label value="Jabatan Penandatangan" />
                            <x-text-input wire:model="signerTitle" class="mt-1 block w-full" />
                        </div>
                    </div>
                    <div class="max-w-sm">
                        <x-input-label value="Nama Perusahaan (folder Drive)" />
                        <x-text-input wire:model="companyName" class="mt-1 block w-full" />
                    </div>
                    <div class="pt-2">
                        <x-primary-button>Simpan Pengaturan</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="font-semibold text-gray-900">Kode Anggaran</h3>
                    <p class="mt-1 text-sm text-gray-500">Digunakan Admin saat menyetujui pengajuan.</p>

                    <form wire:submit="addBudgetCode" class="mt-4 grid gap-3 sm:grid-cols-[200px_1fr_auto]">
                        <x-text-input wire:model="newCode" placeholder="Kode (cth: OPR-001)" class="block w-full" />
                        <x-text-input wire:model="newDescription" placeholder="Uraian (cth: Operasional & Umum)" class="block w-full" />
                        <x-primary-button>Tambah</x-primary-button>
                    </form>

                    @error('newCode')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    <ul class="mt-4 space-y-2">
                        @foreach ($this->budgetCodes as $code)
                            <li class="flex items-center justify-between gap-3 bg-gray-50 rounded-lg px-4 py-2 text-sm">
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900 @if (! $code->is_active) line-through text-gray-400 @endif">
                                        {{ $code->code }}
                                    </p>
                                    <p class="text-gray-600">{{ $code->description }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <button type="button" wire:click="toggleBudgetCode({{ $code->id }})"
                                            class="text-xs font-medium text-indigo-600 hover:text-indigo-500">
                                        {{ $code->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                    <button type="button" wire:click="deleteBudgetCode({{ $code->id }})" wire:confirm="Hapus kode anggaran ini?"
                                            class="text-xs font-medium text-red-600 hover:text-red-500">
                                        Hapus
                                    </button>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>