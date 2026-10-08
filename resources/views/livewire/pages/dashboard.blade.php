<?php

use App\Enums\RequestStatus;
use App\Models\PettyCashRequest;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Dashboard')] class extends Component
{
    /**
     * Requesters only ever see their own data; every other role sees all.
     */
    private function scoped(): Builder
    {
        $user = auth()->user();

        return PettyCashRequest::query()
            ->when($user->isRequester(), fn (Builder $q) => $q->where('requester_id', $user->id));
    }

    #[Computed]
    public function counts(): array
    {
        $rows = $this->scoped()
            ->selectRaw('status, COUNT(*) as total, COALESCE(SUM(nominal), 0) as nominal')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $counts = [];
        foreach (RequestStatus::cases() as $status) {
            $counts[$status->value] = [
                'count' => (int) ($rows[$status->value]->total ?? 0),
                'nominal' => (float) ($rows[$status->value]->nominal ?? 0),
            ];
        }

        $counts['all'] = [
            'count' => array_sum(array_column($counts, 'count')),
            'nominal' => array_sum(array_column($counts, 'nominal')),
        ];

        return $counts;
    }

    #[Computed]
    public function doneThisMonth(): array
    {
        $query = $this->scoped()
            ->where('status', RequestStatus::Done->value)
            ->where('completed_at', '>=', now()->startOfMonth());

        return ['count' => (clone $query)->count(), 'nominal' => (float) $query->sum('nominal')];
    }

    #[Computed]
    public function needsRevision()
    {
        return $this->scoped()
            ->where('status', RequestStatus::NeedsRevision->value)
            ->latest('reviewed_at')
            ->get();
    }

    #[Computed]
    public function queue()
    {
        $user = auth()->user();

        return match (true) {
            $user->isAdmin() => PettyCashRequest::query()->with('requester')
                ->where('status', RequestStatus::PendingReview->value)
                ->oldest('submitted_at')->limit(6)->get(),
            $user->isFinance() => PettyCashRequest::query()->with('requester')
                ->where('status', RequestStatus::Processing->value)
                ->oldest('reviewed_at')->limit(6)->get(),
            default => $this->scoped()->with('requester')->latest('submitted_at')->limit(6)->get(),
        };
    }

    public function greeting(): string
    {
        $hour = (int) now()->format('G');

        return match (true) {
            $hour < 11 => 'Selamat pagi',
            $hour < 15 => 'Selamat siang',
            $hour < 19 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }
}; ?>

@php
    $user = auth()->user();
    $c = $this->counts;
@endphp

<div>
    <x-slot name="header">
        <x-page-header :title="$this->greeting().', '.str($user->name)->before(' ').'!'"
                       :description="now()->translatedFormat('l, d F Y')">
            @if ($user->isRequester())
                <a href="{{ route('requests.create') }}" wire:navigate class="btn-primary">
                    <x-icon name="plus" class="size-4" /> Ajukan Baru
                </a>
            @elseif ($user->can('view-any-requests'))
                <a href="{{ route('reports.index') }}" wire:navigate class="btn-secondary">
                    <x-icon name="chart-bar" class="size-4" /> Lihat Laporan
                </a>
            @endif
        </x-page-header>
    </x-slot>

    <div class="space-y-6">
        {{-- Revision reminders for the requester --}}
        @if ($user->isRequester() && $this->needsRevision->isNotEmpty())
            <x-alert type="warning" title="{{ $this->needsRevision->count() }} pengajuan perlu Anda revisi">
                <ul class="mt-1 space-y-1">
                    @foreach ($this->needsRevision as $item)
                        <li>
                            <a href="{{ route('requests.show', ['id' => $item->id]) }}" wire:navigate class="font-semibold underline underline-offset-2 hover:no-underline">
                                {{ $item->request_number }}
                            </a>
                            — {{ str($item->description)->limit(60) }}
                        </li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        {{-- Stat tiles --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @if ($user->isRequester())
                <x-stat-card label="Total Pengajuan" :value="$c['all']['count']" icon="document-text" :href="route('requests.index')" />
                <x-stat-card label="Sedang Berjalan" :value="$c['pending_review']['count'] + $c['processing']['count']" icon="clock" tone="amber"
                             hint="Menunggu validasi / pencairan" />
                <x-stat-card label="Perlu Revisi" :value="$c['needs_revision']['count']" icon="arrow-path" tone="orange" />
                <x-stat-card label="Total Dicairkan" :value="Money::compact($c['done']['nominal'])" icon="wallet" tone="emerald"
                             :hint="$c['done']['count'].' pengajuan selesai'" />
            @elseif ($user->isAdmin())
                <x-stat-card label="Menunggu Validasi" :value="$c['pending_review']['count']" icon="clipboard-check" tone="amber"
                             :hint="Money::rupiah($c['pending_review']['nominal'])" :href="route('admin.index')" />
                <x-stat-card label="Menunggu Revisi Pengaju" :value="$c['needs_revision']['count']" icon="arrow-path" tone="orange" />
                <x-stat-card label="Diproses Finance" :value="$c['processing']['count']" icon="banknotes" tone="sky"
                             :hint="Money::rupiah($c['processing']['nominal'])" />
                <x-stat-card label="Selesai Bulan Ini" :value="Money::compact($this->doneThisMonth['nominal'])" icon="check-circle" tone="emerald"
                             :hint="$this->doneThisMonth['count'].' pengajuan'" />
            @elseif ($user->isFinance())
                <x-stat-card label="Siap Dicairkan" :value="$c['processing']['count']" icon="banknotes" tone="sky" :href="route('finance.index')" />
                <x-stat-card label="Nominal Antrean" :value="Money::compact($c['processing']['nominal'])" icon="wallet" tone="amber"
                             :hint="Money::rupiah($c['processing']['nominal'])" />
                <x-stat-card label="Selesai Bulan Ini" :value="$this->doneThisMonth['count']" icon="check-circle" tone="emerald" hint="pengajuan" />
                <x-stat-card label="Dicairkan Bulan Ini" :value="Money::compact($this->doneThisMonth['nominal'])" icon="chart-bar" tone="brand"
                             :hint="Money::rupiah($this->doneThisMonth['nominal'])" />
            @else
                <x-stat-card label="Total Pengajuan" :value="$c['all']['count']" icon="document-text" :href="route('head.index')" />
                <x-stat-card label="Total Nominal" :value="Money::compact($c['all']['nominal'])" icon="wallet" tone="brand"
                             :hint="Money::rupiah($c['all']['nominal'])" />
                <x-stat-card label="Sedang Berjalan" :value="$c['pending_review']['count'] + $c['processing']['count']" icon="clock" tone="amber" />
                <x-stat-card label="Dicairkan Bulan Ini" :value="Money::compact($this->doneThisMonth['nominal'])" icon="check-circle" tone="emerald"
                             :hint="$this->doneThisMonth['count'].' pengajuan'" />
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Queue / recent list --}}
            <div class="card overflow-hidden lg:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 class="font-semibold text-slate-900">
                            @if ($user->isAdmin()) Antrean Validasi
                            @elseif ($user->isFinance()) Antrean Pencairan
                            @else Pengajuan Terbaru
                            @endif
                        </h2>
                        <p class="text-xs text-slate-500">
                            @if ($user->isAdmin() || $user->isFinance()) Diurutkan dari yang paling lama menunggu
                            @else 6 pengajuan terakhir
                            @endif
                        </p>
                    </div>
                    @php
                        $more = match (true) {
                            $user->isAdmin() => route('admin.index'),
                            $user->isFinance() => route('finance.index'),
                            $user->isRequester() => route('requests.index'),
                            default => route('reports.index'),
                        };
                    @endphp
                    <a href="{{ $more }}" wire:navigate class="shrink-0 text-sm font-semibold whitespace-nowrap text-brand-600 hover:text-brand-700">Lihat semua</a>
                </div>

                @if ($this->queue->isEmpty())
                    <x-empty-state
                        :icon="$user->isRequester() ? 'document-plus' : 'check-circle'"
                        :title="$user->isRequester() ? 'Belum ada pengajuan' : 'Antrean kosong'"
                        :description="$user->isRequester() ? 'Ajukan reimbursement atau kas kecil pertama Anda.' : 'Tidak ada pengajuan yang menunggu tindakan Anda.'">
                        @if ($user->isRequester())
                            <a href="{{ route('requests.create') }}" wire:navigate class="btn-primary"><x-icon name="plus" class="size-4" /> Ajukan Sekarang</a>
                        @endif
                    </x-empty-state>
                @else
                    <div class="divide-y divide-slate-100">
                        @foreach ($this->queue as $item)
                            <x-request-item :item="$item" :show-requester="! $user->isRequester()"
                                            :date-field="$user->isFinance() ? 'reviewed_at' : 'submitted_at'" />
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Side panel --}}
            <div class="space-y-6">
                <div class="card p-5">
                    <h2 class="font-semibold text-slate-900">Status Pengajuan</h2>
                    <p class="text-xs text-slate-500">{{ $user->isRequester() ? 'Seluruh pengajuan Anda' : 'Seluruh pengajuan di sistem' }}</p>
                    <ul class="mt-4 space-y-3">
                        @foreach (RequestStatus::cases() as $status)
                            @php
                                $count = $c[$status->value]['count'];
                                $pct = $c['all']['count'] > 0 ? round($count / $c['all']['count'] * 100) : 0;
                            @endphp
                            <li>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="flex items-center gap-2 text-slate-600">
                                        <span class="size-2 rounded-full {{ $status->dotClasses() }}"></span>
                                        {{ $status->label() }}
                                    </span>
                                    <span class="font-semibold text-slate-900">{{ $count }}</span>
                                </div>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full {{ $status->dotClasses() }}" style="width: {{ $pct }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>

                @if ($user->isRequester())
                    <div class="card bg-gradient-to-br from-brand-600 to-brand-800 p-5 text-white">
                        <x-icon name="information-circle" class="size-6 text-brand-200" />
                        <h3 class="mt-3 font-semibold">Tips pengajuan cepat disetujui</h3>
                        <ul class="mt-2 space-y-1.5 text-sm text-brand-100">
                            <li>• Tulis keperluan dengan jelas &amp; spesifik.</li>
                            <li>• Lampirkan invoice / struk yang terbaca.</li>
                            <li>• Pastikan nomor WhatsApp di profil sudah benar.</li>
                        </ul>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
