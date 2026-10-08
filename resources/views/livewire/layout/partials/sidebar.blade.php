{{-- Expects $menu and $roleLabel from layout.navigation --}}
<aside class="flex h-full w-full flex-col overflow-y-auto bg-brand-950 px-4 pb-4">
    <div class="flex h-20 shrink-0 items-center px-2">
        <a href="{{ route('dashboard') }}" wire:navigate>
            <x-application-logo with-text dark />
        </a>
    </div>

    <nav class="flex flex-1 flex-col" aria-label="Menu utama">
        <p class="px-3 pb-2 text-[11px] font-semibold tracking-wider text-brand-300/70 uppercase">Menu</p>
        <ul class="space-y-1">
            @foreach ($menu as $item)
                @php($active = request()->routeIs($item['active']))
                <li>
                    <a href="{{ route($item['route']) }}" wire:navigate
                       @class([
                           'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                           'bg-white/10 text-white shadow-inner' => $active,
                           'text-brand-100/80 hover:bg-white/5 hover:text-white' => ! $active,
                       ])
                       @if ($active) aria-current="page" @endif>
                        <x-icon :name="$item['icon']" @class(['size-5', 'text-white' => $active, 'text-brand-300 group-hover:text-white' => ! $active]) />
                        <span class="flex-1">{{ $item['label'] }}</span>
                        @if (($item['badge'] ?? 0) > 0)
                            <span class="min-w-6 rounded-full bg-amber-400 px-2 py-0.5 text-center text-xs font-bold text-amber-950">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="mt-auto pt-6">
            <div class="rounded-2xl bg-white/5 p-3 ring-1 ring-white/10">
                <div class="flex items-center gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-full bg-brand-500 text-sm font-bold text-white">
                        {{ str(auth()->user()->name)->explode(' ')->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
                    </span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-white"
                           x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name"
                           x-on:profile-updated.window="name = $event.detail.name">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-brand-200/80">{{ $roleLabel }}</p>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-2">
                    <a href="{{ route('profile') }}" wire:navigate
                       class="flex items-center justify-center gap-1.5 rounded-lg bg-white/5 px-2 py-2 text-xs font-semibold text-brand-100 transition hover:bg-white/10 hover:text-white">
                        <x-icon name="user" class="size-4" /> Profil
                    </a>
                    <button type="button" wire:click="logout"
                            class="flex items-center justify-center gap-1.5 rounded-lg bg-white/5 px-2 py-2 text-xs font-semibold text-brand-100 transition hover:bg-rose-500/20 hover:text-white">
                        <x-icon name="logout" class="size-4" /> Keluar
                    </button>
                </div>
            </div>
        </div>
    </nav>
</aside>
