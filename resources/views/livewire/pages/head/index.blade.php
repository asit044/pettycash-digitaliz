<?php

use App\Enums\RequestStatus;
use App\Models\PettyCashRequest;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
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
        ]);

        $this->appliedFrom = $this->from;
        $this->appliedTo = $this->to;
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

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Monitoring Petty Cash</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Dari Tanggal" />
                            <input wire:model.live="from" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <x-input-error :messages="$errors->get('from')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label value="Sampai Tanggal" />
                            <input wire:model.live="to" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-indigo-500 focus:ring-indigo-500" />
                            <x-input-error :messages="$errors->get('to')" class="mt-2" />
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-gray-500">Periode {{ $this->appliedFrom }} s/d {{ $this->appliedTo }} (inklusif).</p>
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Total Pengajuan</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">{{ $this->totalCount }}</p>
                </div>
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <p class="text-sm text-gray-500">Total Nominal</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900">Rp {{ number_format($this->totalNominal, 0, ',', '.') }}</p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="font-semibold text-gray-900">Ringkasan per Status</h3>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Jumlah</th>
                                    <th class="px-4 py-3 text-right">Nominal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($this->statusBreakdown as $row)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-gray-900">{{ $row['label'] }}</td>
                                        <td class="px-4 py-3 text-right text-gray-900">{{ $row['count'] }}</td>
                                        <td class="px-4 py-3 text-right text-gray-900">Rp {{ number_format($row['nominal'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="font-semibold text-gray-900">Ringkasan per Kode Anggaran</h3>
                    @if ($this->budgetSummary->isEmpty())
                        <p class="mt-4 text-sm text-gray-500">Belum ada kode anggaran pada periode ini.</p>
                    @else
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3">Kode</th>
                                        <th class="px-4 py-3">Uraian</th>
                                        <th class="px-4 py-3 text-right">Jumlah</th>
                                        <th class="px-4 py-3 text-right">Nominal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($this->budgetSummary as $budget)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-medium text-gray-900">{{ $budget->budget_code }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ $budget->budget_description }}</td>
                                            <td class="px-4 py-3 text-right text-gray-900">{{ $budget->total }}</td>
                                            <td class="px-4 py-3 text-right text-gray-900">Rp {{ number_format($budget->nominal, 0, ',', '.') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="font-semibold text-gray-900">Pengajuan Terbaru</h3>
                    @if ($this->recentRequests->isEmpty())
                        <p class="mt-4 text-sm text-gray-500">Tidak ada data pada periode ini.</p>
                    @else
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-4 py-3">Nomor</th>
                                        <th class="px-4 py-3">Pengaju</th>
                                        <th class="px-4 py-3 text-right">Nominal</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Tanggal</th>
                                        <th class="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($this->recentRequests as $item)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-medium text-gray-900">{{ $item->request_number }}</td>
                                            <td class="px-4 py-3 text-gray-900">{{ $item->requester->name }}</td>
                                            <td class="px-4 py-3 text-right text-gray-900">Rp {{ number_format($item->nominal, 0, ',', '.') }}</td>
                                            <td class="px-4 py-3"><x-status-badge :status="$item->status" /></td>
                                            <td class="px-4 py-3 text-gray-600">{{ $item->submitted_at?->format('d M Y') }}</td>
                                            <td class="px-4 py-3 text-right">
                                                <a href="{{ route('requests.show', ['id' => $item->id]) }}" wire:navigate class="text-indigo-600 hover:text-indigo-500 font-medium">
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
