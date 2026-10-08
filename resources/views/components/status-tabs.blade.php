@props(['tabs', 'current', 'model' => 'statusFilter'])

{{-- $tabs: array<string, array{label: string, count?: int}> keyed by filter value ('' = semua) --}}
<div {{ $attributes->merge(['class' => 'no-scrollbar -mx-1 flex min-w-0 gap-1 overflow-x-auto px-1 py-0.5']) }} role="tablist">
    @foreach ($tabs as $value => $tab)
        @php($active = (string) $current === (string) $value)
        <button type="button" role="tab" aria-selected="{{ $active ? 'true' : 'false' }}"
                wire:click="$set('{{ $model }}', '{{ $value }}')"
                @class([
                    'inline-flex shrink-0 items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition',
                    'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200' => $active,
                    'text-slate-500 hover:bg-white/70 hover:text-slate-800' => ! $active,
                ])>
            {{ $tab['label'] }}
            @isset($tab['count'])
                <span @class([
                    'rounded-full px-2 py-0.5 text-xs',
                    'bg-brand-600 text-white' => $active,
                    'bg-slate-200/70 text-slate-600' => ! $active,
                ])>{{ $tab['count'] }}</span>
            @endisset
        </button>
    @endforeach
</div>
