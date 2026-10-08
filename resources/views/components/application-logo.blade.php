@props(['withText' => false, 'dark' => false])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-md shadow-brand-600/30">
        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h13A2.5 2.5 0 0 1 21 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 16.5v-9Z" />
            <path d="M16 12h2" />
            <path d="M7 9.5h5M7 14.5h3" />
        </svg>
    </span>
    @if ($withText)
        <span class="leading-tight">
            <span @class(['block text-sm font-bold tracking-tight', 'text-white' => $dark, 'text-slate-900' => ! $dark])>Petty Cash</span>
            <span @class(['block text-xs font-medium', 'text-brand-200' => $dark, 'text-slate-500' => ! $dark])>Digitaliz Internal</span>
        </span>
    @endif
</span>
