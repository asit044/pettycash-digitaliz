@props(['status', 'short' => false])

@php
    $enum = $status instanceof \App\Enums\RequestStatus ? $status : \App\Enums\RequestStatus::tryFrom((string) $status);
@endphp

@if ($enum)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap ring-1 ring-inset '.$enum->badgeClasses()]) }}>
        <span class="size-1.5 rounded-full {{ $enum->dotClasses() }}"></span>
        {{ $short ? $enum->shortLabel() : $enum->label() }}
    </span>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700']) }}>{{ $status }}</span>
@endif
