<?php

use App\Enums\RequestStatus;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Pengajuan Saya')] class extends Component
{
    use WithPagination;

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function requests()
    {
        return request()->user()
            ->requests()
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($q) {
                $q->where(fn ($q) => $q
                    ->where('request_number', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%'));
            })
            ->latest('submitted_at')
            ->latest('id')
            ->paginate(10);
    }

    #[Computed]
    public function tabs(): array
    {
        $counts = request()->user()->requests()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $tabs = ['' => ['label' => 'Semua', 'count' => (int) $counts->sum()]];

        foreach (RequestStatus::cases() as $status) {
            $tabs[$status->value] = ['label' => $status->shortLabel(), 'count' => (int) ($counts[$status->value] ?? 0)];
        }

        return $tabs;
    }
}; ?>

<div>
    <x-slot name="header">
        <x-page-header title="Pengajuan Saya" description="Pantau status dan riwayat seluruh pengajuan Anda.">
            <a href="{{ route('requests.create') }}" wire:navigate class="btn-primary">
                <x-icon name="plus" class="size-4" /> Ajukan Baru
            </a>
        </x-page-header>
    </x-slot>

    <div class="space-y-4">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <x-status-tabs :tabs="$this->tabs" :current="$statusFilter" />

            <div class="relative w-full xl:w-72 xl:shrink-0">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nomor atau keperluan…" class="field pl-10" />
            </div>
        </div>

        <div class="card overflow-hidden">
            @if ($this->requests->isEmpty())
                @if ($search !== '' || $statusFilter !== '')
                    <x-empty-state icon="magnifying-glass" title="Tidak ada pengajuan yang cocok" description="Coba ubah kata kunci atau filter status." />
                @else
                    <x-empty-state icon="document-plus" title="Belum ada pengajuan" description="Mulai ajukan reimbursement atau kas kecil Anda.">
                        <a href="{{ route('requests.create') }}" wire:navigate class="btn-primary"><x-icon name="plus" class="size-4" /> Ajukan Sekarang</a>
                    </x-empty-state>
                @endif
            @else
                {{-- Mobile: list --}}
                <div class="divide-y divide-slate-100 xl:hidden">
                    @foreach ($this->requests as $item)
                        <x-request-item :item="$item" wire:key="m-{{ $item->id }}" />
                    @endforeach
                </div>

                {{-- Desktop: table --}}
                <table class="hidden min-w-full divide-y divide-slate-100 text-sm xl:table">
                    <thead class="table-head">
                        <tr>
                            <th class="px-5 py-3">Nomor</th>
                            <th class="px-5 py-3">Keperluan</th>
                            <th class="px-5 py-3 text-right">Nominal</th>
                            <th class="px-5 py-3">Tanggal</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->requests as $item)
                            <tr wire:key="d-{{ $item->id }}" class="transition hover:bg-slate-50">
                                <td class="px-5 py-3.5 font-semibold whitespace-nowrap text-slate-900">{{ $item->request_number }}</td>
                                <td class="max-w-xs truncate px-5 py-3.5 text-slate-600">{{ $item->description }}</td>
                                <td class="px-5 py-3.5 text-right font-semibold whitespace-nowrap text-slate-900">{{ Money::rupiah($item->nominal) }}</td>
                                <td class="px-5 py-3.5 whitespace-nowrap text-slate-500">{{ $item->submitted_at?->translatedFormat('d M Y') }}</td>
                                <td class="px-5 py-3.5"><x-status-badge :status="$item->status" /></td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="{{ route('requests.show', $item) }}" wire:navigate class="inline-flex items-center gap-1 font-semibold text-brand-600 hover:text-brand-700">
                                        Detail <x-icon name="chevron-right" class="size-4" />
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="border-t border-slate-100 px-5 py-3">
                    {{ $this->requests->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
