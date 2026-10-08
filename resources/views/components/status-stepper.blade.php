@props(['status'])

@use('App\Enums\RequestStatus', 'S')

@php
    $status = $status instanceof S ? $status : S::from($status);

    // state: done | current | warning | error | upcoming
    $steps = [
        ['label' => 'Diajukan', 'icon' => 'document-plus', 'state' => 'done'],
        ['label' => match ($status) {
            S::NeedsRevision => 'Perlu Revisi',
            S::Rejected => 'Ditolak',
            default => 'Validasi Admin',
        }, 'icon' => match ($status) {
            S::NeedsRevision => 'arrow-path',
            S::Rejected => 'x-circle',
            default => 'clipboard-check',
        }, 'state' => match ($status) {
            S::PendingReview => 'current',
            S::NeedsRevision => 'warning',
            S::Rejected => 'error',
            default => 'done',
        }],
        ['label' => 'Diproses Finance', 'icon' => 'banknotes', 'state' => match ($status) {
            S::Processing => 'current',
            S::Done => 'done',
            default => 'upcoming',
        }],
        ['label' => 'Selesai', 'icon' => 'check-circle', 'state' => $status === S::Done ? 'done' : 'upcoming'],
    ];

    $circle = [
        'done' => 'bg-emerald-500 text-white ring-emerald-100',
        'current' => 'bg-brand-600 text-white ring-brand-100 animate-pulse',
        'warning' => 'bg-orange-500 text-white ring-orange-100',
        'error' => 'bg-rose-500 text-white ring-rose-100',
        'upcoming' => 'bg-white text-slate-400 ring-slate-200',
    ];
    $text = [
        'done' => 'text-slate-900',
        'current' => 'text-brand-700',
        'warning' => 'text-orange-700',
        'error' => 'text-rose-700',
        'upcoming' => 'text-slate-400',
    ];
@endphp

<ol {{ $attributes->merge(['class' => 'grid grid-cols-4']) }}>
    @foreach ($steps as $i => $step)
        <li class="relative flex flex-col items-center text-center">
            @if (! $loop->last)
                <span @class([
                    'absolute top-5 left-1/2 h-0.5 w-full',
                    'bg-emerald-400' => $steps[$i + 1]['state'] !== 'upcoming',
                    'bg-slate-200' => $steps[$i + 1]['state'] === 'upcoming',
                ])></span>
            @endif
            <span class="relative grid size-10 place-items-center rounded-full ring-4 {{ $circle[$step['state']] }}" style="animation-duration: 2.5s">
                <x-icon :name="$step['state'] === 'done' ? 'check' : $step['icon']" class="size-5" />
            </span>
            <span class="mt-2 px-1 text-xs leading-tight font-semibold sm:text-sm {{ $text[$step['state']] }}">{{ $step['label'] }}</span>
        </li>
    @endforeach
</ol>
