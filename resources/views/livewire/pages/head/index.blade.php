<?php

use App\Enums\RequestStatus;
use App\Models\PettyCashRequest;
use App\Support\Money;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Monitoring')] class extends Component
{
    public string $from = '';

    public string $to = '';

    public string $appliedFrom = '';

    public string $appliedTo = '';

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->format('Y-m-d');
        $this->to = now()->format('Y-m-d');
        $this->appliedFrom = $this->from;
        $this->appliedTo = $this->to;
    }

    public function updated(): void
    {
        $this->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], attributes: ['from' => 'tanggal awal', 'to' => 'tanggal akhir']);

        $this->appliedFrom = $this->from;
        $this->appliedTo = $this->to;
    }

    public function preset(string $range): void
    {
        [$from, $to] = match ($range) {
            'last-month' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'year' => [now()->startOfYear(), now()],
            default => [now()->startOfMonth(), now()],
        };

        $this->from = $this->appliedFrom = $from->format('Y-m-d');
        $this->to = $this->appliedTo = $to->format('Y-m-d');
        $this->resetValidation();
    }

    private function base()
    {
        return PettyCashRequest::query()
            ->whereBetween('submitted_at', [$this->appliedFrom.' 00:00:00', $this->appliedTo.' 23:59:59']);
    }

    #[Computed]
    public function totalCount(): int
    {
        return (clone $this->base())->count();
    }

    #[Computed]
    public function totalNominal(): float
    {
        return (float) (clone $this->base())->sum('nominal');
    }

    #[Computed]
    public function statusBreakdown(): array
    {
        $grouped = (clone $this->base())
            ->selectRaw('status, COUNT(*) as total, COALESCE(SUM(nominal), 0) as nominal')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $rows = [];

        foreach (RequestStatus::cases() as $status) {
            $row = $grouped[$status->value] ?? null;

            $rows[] = [
                'value' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($row->total ?? 0),
                'nominal' => (float) ($row->nominal ?? 0),
            ];
        }

        return $rows;
    }

    #[Computed]
    public function budgetSummary()
    {
        return (clone $this->base())
            ->selectRaw('budget_code, MAX(budget_description) as budget_description, COUNT(*) as total, COALESCE(SUM(nominal), 0) as nominal')
            ->whereNotNull('budget_code')
            ->groupBy('budget_code')
            ->orderByDesc('nominal')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function recentRequests()
    {
        return (clone $this->base())
            ->with('requester')
            ->latest('submitted_at')
            ->limit(10)
            ->get();
    }
}; ?>

@php
    $breakdown = collect($this->statusBreakdown)->keyBy('value');
    $done = $breakdown['done'];
    $inFlight = $breakdown['pending_review']['nominal'] + $breakdown['processing']['nominal'];
    $maxBudget = (float) ($this->budgetSummary->max('nominal') ?: 1);
@endphp

<div>
    <x-slot name="header">
        <x-page-header title="Monitoring Petty Cash" description="Ringkasan pengajuan, status, dan penggunaan anggaran untuk Head of Digitaliz.">
            <a href="{{ route('reports.index', ['from' => $appliedFrom, 'to' => $appliedTo]) }}" wire:navigate class="btn-secondary">
                <x-icon name="chart-bar" class="size-4" /> Lihat rincian di Laporan
            </a>
        </x-page-header>
    </x-slot>

    <div class="space-y-6">
        <div class="card flex flex-col gap-4 p-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid flex-1 gap-4 sm:grid-cols-2 lg:max-w-lg">
                <div>
                    <x-input-label for="from" value="Dari tanggal" />
                    <input wire:model.live="from" id="from" type="date" class="field mt-1.5" />
                    <x-input-error :messages="$errors->get('from')" class="mt-1.5" />
                </div>
                <div>
                    <x-input-label for="to" value="Sampai tanggal" />
                    <input wire:model.live="to" id="to" type="date" class="field mt-1.5" />
                    <x-input-error :messages="$errors->get('to')" class="mt-1.5" />
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="preset('month')" class="btn-secondary px-3 py-2 text-xs">Bulan ini</button>
                <button type="button" wire:click="preset('last-month')" class="btn-secondary px-3 py-2 text-xs">Bulan lalu</button>
                <button type="button" wire:click="preset('year')" class="btn-secondary px-3 py-2 text-xs">Tahun ini</button>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Total Pengajuan" :value="$this->totalCount" icon="document-text" />
            <x-stat-card label="Total Nominal" :value="Money::compact($this->totalNominal)" icon="wallet" tone="brand" :hint="Money::rupiah($this->totalNominal)" />
            <x-stat-card label="Sudah Dicairkan" :value="Money::compact($done['nominal'])" icon="check-circle" tone="emerald" :hint="$done['count'].' pengajuan'" />
            <x-stat-card label="Sedang Berjalan" :value="Money::compact($inFlight)" icon="clock" tone="amber"
                         :hint="($breakdown['pending_review']['count'] + $breakdown['processing']['count']).' pengajuan'" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="card p-5 sm:p-6">
                <h2 class="font-semibold text-slate-900">Ringkasan per Status</h2>
                <p class="text-xs text-slate-500">Periode {{ \Illuminate\Support\Carbon::parse($appliedFrom)->translatedFormat('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($appliedTo)->translatedFormat('d M Y') }}</p>

                <ul class="mt-5 space-y-4">
                    @foreach (RequestStatus::cases() as $status)
                        @php
                            $row = $breakdown[$status->value];
                            $pct = $this->totalCount > 0 ? round($row['count'] / $this->totalCount * 100) : 0;
                        @endphp
                        <li>
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="flex items-center gap-2 font-medium text-slate-700">
                                    <span class="size-2.5 rounded-full {{ $status->dotClasses() }}"></span>
                                    {{ $row['label'] }}
                                </span>
                                <span class="text-right">
                                    <span class="font-semibold text-slate-900">{{ $row['count'] }}</span>
                                    <span class="text-slate-400">·</span>
                                    <span class="text-slate-500">{{ Money::rupiah($row['nominal']) }}</span>
                                </span>
                            </div>
                            <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full {{ $status->dotClasses() }} transition-all" style="width: {{ $pct }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card p-5 sm:p-6">
                <h2 class="font-semibold text-slate-900">Ringkasan per Kode Anggaran</h2>
                <p class="text-xs text-slate-500">10 kode dengan nominal terbesar</p>

                @if ($this->budgetSummary->isEmpty())
                    <x-empty-state icon="tag" title="Belum ada kode anggaran" description="Belum ada pengajuan yang disetujui pada periode ini." class="py-10" />
                @else
                    <ul class="mt-5 space-y-4">
                        @foreach ($this->budgetSummary as $budget)
                            <li>
                                <div class="flex items-center justify-between gap-3 text-sm">
                                    <span class="min-w-0 truncate">
                                        <span class="font-mono font-semibold text-slate-900">{{ $budget->budget_code }}</span>
                                        <span class="text-slate-500">{{ $budget->budget_description }}</span>
                                    </span>
                                    <span class="shrink-0 font-semibold text-slate-900">{{ Money::rupiah($budget->nominal) }}</span>
                                </div>
                                <div class="mt-2 flex items-center gap-3">
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                        <div class="h-full rounded-full bg-brand-500" style="width: {{ round($budget->nominal / $maxBudget * 100) }}%"></div>
                                    </div>
                                    <span class="w-20 shrink-0 text-right text-xs text-slate-500">{{ $budget->total }} pengajuan</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="border-b border-slate-100 px-5 py-4 sm:px-6">
                <h2 class="font-semibold text-slate-900">Pengajuan Terbaru</h2>
            </div>
            @if ($this->recentRequests->isEmpty())
                <x-empty-state title="Tidak ada data pada periode ini" />
            @else
                <div class="divide-y divide-slate-100">
                    @foreach ($this->recentRequests as $item)
                        <x-request-item :item="$item" show-requester wire:key="h-{{ $item->id }}" />
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
