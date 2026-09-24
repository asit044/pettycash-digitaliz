@props(['status'])

@php
    $color = match ($status) {
        'pending_review' => 'bg-yellow-100 text-yellow-800',
        'needs_revision' => 'bg-orange-100 text-orange-800',
        'rejected' => 'bg-red-100 text-red-800',
        'processing' => 'bg-blue-100 text-blue-800',
        'done' => 'bg-green-100 text-green-800',
        default => 'bg-gray-100 text-gray-800',
    };

    $label = match ($status) {
        'pending_review' => 'Menunggu Validasi Admin',
        'needs_revision' => 'Perlu Revisi',
        'rejected' => 'Ditolak',
        'processing' => 'Diproses Finance',
        'done' => 'Selesai',
        default => $status,
    };
@endphp

<span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $color }}">
    {{ $label }}
</span>