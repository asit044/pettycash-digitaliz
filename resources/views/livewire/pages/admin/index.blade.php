<?php

use App\Enums\RequestStatus;
use App\Models\PettyCashRequest;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Validasi Pengajuan')] class extends Component
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $statusFilter = 'pending_review';

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
        $pendingOnly = $this->statusFilter === RequestStatus::PendingReview->value;

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
            // The validation queue is first-in-first-out; other tabs show newest first.
            ->when($pendingOnly, fn ($q) => $q->oldest('submitted_at'), fn ($q) => $q->latest('submitted_at'))
            ->orderBy('id')
            ->paginate(15);
    }

    #[Computed]
    public function pendingCount(): int
    {
        return PettyCashRequest::query()->where('status', RequestStatus::PendingReview->value)->count();
    }

    #[Computed]
    public function tabs(): array
    {
        $counts = PettyCashRequest::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $tabs = [];
        foreach (RequestStatus::cases() as $status) {
            $tabs[$status->value] = ['label' => $status->shortLabel(), 'count' => (int) ($counts[$status->value] ?? 0)];
        }
        $tabs[''] = ['label' => 'Semua', 'count' => (int) $counts->sum()];

        return $tabs;
    }
}; ?>

<div>
    <x-slot name="header">
        <x-page-header title="Validasi Pengajuan"
                       :description="$this->pendingCount > 0 ? $this->pendingCount.' pengajuan menunggu validasi Anda.' : 'Semua pengajuan sudah divalidasi.'" />
    </x-slot>

    <div class="space-y-4">
        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <x-status-tabs :tabs="$this->tabs" :current="$statusFilter" />

            <div class="relative w-full xl:w-80 xl:shrink-0">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nomor, nama, atau keperluan…" class="field pl-10" />
            </div>
        </div>

        <div class="card overflow-hidden">
            @if ($this->requests->isEmpty())
                <x-empty-state :icon="$search !== '' ? 'magnifying-glass' : 'check-circle'"
                               :title="$search !== '' ? 'Tidak ada pengajuan yang cocok' : 'Tidak ada pengajuan di sini'"
                               description="Pengajuan baru akan muncul otomatis dan Anda akan menerima notifikasi WhatsApp." />
            @else
                <div class="divide-y divide-slate-100 xl:hidden">
                    @foreach ($this->requests as $item)
                        <x-request-item :item="$item" show-requester wire:key="m-{{ $item->id }}" />
                    @endforeach
                </div>

                <table class="hidden min-w-full divide-y divide-slate-100 text-sm xl:table">
                    <thead class="table-head">
                        <tr>
                            <th class="px-5 py-3">Nomor</th>
                            <th class="px-5 py-3">Pengaju</th>
                            <th class="px-5 py-3">Keperluan</th>
                            <th class="px-5 py-3 text-right">Nominal</th>
                            <th class="px-5 py-3">Diajukan</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->requests as $item)
                            <tr wire:key="d-{{ $item->id }}" class="transition hover:bg-slate-50">
                                <td class="px-5 py-3.5 font-semibold whitespace-nowrap text-slate-900">{{ $item->request_number }}</td>
                                <td class="px-5 py-3.5 whitespace-nowrap text-slate-900">{{ $item->requester->name }}</td>
                                <td class="max-w-xs truncate px-5 py-3.5 text-slate-600">{{ $item->description }}</td>
                                <td class="px-5 py-3.5 text-right font-semibold whitespace-nowrap text-slate-900">{{ Money::rupiah($item->nominal) }}</td>
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <span class="text-slate-700">{{ $item->submitted_at?->translatedFormat('d M Y') }}</span>
                                    <span class="block text-xs text-slate-400">{{ $item->submitted_at?->diffForHumans() }}</span>
                                </td>
                                <td class="px-5 py-3.5"><x-status-badge :status="$item->status" short /></td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="{{ route('requests.show', ['id' => $item->id]) }}" wire:navigate
                                       @class([
                                           'btn px-3 py-1.5',
                                           'bg-brand-600 text-white hover:bg-brand-700' => $item->status === 'pending_review',
                                           'text-brand-600 hover:bg-brand-50' => $item->status !== 'pending_review',
                                       ])>
                                        {{ $item->status === 'pending_review' ? 'Review' : 'Detail' }}
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
