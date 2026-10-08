@props(['label', 'value', 'icon' => 'chart-bar', 'tone' => 'brand', 'hint' => null, 'href' => null])

@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600',
        'amber' => 'bg-amber-50 text-amber-600',
        'orange' => 'bg-orange-50 text-orange-600',
        'sky' => 'bg-sky-50 text-sky-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'slate' => 'bg-slate-100 text-slate-600',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge(['class' => 'card flex items-start gap-4 p-5'.($href ? ' transition hover:border-brand-200 hover:shadow-md' : '')]) }}>
    <span class="grid size-11 shrink-0 place-items-center rounded-xl {{ $tones[$tone] ?? $tones['brand'] }}">
        <x-icon :name="$icon" class="size-5.5" />
    </span>
    <div class="min-w-0">
        <p class="text-sm font-medium text-slate-500">{{ $label }}</p>
        <p class="mt-1 text-2xl leading-tight font-bold tracking-tight text-slate-900">{{ $value }}</p>
        @if ($hint)
            <p class="mt-0.5 text-xs text-slate-500">{{ $hint }}</p>
        @endif
    </div>
</{{ $tag }}>
