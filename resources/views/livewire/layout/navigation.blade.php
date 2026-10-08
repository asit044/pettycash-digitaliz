<?php

use App\Enums\RequestStatus;
use App\Enums\Role;
use App\Livewire\Actions\Logout;
use App\Models\PettyCashRequest;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }

    /**
     * Sidebar menu for the signed-in user's role, with queue counters
     * so each actor sees at a glance what is waiting for them.
     *
     * @return array<int, array{label: string, route: string, active: string, icon: string, badge?: int}>
     */
    public function menu(): array
    {
        $user = auth()->user();

        $items = [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'home'],
        ];

        if ($user->isRequester()) {
            $items[] = ['label' => 'Ajukan Baru', 'route' => 'requests.create', 'active' => 'requests.create', 'icon' => 'document-plus'];
            $items[] = [
                'label' => 'Pengajuan Saya', 'route' => 'requests.index', 'active' => 'requests.index', 'icon' => 'document-text',
                'badge' => $user->requests()->where('status', RequestStatus::NeedsRevision->value)->count(),
            ];
        }

        if ($user->isAdmin()) {
            $items[] = [
                'label' => 'Validasi Pengajuan', 'route' => 'admin.index', 'active' => 'admin.index', 'icon' => 'clipboard-check',
                'badge' => PettyCashRequest::query()->where('status', RequestStatus::PendingReview->value)->count(),
            ];
        }

        if ($user->isFinance()) {
            $items[] = [
                'label' => 'Pencairan', 'route' => 'finance.index', 'active' => 'finance.index', 'icon' => 'banknotes',
                'badge' => PettyCashRequest::query()->where('status', RequestStatus::Processing->value)->count(),
            ];
        }

        if ($user->isHead()) {
            $items[] = ['label' => 'Monitoring', 'route' => 'head.index', 'active' => 'head.index', 'icon' => 'presentation-chart'];
        }

        if ($user->can('view-any-requests')) {
            $items[] = ['label' => 'Laporan', 'route' => 'reports.index', 'active' => 'reports.index', 'icon' => 'chart-bar'];
        }

        if ($user->can('manage-settings')) {
            $items[] = ['label' => 'Pengguna', 'route' => 'settings.users', 'active' => 'settings.users', 'icon' => 'users'];
            $items[] = ['label' => 'Pengaturan', 'route' => 'settings.index', 'active' => 'settings.index', 'icon' => 'cog'];
        }

        return $items;
    }

    public function roleLabel(): string
    {
        return Role::tryFrom((string) auth()->user()->role)?->label() ?? '-';
    }
}; ?>

@php
    $menu = $this->menu();
    $roleLabel = $this->roleLabel();
@endphp

<div x-data="{ open: false }" x-on:keydown.escape.window="open = false">
    {{-- Mobile top bar --}}
    <div class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur lg:hidden">
        <button type="button" x-on:click="open = true" class="-ml-1 rounded-lg p-2 text-slate-600 hover:bg-slate-100" aria-label="Buka menu">
            <x-icon name="bars" class="size-6" />
        </button>
        <a href="{{ route('dashboard') }}" wire:navigate>
            <x-application-logo with-text />
        </a>
    </div>

    {{-- Mobile overlay --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-40 lg:hidden">
        <div x-show="open" x-transition.opacity class="absolute inset-0 bg-slate-900/50" x-on:click="open = false"></div>
        <div x-show="open"
             x-transition:enter="transition duration-200 ease-out" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition duration-150 ease-in" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
             class="relative h-full w-72 max-w-[85%]">
            <button type="button" x-on:click="open = false" class="absolute top-4 -right-12 rounded-lg p-2 text-white" aria-label="Tutup menu">
                <x-icon name="x-mark" class="size-6" />
            </button>
            @include('livewire.layout.partials.sidebar')
        </div>
    </div>

    {{-- Desktop sidebar --}}
    <div class="hidden lg:fixed lg:inset-y-0 lg:z-30 lg:flex lg:w-72">
        @include('livewire.layout.partials.sidebar')
    </div>
</div>
