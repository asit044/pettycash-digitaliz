<?php

use App\Enums\Role;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('Pengguna')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'peran', except: '')]
    public string $roleFilter = '';

    /** null = closed, 0 = create, >0 = editing that user id */
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $role = 'requester';

    public string $password = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->withCount('requests')
            ->when($this->roleFilter !== '', fn ($q) => $q->where('role', $this->roleFilter))
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('email', 'like', '%'.$this->search.'%')))
            ->orderByRaw("CASE role WHEN 'admin' THEN 1 WHEN 'finance' THEN 2 WHEN 'head' THEN 3 ELSE 4 END")
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function roleCounts(): array
    {
        return User::query()->selectRaw('role, COUNT(*) as total')->groupBy('role')->pluck('total', 'role')->all();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->editingId = 0;
    }

    public function edit(int $id): void
    {
        $user = User::findOrFail($id);

        $this->resetForm();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->role = $user->role;
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        abort_unless(auth()->user()->can('manage-settings'), 403);

        $creating = $this->editingId === 0;

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($creating ? null : $this->editingId)],
            'phone' => ['nullable', 'string', 'max:20', Phone::rule()],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => $creating ? ['required', 'string', Password::defaults()] : ['nullable', 'string', Password::defaults()],
        ], attributes: ['name' => 'nama', 'phone' => 'nomor WhatsApp', 'role' => 'peran']);

        // An admin cannot demote themselves and lock everyone out of settings.
        if (! $creating && $this->editingId === auth()->id() && $this->role !== Role::Admin->value) {
            $this->addError('role', 'Anda tidak dapat mengubah peran akun Anda sendiri.');

            return;
        }

        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => Phone::normalize($this->phone),
            'role' => $this->role,
        ];

        if (filled($this->password)) {
            $data['password'] = $this->password;
        }

        if ($creating) {
            User::create($data + ['email_verified_at' => now()]);
            session()->flash('status', 'Pengguna '.$this->name.' ditambahkan.');
        } else {
            User::findOrFail($this->editingId)->update($data);
            session()->flash('status', 'Data '.$this->name.' diperbarui.');
        }

        $this->resetForm();
        unset($this->users, $this->roleCounts);
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'name', 'email', 'phone', 'password');
        $this->role = Role::Requester->value;
        $this->resetValidation();
    }
}; ?>

<div>
    <x-slot name="header">
        <x-page-header title="Pengguna" description="Atur peran dan nomor WhatsApp setiap anggota tim.">
            <button type="button" wire:click="create" class="btn-primary">
                <x-icon name="plus" class="size-4" /> Tambah Pengguna
            </button>
        </x-page-header>
    </x-slot>

    <div class="space-y-4">
        @if (session('status'))
            <x-alert type="success" dismissible>{{ session('status') }}</x-alert>
        @endif

        <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            @php
                $tabs = ['' => ['label' => 'Semua', 'count' => array_sum($this->roleCounts)]];
                foreach (Role::cases() as $r) {
                    $tabs[$r->value] = ['label' => $r->label(), 'count' => (int) ($this->roleCounts[$r->value] ?? 0)];
                }
            @endphp
            <x-status-tabs :tabs="$tabs" :current="$roleFilter" model="roleFilter" />

            <div class="relative w-full xl:w-72 xl:shrink-0">
                <x-icon name="magnifying-glass" class="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-slate-400" />
                <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nama atau email…" class="field pl-10" />
            </div>
        </div>

        <div class="card overflow-hidden">
            @if ($this->users->isEmpty())
                <x-empty-state icon="users" title="Tidak ada pengguna yang cocok" />
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($this->users as $user)
                        @php($roleEnum = Role::tryFrom($user->role))
                        <li wire:key="u-{{ $user->id }}" class="flex items-center gap-4 px-5 py-3.5">
                            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand-50 text-sm font-bold text-brand-700">
                                {{ str($user->name)->explode(' ')->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-slate-900">
                                    {{ $user->name }}
                                    @if ($user->id === auth()->id())
                                        <span class="ml-1 text-xs font-normal text-slate-400">(Anda)</span>
                                    @endif
                                </p>
                                <p class="truncate text-xs text-slate-500">
                                    {{ $user->email }}
                                    · @if ($user->phone) {{ $user->phone }} @else <span class="text-amber-600">WA belum diisi</span> @endif
                                </p>
                            </div>
                            <span @class([
                                'hidden rounded-full px-2.5 py-1 text-xs font-semibold sm:inline-flex',
                                'bg-violet-50 text-violet-700' => $user->role === 'admin',
                                'bg-sky-50 text-sky-700' => $user->role === 'finance',
                                'bg-amber-50 text-amber-700' => $user->role === 'head',
                                'bg-slate-100 text-slate-600' => $user->role === 'requester',
                            ])>{{ $roleEnum?->label() ?? $user->role }}</span>
                            <span class="hidden w-24 text-right text-xs text-slate-400 md:block">{{ $user->requests_count }} pengajuan</span>
                            <button type="button" wire:click="edit({{ $user->id }})" class="btn-ghost px-2.5 py-2" title="Ubah">
                                <x-icon name="pencil" class="size-4" /><span class="sr-only">Ubah</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                <div class="border-t border-slate-100 px-5 py-3">
                    {{ $this->users->links() }}
                </div>
            @endif
        </div>
    </div>

    {{-- Create / edit drawer --}}
    @if ($editingId !== null)
        <div class="fixed inset-0 z-50 flex justify-end" x-data x-on:keydown.escape.window="$wire.cancel()">
            <div class="absolute inset-0 bg-slate-900/40" wire:click="cancel"></div>
            <form wire:submit="save" class="relative flex h-full w-full max-w-md flex-col bg-white shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-5">
                    <h2 class="text-lg font-bold text-slate-900">{{ $editingId === 0 ? 'Tambah Pengguna' : 'Ubah Pengguna' }}</h2>
                    <button type="button" wire:click="cancel" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Tutup">
                        <x-icon name="x-mark" />
                    </button>
                </div>

                <div class="flex-1 space-y-5 overflow-y-auto px-6 py-6">
                    <div>
                        <x-input-label for="u-name" value="Nama lengkap" required />
                        <x-text-input wire:model="name" id="u-name" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('name')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="u-email" value="Email" required />
                        <x-text-input wire:model="email" id="u-email" type="email" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="u-phone" value="Nomor WhatsApp" />
                        <x-text-input wire:model="phone" id="u-phone" type="tel" placeholder="0812xxxxxxxx" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('phone')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label value="Peran" required />
                        <div class="mt-1.5 grid gap-2">
                            @foreach (Role::cases() as $r)
                                <label @class(['flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition', 'border-brand-500 bg-brand-50/50 ring-1 ring-brand-500' => $role === $r->value, 'border-slate-200 hover:bg-slate-50' => $role !== $r->value])>
                                    <input type="radio" wire:model.live="role" value="{{ $r->value }}" class="mt-0.5 text-brand-600 focus:ring-brand-500">
                                    <span class="text-sm">
                                        <span class="block font-semibold text-slate-900">{{ $r->label() }}</span>
                                        <span class="block text-xs text-slate-500">
                                            {{ match ($r) {
                                                Role::Requester => 'Membuat pengajuan & memantau miliknya sendiri.',
                                                Role::Admin => 'Validasi, kode anggaran, laporan, pengaturan.',
                                                Role::Finance => 'Memproses pencairan & mengunggah bukti transfer.',
                                                Role::Head => 'Melihat laporan & ringkasan.',
                                            } }}
                                        </span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('role')" class="mt-1.5" />
                    </div>
                    <div>
                        <x-input-label for="u-password" :value="$editingId === 0 ? 'Password awal' : 'Password baru'" :required="$editingId === 0" />
                        <x-text-input wire:model="password" id="u-password" type="password" autocomplete="new-password" class="mt-1.5" />
                        @if ($editingId !== 0)
                            <p class="mt-1.5 text-xs text-slate-500">Kosongkan jika tidak ingin mengubah password.</p>
                        @endif
                        <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 px-6 py-4">
                    <button type="button" wire:click="cancel" class="btn-secondary">Batal</button>
                    <x-primary-button wire:loading.attr="disabled">Simpan</x-primary-button>
                </div>
            </form>
        </div>
    @endif
</div>
