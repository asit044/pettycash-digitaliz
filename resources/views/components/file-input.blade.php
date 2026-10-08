@props([
    'model',
    'label',
    'hint' => 'PDF, JPG, PNG, atau WEBP · maks. 10 MB',
    'required' => false,
    'accept' => '.pdf,.jpg,.jpeg,.png,.webp',
])

@php
    $id = 'upload-'.str_replace('.', '-', $model);
@endphp

<div x-data="{ name: null, uploading: false, progress: 0 }"
     x-on:livewire-upload-start="uploading = true; progress = 0"
     x-on:livewire-upload-finish="uploading = false"
     x-on:livewire-upload-error="uploading = false; name = null"
     x-on:livewire-upload-progress="progress = $event.detail.progress"
     {{ $attributes }}>
    <x-input-label :for="$id" :value="$label" :required="$required" />

    <label for="{{ $id }}"
           class="group mt-1.5 flex cursor-pointer items-center gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50/60 px-4 py-3.5 transition hover:border-brand-400 hover:bg-brand-50/40"
           x-bind:class="name ? 'border-solid border-brand-300 bg-brand-50/50' : ''">
        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-white text-slate-400 shadow-sm ring-1 ring-slate-200 transition group-hover:text-brand-500"
              x-bind:class="name ? 'text-brand-600' : ''">
            <x-icon name="cloud-arrow-up" x-show="!name" />
            <x-icon name="paper-clip" x-show="name" x-cloak />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold text-slate-700" x-text="name ?? 'Pilih berkas'">Pilih berkas</span>
            <span class="block text-xs text-slate-500" x-show="!uploading">{{ $hint }}</span>
            <span class="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-slate-200" x-show="uploading" x-cloak>
                <span class="block h-full rounded-full bg-brand-500 transition-all" x-bind:style="`width: ${progress}%`"></span>
            </span>
        </span>
        <span class="hidden text-xs font-semibold text-brand-600 sm:block" x-text="name ? 'Ganti' : 'Telusuri'">Telusuri</span>
    </label>

    <input id="{{ $id }}" type="file" class="sr-only" accept="{{ $accept }}"
           wire:model="{{ $model }}"
           x-on:change="name = $event.target.files[0]?.name ?? null" />

    <x-input-error :messages="$errors->get($model)" class="mt-1.5" />
</div>
