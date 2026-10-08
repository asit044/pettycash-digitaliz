@props(['item', 'showRequester' => false, 'dateField' => 'submitted_at'])

<a href="{{ route('requests.show', ['id' => $item->id]) }}" wire:navigate
   {{ $attributes->merge(['class' => 'group flex items-center gap-4 px-5 py-4 transition hover:bg-slate-50']) }}>
    <span class="hidden size-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-500 transition group-hover:bg-brand-50 group-hover:text-brand-600 sm:grid">
        <x-icon name="document-text" />
    </span>
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="text-sm font-semibold text-slate-900">{{ $item->request_number }}</span>
            <x-status-badge :status="$item->status" short />
        </div>
        <p class="mt-0.5 truncate text-sm text-slate-500">
            @if ($showRequester)
                <span class="font-medium text-slate-700">{{ $item->requester?->name }}</span> ·
            @endif
            {{ $item->description }}
        </p>
    </div>
    <div class="shrink-0 text-right">
        <p class="text-sm font-bold text-slate-900">{{ \App\Support\Money::rupiah($item->nominal) }}</p>
        <p class="mt-0.5 text-xs text-slate-400">{{ $item->{$dateField}?->diffForHumans() }}</p>
    </div>
    <x-icon name="chevron-right" class="hidden size-4 text-slate-300 group-hover:text-slate-500 sm:block" />
</a>
