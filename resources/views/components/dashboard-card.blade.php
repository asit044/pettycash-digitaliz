@props(['title', 'description', 'href' => null, 'cta' => null])

<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
    <div class="p-6">
        <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
        <p class="mt-2 text-sm text-gray-600">{{ $description }}</p>
        @if ($href)
            <a href="{{ $href }}" wire:navigate
               class="mt-4 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                {{ $cta ?: 'Buka' }}
            </a>
        @endif
    </div>
</div>