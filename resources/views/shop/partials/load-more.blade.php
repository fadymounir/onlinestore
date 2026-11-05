{{--
    What sits under a product grid in place of a pager.

    The storefront's product listings load as the shopper scrolls: app.js
    watches this link and follows it before it reaches the bottom of the
    screen, appending the next twelve cards to the grid above.

    It is a real <a href> to the next page, so with JavaScript off it is an
    ordinary "next page" link and the listing is still walkable — which is the
    only reason the numbered pager could be dropped. The pager itself still
    exists for the order history, where paging is how you want to read.

    @param \Illuminate\Contracts\Pagination\Paginator $products
    @param string $grid  CSS selector of the grid the next page is added to
--}}
@if ($products->hasMorePages())
    <div class="mt-10 flex justify-center">
        {{-- The value is where app.js puts the placeholder cards while the
             page is in flight. Where the real ones go is named by the server
             in its response, so the two can never disagree about the order. --}}
        <a href="{{ $products->nextPageUrl() }}" rel="next" data-load-more="{{ $grid }}"
           class="inline-flex items-center gap-2 rounded-full border border-stone-200 bg-white px-7 py-3.5
                  text-sm font-bold text-stone-800 shadow-soft transition
                  hover:border-brand-300 hover:bg-brand-50 hover:text-brand-800">
            عرض المزيد
            <svg class="size-4 text-brand-600" fill="none" stroke="currentColor" stroke-width="2.2"
                 viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
            </svg>
        </a>
    </div>
@elseif ($products->total() > $products->perPage())
    {{-- Only worth saying to someone who actually scrolled for it. --}}
    <p class="mt-10 text-center text-xs text-stone-400">وصلت إلى نهاية النتائج</p>
@endif
