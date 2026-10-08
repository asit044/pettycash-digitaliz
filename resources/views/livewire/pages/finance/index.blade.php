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

new #[Layout('layouts.app')] #[Title('Pencairan Finance')] class extends Component
{
    use WithPagination;

    #[Url(as: 'status')]
    public string $statusFilter = RequestStatus::Processing->value;

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
        $processing = $this->statusFilter === RequestStatus::Processing->value;

        return PettyCashRequest::query()
            ->with(['requester'])
            // Finance only ever works on approved requests.
            ->whereIn('status', [RequestStatus::Processing->value, RequestStatus::Done->value])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->search !== '', function ($q) {
                $q->where(function ($q) {
                    $q->where('request_number', 'like', '%'.$this->search.'%')
                        ->orWhereHas('requester', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->when($processing, fn ($q) => $q->oldest('reviewed_at'), fn ($q) => $q->latest('completed_at'))
            ->orderBy('id')
            ->paginate(15);
    }

    #[Computed]
    public function waitingCount(): int
    {
        return PettyCashRequest::query()->where('status', RequestStatus::Processing->value)->count();
    }

    #[Computed]
    public function waitingNominal(): float
    {
        return (float) PettyCashRequest::query()->where('status', RequestStatus::Processing->value)->sum('nominal');
    }

    #[Computed]
    public function doneThisMonth(): array
    {
        $q = PettyCashRequest::query()
            ->where('status', RequestStatus::Done->value)
            ->where('completed_at', '>=', now()->startOfMonth());

        return ['count' => (clone $q)->count(), 'nominal' => (float) $q->sum('nominal')];
    }

    #[Computed]
    public function tabs(): array
    {
        return [
            RequestStatus::Processing->value => ['label' => 'Siap Dicairkan', 'count' => $this->waitingCount],
            RequestStatus::Done->value => ['label' => 'Selesai', 'count' => PettyCashRequest::query()->where('status', RequestStatus::Done->value)->count()],
        ];
    }
}; ?>

<div>
    <x-slot name="header">
        <x-page-header title="Pencairan Finance"
                       :description="$this->waitingCount > 0 ? $this->waitingCount.' pengajuan siap diproses pencairannya.' : 'Tidak ada pengajuan yang menunggu pencairan.'" />
    </x-slot>

    <div class="space-y-4">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat-card label="Siap Dicairkan" :value="$this->waitingCount" icon="clock" tone="sky" />
            <x-stat-card label="Total Nominal Antrean" :value="Money::compact($this->waitingNominal)" icon="wallet" tone="amber" :hint="Money::rupiah($this->waitingNominal)" />
            <x-stat-card label="Dicairkan Bulan Ini" :value="Money::compact($this->doneThisMonth['nominal'])" icon="check-circle" tone="emerald"
                         :hint="$this->doneThisMonth['count'].' pengajuan'" />
        </div>

        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <x-status-tabs :tabs="$this->tabs" :current="$statusFilter" />

            <div class="relative w-full xl:w-72 xl:shrink-0">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nomor atau nama…" class="field pl-10" />
            </div>
        </div>

        <div class="card overflow-hidden">
            @if ($this->requests->isEmpty())
                <x-empty-state icon="banknotes" title="Belum ada pengajuan untuk diproses"
                               description="Pengajuan yang disetujui Admin akan muncul di sini dan Anda akan menerima notifikasi WhatsApp." />
            @else
                <div class="divide-y divide-slate-100 xl:hidden">
                    @foreach ($this->requests as $item)
                        <x-request-item :item="$item" show-requester date-field="reviewed_at" wire:key="m-{{ $item->id }}" />
                    @endforeach
                </div>

                <table class="hidden min-w-full divide-y divide-slate-100 text-sm xl:table">
                    <thead class="table-head">
                        <tr>
                            <th class="px-5 py-3">Nomor</th>
                            <th class="px-5 py-3">Pengaju</th>
                            <th class="px-5 py-3 text-right">Nominal</th>
                            <th class="px-5 py-3">Kode Anggaran</th>
                            <th class="px-5 py-3">{{ $statusFilter === 'done' ? 'Selesai' : 'Disetujui' }}</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3"><span class="sr-only">Aksi</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->requests as $item)
                            @php($date = $item->status === 'done' ? $item->completed_at : $item->reviewed_at)
                            <tr wire:key="d-{{ $item->id }}" class="transition hover:bg-slate-50">
                                <td class="px-5 py-3.5 font-semibold whitespace-nowrap text-slate-900">{{ $item->request_number }}</td>
                                <td class="px-5 py-3.5 whitespace-nowrap text-slate-900">{{ $item->requester->name }}</td>
                                <td class="px-5 py-3.5 text-right font-semibold whitespace-nowrap text-slate-900">{{ Money::rupiah($item->nominal) }}</td>
                                <td class="px-5 py-3.5 font-mono text-xs whitespace-nowrap text-slate-600">{{ $item->budget_code ?: '—' }}</td>
                                <td class="px-5 py-3.5 whitespace-nowrap">
                                    <span class="text-slate-700">{{ $date?->translatedFormat('d M Y') }}</span>
                                    <span class="block text-xs text-slate-400">{{ $date?->diffForHumans() }}</span>
                                </td>
                                <td class="px-5 py-3.5"><x-status-badge :status="$item->status" /></td>
                                <td class="px-5 py-3.5 text-right">
                                    <a href="{{ route('requests.show', ['id' => $item->id]) }}" wire:navigate
                                       @class([
                                           'btn px-3 py-1.5',
                                           'bg-brand-600 text-white hover:bg-brand-700' => $item->status === 'processing',
                                           'text-brand-600 hover:bg-brand-50' => $item->status !== 'processing',
                                       ])>
                                        {{ $item->status === 'processing' ? 'Proses' : 'Detail' }}
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
