<?php

use App\Enums\RequestStatus;
use App\Models\PettyCashRequest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $statusFilter = '';

    public string $search = '';

    public function updatedStatusFilter(): void
    {
    }

    #[Computed]
    public function requests()
    {
        return PettyCashRequest::query()
            ->with(['requester'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('request_number', 'like', '%'.$this->search.'%')
                        ->orWhere('description', 'like', '%'.$this->search.'%')
                        ->orWhereHas('requester', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->latest('submitted_at')
            ->get();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return PettyCashRequest::query()->where('status', RequestStatus::PendingReview->value)->count();
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Validasi Pengajuan</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-gray-600">
                        {{ $this->pendingCount }} pengajuan menunggu validasi Anda.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nomor / nama / keperluan"
                           class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Semua Status</option>
                        @foreach (App\Enums\RequestStatus::cases() as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if ($this->requests->isEmpty())
                        <div class="text-center py-10 text-gray-500">
                            <p class="text-lg font-medium">Tidak ada pengajuan yang cocok</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3">Nomor</th>
                                        <th class="px-4 py-3">Pengaju</th>
                                        <th class="px-4 py-3">Keperluan</th>
                                        <th class="px-4 py-3">Nominal</th>
                                        <th class="px-4 py-3">Tanggal</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($this->requests as $item)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-medium text-gray-900">{{ $item->request_number }}</td>
                                            <td class="px-4 py-3 text-gray-900">{{ $item->requester->name }}</td>
                                            <td class="px-4 py-3 text-gray-600 max-w-xs truncate">{{ $item->description }}</td>
                                            <td class="px-4 py-3 text-gray-900">Rp {{ number_format($item->nominal, 0, ',', '.') }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ $item->submitted_at?->format('d M Y') }}</td>
                                            <td class="px-4 py-3"><x-status-badge :status="$item->status" /></td>
                                            <td class="px-4 py-3 text-right">
                                                <a href="{{ route('requests.show', ['id' => $item->id]) }}" wire:navigate class="text-indigo-600 hover:text-indigo-500 font-medium">
                                                    Review →
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>