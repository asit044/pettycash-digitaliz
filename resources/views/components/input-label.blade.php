@props(['value', 'required' => false])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-slate-700']) }}>
    {{ $value ?? $slot }}
    @if ($required)
        <span class="text-rose-500">*</span>
    @endif
</label>
