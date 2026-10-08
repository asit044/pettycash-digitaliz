@props(['type' => 'info', 'title' => null, 'dismissible' => false])

@php
    $styles = [
        'success' => ['border-emerald-200 bg-emerald-50 text-emerald-800', 'check-circle', 'text-emerald-500'],
        'error' => ['border-rose-200 bg-rose-50 text-rose-800', 'x-circle', 'text-rose-500'],
        'warning' => ['border-amber-200 bg-amber-50 text-amber-800', 'exclamation-triangle', 'text-amber-500'],
        'info' => ['border-sky-200 bg-sky-50 text-sky-800', 'information-circle', 'text-sky-500'],
    ];
    [$box, $icon, $iconColor] = $styles[$type] ?? $styles['info'];
@endphp

<div @if ($dismissible) x-data="{ show: true }" x-show="show" x-transition @endif
     role="{{ $type === 'error' ? 'alert' : 'status' }}"
     {{ $attributes->merge(['class' => 'flex gap-3 rounded-xl border px-4 py-3 text-sm '.$box]) }}>
    <x-icon :name="$icon" class="mt-0.5 size-5 {{ $iconColor }}" />
    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div @class(['mt-0.5' => $title])>{{ $slot }}</div>
    </div>
    @if ($dismissible)
        <button type="button" x-on:click="show = false" class="-m-1 rounded-lg p-1 opacity-60 transition hover:opacity-100" aria-label="Tutup">
            <x-icon name="x-mark" class="size-4" />
        </button>
    @endif
</div>
