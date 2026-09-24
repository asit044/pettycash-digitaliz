<?php

use App\Enums\RequestFileType;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    #[Validate('required|numeric|min:1|max_digits:15')]
    public string $nominal = '';

    #[Validate('required|string|max:2000')]
    public string $description = '';

    #[Validate('nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp')]
    public $invoice;

    #[Validate('nullable|file|max:10240|mimes:pdf,jpg,jpeg,png,webp')]
    public $proofTransfer;

    public function submit(): void
    {
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

        session()->flash('status', 'Pengajuan '.$request->request_number.' berhasil dikirim.');

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
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ajukan Pengajuan</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <form wire:submit="submit" class="p-6 space-y-6">
                    <div>
                        <x-input-label for="description" value="Keperluan *" />
                        <textarea wire:model="description" id="description" rows="3"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                  placeholder="Contoh: Pembelian pulsa untuk meeting tim, transport meeting klien, dll."></textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="nominal" value="Nominal (Rp) *" />
                        <x-text-input wire:model="nominal" id="nominal" class="mt-1 block w-full" type="text"
                                      inputmode="numeric" placeholder="250000" />
                        <x-input-error :messages="$errors->get('nominal')" class="mt-2" />
                    </div>

                    <div class="p-4 rounded-lg bg-sky-50 border border-sky-200 text-sm text-sky-800">
                        {{ $this->budgetHiddenNotice }}
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <x-input-label for="invoice" value="Invoice / Struk (opsional)" />
                            <input wire:model="invoice" id="invoice" type="file"
                                   accept=".pdf,.jpg,.jpeg,.png,.webp"
                                   class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100" />
                            <x-input-error :messages="$errors->get('invoice')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="proofTransfer" value="Bukti Transfer Awal (opsional)" />
                            <input wire:model="proofTransfer" id="proofTransfer" type="file"
                                   accept=".pdf,.jpg,.jpeg,.png,.webp"
                                   class="mt-1 block w-full text-sm text-gray-600 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100" />
                            <x-input-error :messages="$errors->get('proofTransfer')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('requests.index') }}" wire:navigate class="text-sm text-gray-600 hover:text-gray-900">
                            Batal
                        </a>
                        <x-primary-button wire:loading.attr="disabled">
                            {{ __('Kirim Pengajuan') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>