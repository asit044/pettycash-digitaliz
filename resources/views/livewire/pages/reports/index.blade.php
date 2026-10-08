<?php

use App\Enums\RequestStatus;
use App\Models\BudgetCode;
use App\Models\PettyCashRequest;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Laporan')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'kode', except: '')]
    public string $budgetCode = '';

    public function mount(): void
    {
        $this->from = $this->from ?: now()->startOfMonth()->format('Y-m-d');
        $this->to = $this->to ?: now()->format('Y-m-d');
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function preset(string $range): void
    {
        [$from, $to] = match ($range) {
            'last-month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'year' => [now()->startOfYear(), now()],
            default => [now()->startOfMonth(), now()],
        };

        $this->from = $from->format('Y-m-d');
        $this->to = $to->format('Y-m-d');
        $this->resetPage();
    }

    private function base(): Builder
    {
        return PettyCashRequest::query()
            ->whereBetween('submitted_at', [$this->from.' 00:00:00', $this->to.' 23:59:59'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->budgetCode !== '', fn ($q) => $q->where('budget_code', $this->budgetCode));
    }

    #[Computed]
    public function queried()
    {
        return $this->base()->with('requester')->latest('submitted_at')->orderBy('id')->paginate(20);
    }

    #[Computed]
    public function totals(): array
    {
        $row = $this->base()
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(nominal), 0) as nominal, COALESCE(SUM(CASE WHEN status = ? THEN nominal ELSE 0 END), 0) as paid', [RequestStatus::Done->value])
            ->first();

        return ['count' => (int) $row->total, 'nominal' => (float) $row->nominal, 'paid' => (float) $row->paid];
    }

    #[Computed]
    public function totalNominal(): float
    {
        return $this->totals['nominal'];
    }

    #[Computed]
    public function paidNominal(): float
    {
        return $this->totals['paid'];
    }

    #[Computed]
    public function budgetCodes()
    {
        return BudgetCode::query()->orderBy('code')->get(['code', 'description']);
    }

    #[Computed]
    public function exportQuery(): string
    {
        return http_build_query(array_filter([
            'from' => $this->from,
            'to' => $this->to,
            'status' => $this->statusFilter,
            'budget_code' => $this->budgetCode,
        ], fn ($v) => $v !== ''));
    }
}; ?>

<div>
    <x-slot name="header">
        <x-page-header title="Laporan & Rekap" description="Saring berdasarkan periode, status, dan kode anggaran, lalu ekspor ke CSV atau PDF.">
            @can('export-reports')
                <a href="{{ route('exports.csv').'?'.$this->exportQuery }}" class="btn-secondary">
                    <x-icon name="arrow-down-tray" class="size-4" /> CSV
                </a>
                <a href="{{ route('exports.pdf').'?'.$this->exportQuery }}" class="btn-primary">
                    <x-icon name="arrow-down-tray" class="size-4" /> PDF bertanda tangan
                </a>
            @endcan
        </x-page-header>
    </x-slot>

    <div class="space-y-6">
        {{-- Filters --}}
        <div class="card p-5">
            <div class="flex flex-wrap items-center gap-2">
                <span class="flex items-center gap-1.5 text-sm font-semibold text-slate-700"><x-icon name="funnel" class="size-4" /> Filter</span>
                <span class="mx-1 hidden h-4 w-px bg-slate-200 sm:block"></span>
                <button type="button" wire:click="preset('month')" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Bulan ini</button>
                <button type="button" wire:click="preset('last-month')" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Bulan lalu</button>
                <button type="button" wire:click="preset('year')" class="rounded-lg px-2.5 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200 hover:bg-slate-50">Tahun ini</button>
            </div>

            <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <x-input-label for="from" value="Dari tanggal" />
                    <input wire:model.live="from" id="from" type="date" class="field mt-1.5" />
                </div>
                <div>
                    <x-input-label for="to" value="Sampai tanggal" />
                    <input wire:model.live="to" id="to" type="date" class="field mt-1.5" />
                </div>
                <div>
                    <x-input-label for="statusFilter" value="Status" />
                    <select wire:model.live="statusFilter" id="statusFilter" class="field mt-1.5">
                        <option value="">Semua status</option>
                        @foreach (RequestStatus::cases() as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="budgetCode" value="Kode anggaran" />
                    <select wire:model.live="budgetCode" id="budgetCode" class="field mt-1.5">
                        <option value="">Semua kode</option>
                        @foreach ($this->budgetCodes as $code)
                            <option value="{{ $code->code }}">{{ $code->code }} — {{ $code->description }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-stat-card label="Total Pengajuan" :value="$this->totals['count']" icon="document-text" />
            <x-stat-card label="Total Nominal" :value="Money::compact($this->totalNominal)" icon="wallet" tone="amber" :hint="Money::rupiah($this->totalNominal)" />
            <x-stat-card label="Nominal Selesai" :value="Money::compact($this->paidNominal)" icon="check-circle" tone="emerald" :hint="Money::rupiah($this->paidNominal)" />
        </div>

        <div class="card overflow-hidden">
            @if ($this->queried->isEmpty())
                <x-empty-state icon="chart-bar" title="Tidak ada data pada periode ini" description="Ubah rentang tanggal atau filter untuk melihat data lain." />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-100 text-sm">
                        <thead class="table-head">
                            <tr>
                                <th class="px-5 py-3">Nomor</th>
                                <th class="px-5 py-3">Pengaju</th>
                                <th class="px-5 py-3">Tanggal</th>
                                <th class="px-5 py-3">Kode</th>
                                <th class="px-5 py-3 text-right">Nominal</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($this->queried as $item)
                                <tr wire:key="r-{{ $item->id }}" class="transition hover:bg-slate-50">
                                    <td class="px-5 py-3.5 font-semibold whitespace-nowrap text-slate-900">{{ $item->request_number }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-slate-900">{{ $item->requester->name }}</td>
                                    <td class="px-5 py-3.5 whitespace-nowrap text-slate-500">{{ $item->submitted_at?->translatedFormat('d M Y') }}</td>
                                    <td class="px-5 py-3.5 font-mono text-xs whitespace-nowrap text-slate-600">{{ $item->budget_code ?: '—' }}</td>
                                    <td class="px-5 py-3.5 text-right font-semibold whitespace-nowrap text-slate-900">{{ Money::rupiah($item->nominal) }}</td>
                                    <td class="px-5 py-3.5"><x-status-badge :status="$item->status" short /></td>
                                    <td class="px-5 py-3.5 text-right">
                                        <a href="{{ route('requests.show', ['id' => $item->id]) }}" wire:navigate class="font-semibold text-brand-600 hover:text-brand-700">Detail</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-100 px-5 py-3">
                    {{ $this->queried->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
