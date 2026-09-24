<?php

use App\Enums\RequestStatus;
use App\Models\PettyCashRequest;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $from = '';

    public string $to = '';

    public string $statusFilter = '';

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->format('Y-m-d');
        $this->to = now()->format('Y-m-d');
    }

    #[Computed]
    public function queried()
    {
        return PettyCashRequest::query()
            ->whereBetween('submitted_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->latest('submitted_at')
            ->get();
    }

    #[Computed]
    public function totalNominal(): float
    {
        return (float) $this->queried->sum('nominal');
    }

    #[Computed]
    public function paidNominal(): float
    {
        return (float) $this->queried->where('status', RequestStatus::Done->value)->sum('nominal');
    }

    #[Computed]
    public function exportQuery(): string
    {
        return http_build_query([
            'from' => $this->from,
            'to' => $this->to,
            'status' => $this->statusFilter,
        ]);
    }
}; ?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Laporan &amp; Ringkasan</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Dari Tanggal" />
                            <input wire:model.live="from" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <x-input-label value="Sampai Tanggal" />
                            <input wire:model.live="to" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                    </div>
                    <div class="mt-3">
                        <x-input-label value="Filter Status" />
                        <select wire:model.live="statusFilter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Semua Status</option>
                            @foreach (App\Enums\RequestStatus::cases() as $status)
                                <option value="{{ $status->value }}">{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </div>

                    @can('export-reports')
                        <div class="mt-5 flex flex-wrap items-center gap-3">
                            <a href="{{ route('exports.csv', $this->exportQuery) }}"
                               class="inline-flex items-center rounded-md bg-gray-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-gray-600">
                                ⬇ Ekspor CSV
                            </a>
                            <a href="{{ route('exports.pdf', $this->exportQuery) }}"
                               class="inline-flex items-center rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500">
                                ⬇ Ekspor PDF
                            </a>
                        </div>
                    @endcan
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-3">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Total Pengajuan</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $this->queried->count() }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Total Nominal</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">Rp {{ number_format($this->totalNominal, 0, ',', '.') }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Nominal Selesai</p>
                    <p class="mt-2 text-3xl font-bold text-green-600">Rp {{ number_format($this->paidNominal, 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if ($this->queried->isEmpty())
                        <div class="text-center py-10 text-gray-500">Tidak ada data pada periode ini.</div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3">Nomor</th>
                                        <th class="px-4 py-3">Pengaju</th>
                                        <th class="px-4 py-3">Tanggal</th>
                                        <th class="px-4 py-3">Kode</th>
                                        <th class="px-4 py-3">Nominal</th>
                                        <th class="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($this->queried as $item)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-medium text-gray-900">{{ $item->request_number }}</td>
                                            <td class="px-4 py-3 text-gray-900">{{ $item->requester->name }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ $item->submitted_at?->format('d M Y') }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ $item->budget_code ?: '—' }}</td>
                                            <td class="px-4 py-3 text-gray-900">Rp {{ number_format($item->nominal, 0, ',', '.') }}</td>
                                            <td class="px-4 py-3"><x-status-badge :status="$item->status" /></td>
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