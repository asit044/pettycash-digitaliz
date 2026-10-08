@php
    if (! isset($scrollTo)) {
        $scrollTo = 'body';
    }

    $scrollIntoViewJsSnippet = ($scrollTo !== false)
        ? <<<JS
           (\$el.closest('{$scrollTo}') || document.querySelector('{$scrollTo}')).scrollIntoView()
        JS
        : '';
@endphp

<div>
    @if ($paginator->hasPages())
        <nav role="navigation" aria-label="Navigasi halaman" class="flex items-center justify-between gap-3">
            <p class="hidden text-sm text-slate-500 sm:block">
                Menampilkan <span class="font-semibold text-slate-700">{{ $paginator->firstItem() }}</span>–<span class="font-semibold text-slate-700">{{ $paginator->lastItem() }}</span>
                dari <span class="font-semibold text-slate-700">{{ $paginator->total() }}</span>
            </p>

            <div class="flex flex-1 items-center justify-between gap-1 sm:flex-none sm:justify-end">
                @if ($paginator->onFirstPage())
                    <span class="btn-secondary pointer-events-none px-3 py-2 opacity-50"><x-icon name="arrow-left" class="size-4" /><span class="sm:hidden">Sebelumnya</span></span>
                @else
                    <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled"
                            class="btn-secondary px-3 py-2" aria-label="Sebelumnya"><x-icon name="arrow-left" class="size-4" /><span class="sm:hidden">Sebelumnya</span></button>
                @endif

                <div class="hidden items-center gap-1 sm:flex">
                    @foreach ($elements as $element)
                        @if (is_string($element))
                            <span class="px-2 text-sm text-slate-400">{{ $element }}</span>
                        @endif

                        @if (is_array($element))
                            @foreach ($element as $page => $url)
                                <span wire:key="paginator-{{ $paginator->getPageName() }}-page{{ $page }}">
                                    @if ($page == $paginator->currentPage())
                                        <span aria-current="page" class="grid size-9 place-items-center rounded-xl bg-brand-600 text-sm font-semibold text-white">{{ $page }}</span>
                                    @else
                                        <button type="button" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}"
                                                class="grid size-9 place-items-center rounded-xl text-sm font-semibold text-slate-600 transition hover:bg-slate-100">{{ $page }}</button>
                                    @endif
                                </span>
                            @endforeach
                        @endif
                    @endforeach
                </div>

                @if ($paginator->hasMorePages())
                    <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" x-on:click="{{ $scrollIntoViewJsSnippet }}" wire:loading.attr="disabled"
                            class="btn-secondary px-3 py-2" aria-label="Berikutnya"><span class="sm:hidden">Berikutnya</span><x-icon name="arrow-right" class="size-4" /></button>
                @else
                    <span class="btn-secondary pointer-events-none px-3 py-2 opacity-50"><span class="sm:hidden">Berikutnya</span><x-icon name="arrow-right" class="size-4" /></span>
                @endif
            </div>
        </nav>
    @endif
</div>
