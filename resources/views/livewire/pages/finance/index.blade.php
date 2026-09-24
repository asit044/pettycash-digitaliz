<?php

use App\Enums\RequestStatus;
use App\Models\PettyCashRequest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $statusFilter = RequestStatus::Processing->value;

    public string $search = '';

    #[Computed]
    public function requests()
    {
        return PettyCashRequest::query()
            ->with(['requester'])
            ->whereIn('status', [RequestStatus::Processing->value, RequestStatus::Done->value])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('request_number', 'like', '%'.$this->search.'%')
                        ->orWhereHas('requester', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->latest('reviewed_at')
            ->get();
    }

    #[Computed]
    public function waitingCount(): int
    {
        return PettyCashRequest::query()->where('status', RequestStatus::Processing->value)->count();
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pencairan Finance</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4 flex items-center justify-between gap-3">
                <p class="text-sm text-gray-600">
                    {{ $this->waitingCount }} pengajuan siap diproses pencairannya.
                </p>
                <div class="flex items-center gap-3">
                    <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nomor / nama"
                           class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                    <select wire:model.live="statusFilter" class="rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="{{ App\Enums\RequestStatus::Processing->value }}">Diproses Finance</option>
                        <option value="{{ App\Enums\RequestStatus::Done->value }}">Selesai</option>
                    </select>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if ($this->requests->isEmpty())
                        <div class="text-center py-10 text-gray-500">
                            <p class="text-lg font-medium">Belum ada pengajuan untuk diproses</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3">Nomor</th>
                                        <th class="px-4 py-3">Pengaju</th>
                                        <th class="px-4 py-3">Nominal</th>
                                        <th class="px-4 py-3">Kode Anggaran</th>
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
                                            <td class="px-4 py-3 text-gray-900">Rp {{ number_format($item->nominal, 0, ',', '.') }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ $item->budget_code ?: '—' }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ $item->reviewed_at?->format('d M Y') }}</td>
                                            <td class="px-4 py-3"><x-status-badge :status="$item->status" /></td>
                                            <td class="px-4 py-3 text-right">
                                                <a href="{{ route('requests.show', ['id' => $item->id]) }}" wire:navigate class="text-indigo-600 hover:text-indigo-500 font-medium">
                                                    Proses →
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