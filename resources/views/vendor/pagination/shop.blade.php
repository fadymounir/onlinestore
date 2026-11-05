{{--
    The storefront's pager.

    Replaces Laravel's stock `pagination::tailwind`, which did not fit this site
    on three counts: a gray/blue palette against a stone/amber storefront,
    `dark:` variants for a dark mode that does not exist here, and end caps
    rounded with physical `rounded-l`/`rounded-r` — the wrong sides in an RTL
    document. Registered as the default in AppServiceProvider, so every
    `$paginator->links()` picks it up.

    Separate round pills rather than one joined bar: each cell is a 40px tap
    target, and joined segments at that size crowd each other on a phone.

    Direction is handled with logical properties and the `rtl:` variant, not
    mirrored markup — the chevrons rotate, and the browser lays the row out
    right-to-left on its own.

    @param \Illuminate\Contracts\Pagination\Paginator $paginator
    @param array $elements  page-number arrays interleaved with '...' strings
--}}
@php
    // One size for every cell keeps the row's rhythm steady.
    $cell = 'inline-flex size-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold transition';
    $idle = 'border border-stone-200 bg-white text-stone-600 hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800';
    $here = 'border border-brand-500 bg-brand-500 text-white shadow-sm shadow-brand-500/30';
    $gone = 'border border-stone-100 bg-stone-50 text-stone-300 cursor-not-allowed';

    // Prev/next widen to hold a label from `sm` up, so they stay pills, not
    // circles, once there is room for the word.
    $step = 'inline-flex h-10 shrink-0 items-center gap-1.5 rounded-full px-3 text-sm font-semibold transition sm:px-4';

    // Each chevron points towards the inline start/end; `rtl:rotate-180` swaps
    // which visual direction that is.
    $svg = 'size-4 rtl:rotate-180';
    $back = '<svg class="'.$svg.'" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.5 7.5 12 15 4.5"/></svg>';
    $forward = '<svg class="'.$svg.'" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4.5 16.5 12 9 19.5"/></svg>';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="التنقل بين الصفحات" class="flex flex-col items-center gap-4">
        {{-- Where the shopper is in the whole set. Western digits, to match
             <x-price> and the rest of the storefront's numbers. --}}
        <p class="text-xs text-stone-500">
            عرض
            <span class="font-semibold text-stone-700">{{ $paginator->firstItem() ?? 0 }}</span>
            –
            <span class="font-semibold text-stone-700">{{ $paginator->lastItem() ?? 0 }}</span>
            من
            <span class="font-semibold text-stone-700">{{ $paginator->total() }}</span>
        </p>

        <div class="flex items-center gap-1.5">
            {{-- previous --}}
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="الصفحة السابقة" class="{{ $step }} {{ $gone }}">
                    {!! $back !!}
                    <span class="hidden sm:inline">السابق</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="الصفحة السابقة"
                   class="{{ $step }} {{ $idle }}">
                    {!! $back !!}
                    <span class="hidden sm:inline">السابق</span>
                </a>
            @endif

            {{-- Numbers are a phone-width luxury; below `sm` the counter below
                 says the same thing in a quarter of the space. --}}
            <span class="hidden items-center gap-1.5 sm:flex">
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-hidden="true" class="{{ $cell }} text-stone-400">&hellip;</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span aria-current="page" class="{{ $cell }} {{ $here }}">{{ $page }}</span>
                            @else
                                <a href="{{ $url }}" aria-label="الصفحة {{ $page }}"
                                   class="{{ $cell }} {{ $idle }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </span>

            <span class="px-2 text-sm text-stone-600 sm:hidden">
                صفحة {{ $paginator->currentPage() }} من {{ $paginator->lastPage() }}
            </span>

            {{-- next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="الصفحة التالية"
                   class="{{ $step }} {{ $idle }}">
                    <span class="hidden sm:inline">التالي</span>
                    {!! $forward !!}
                </a>
            @else
                <span aria-disabled="true" aria-label="الصفحة التالية" class="{{ $step }} {{ $gone }}">
                    <span class="hidden sm:inline">التالي</span>
                    {!! $forward !!}
                </span>
            @endif
        </div>
    </nav>
@endif
