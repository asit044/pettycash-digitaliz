<?php

use App\Enums\RequestStatus;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Computed]
    public function requests()
    {
        return request()->user()
            ->requests()
            ->with(['files' => fn ($q) => $q->where('type', 'official_receipt')])
            ->latest('submitted_at')
            ->get();
    }
}; ?>

<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengajuan Saya</h2>
            <a href="{{ route('requests.create') }}" wire:navigate
               class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                + Ajukan Baru
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    @if ($this->requests->isEmpty())
                        <div class="text-center py-10 text-gray-500">
                            <p class="text-lg font-medium">Belum ada pengajuan</p>
                            <p class="mt-1 text-sm">Mulai ajukan reimbursemen atau kas kecil Anda.</p>
                            <a href="{{ route('requests.create') }}" wire:navigate
                               class="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                                Ajukan Sekarang
                            </a>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3">Nomor</th>
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
                                            <td class="px-4 py-3 text-gray-600 max-w-xs truncate">{{ $item->description }}</td>
                                            <td class="px-4 py-3 text-gray-900">Rp {{ number_format($item->nominal, 0, ',', '.') }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ $item->submitted_at?->format('d M Y') }}</td>
                                            <td class="px-4 py-3">
                                                <x-status-badge :status="$item->status" />
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <a href="{{ route('requests.show', $item) }}" wire:navigate class="text-indigo-600 hover:text-indigo-500 font-medium">
                                                    Detail →
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